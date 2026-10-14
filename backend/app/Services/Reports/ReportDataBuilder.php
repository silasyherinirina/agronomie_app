<?php

namespace App\Services\Reports;

use App\Models\AgriculturalCampaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Module Rapports — construit une ReportData neutre pour un type de rapport donné, sur la base
 * des MÊMES règles de périmètre par rôle et des MÊMES filtres que les endpoints CRUD/statistiques
 * existants (§19 : jamais un second calcul divergent — voir ReportService pour l'orchestration
 * et les Formatters pour la sérialisation).
 *
 * Priorité cahier des charges §18 : production, récoltes, ventes (les 3 types implémentés ici).
 *
 * Restructuration "cycle cultural = relation racine" : deux modes coexistent (§10) —
 *   Mode A (global, `crop_cycle_id` absent) : les filtres indépendants existants restent
 *   pleinement fonctionnels ("les filtres globaux peuvent être utilisés selon les
 *   fonctionnalités déjà prévues") — comportement inchangé, voir buildProduction/buildHarvests/
 *   buildSales ci-dessous.
 *   Mode B (`crop_cycle_id` fourni) : le cycle devient la contrainte principale — toute autre
 *   dimension explicitement envoyée doit lui être cohérente (rejetée sinon, §11/§26), et le
 *   filtrage effectif se fait sur CE cycle précisément (jamais reconstruit via ses attributs
 *   dérivés, qui pourraient coïncider avec un autre cycle).
 */
class ReportDataBuilder
{
    public function __construct(private readonly SaleService $sales) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function build(string $type, array $filters, User $user): ReportData
    {
        $cropCycle = $this->resolveCropCycle($filters, $user);

        return match ($type) {
            'production' => $this->buildProduction($filters, $user, $cropCycle),
            'harvests' => $this->buildHarvests($filters, $user, $cropCycle),
            'sales' => $this->buildSales($filters, $user, $cropCycle),
            default => throw new UnknownReportTypeException($type),
        };
    }

    /**
     * Résout et valide le cycle cultural racine (§10/§11) : introuvable, hors du périmètre du
     * rôle, ou incohérent avec une autre dimension explicitement envoyée -> rejeté (422), jamais
     * silencieusement ignoré ou corrigé (§11 : "signaler clairement l'incohérence").
     *
     * @param  array<string, mixed>  $filters
     */
    private function resolveCropCycle(array $filters, User $user): ?CropCycle
    {
        if (empty($filters['crop_cycle_id'])) {
            return null;
        }

        $cycle = CropCycle::with(['plot.farmer', 'plot.cooperative', 'crop', 'variety', 'campaign'])
            ->find($filters['crop_cycle_id']);

        if (! $cycle) {
            throw ValidationException::withMessages(['crop_cycle_id' => ['Ce cycle cultural est introuvable.']]);
        }

        if ($user->role === 'cooperative_manager' && $cycle->plot?->cooperative_id !== ($user->managedCooperative?->id ?? 0)) {
            throw ValidationException::withMessages(['crop_cycle_id' => ["Ce cycle cultural n'appartient pas à votre coopérative."]]);
        }

        if ($user->role === 'farmer' && $cycle->plot?->farmer_id !== ($user->farmer?->id ?? 0)) {
            throw ValidationException::withMessages(['crop_cycle_id' => ["Ce cycle cultural ne vous appartient pas."]]);
        }

        $this->assertFiltersConsistentWithCycle($filters, $cycle);

        return $cycle;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function assertFiltersConsistentWithCycle(array $filters, CropCycle $cycle): void
    {
        $expected = [
            'plot_id' => $cycle->plot_id,
            'crop_id' => $cycle->crop_id,
            'variety_id' => $cycle->variety_id,
            'campaign_id' => $cycle->campaign_id,
            'farmer_id' => $cycle->plot?->farmer_id,
            'cooperative_id' => $cycle->plot?->cooperative_id,
        ];

        $errors = [];
        foreach ($expected as $field => $value) {
            if (! empty($filters[$field]) && $value !== null && (int) $filters[$field] !== $value) {
                $errors[$field] = ['Incohérent avec le cycle cultural sélectionné.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Rapport de production (§18-1) : agrégat quantité/rendement par culture et par unité —
     * jamais une somme entre unités hétérogènes (§25), même logique que
     * DashboardService::productionByCrop()/yieldByCrop().
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildProduction(array $filters, User $user, ?CropCycle $cropCycle): ReportData
    {
        [$cooperativeId, $farmerId] = $this->resolveScope($user, $filters, $cropCycle);

        $rows = $this->scopedHarvestJoin($cooperativeId, $farmerId, $filters, $cropCycle)
            ->join('crops', 'crop_cycles.crop_id', '=', 'crops.id')
            ->select(
                'crops.name as crop_name',
                'harvests.unit',
                DB::raw('SUM(harvests.quantity) as total_quantity'),
                DB::raw('AVG(harvests.yield_value) as average_yield'),
                DB::raw('COUNT(*) as harvests_count'),
            )
            ->groupBy('crops.name', 'harvests.unit')
            ->orderByDesc('total_quantity')
            ->get();

        $data = $rows->map(fn ($r) => [
            'crop' => $r->crop_name,
            'unit' => $r->unit,
            'total_quantity' => round((float) $r->total_quantity, 3),
            'average_yield' => $r->average_yield !== null ? round((float) $r->average_yield, 3) : null,
            'harvests_count' => (int) $r->harvests_count,
        ]);

        return new ReportData(
            type: 'production',
            title: 'Rapport de production',
            generatedAt: new DateTimeImmutable(),
            filtersSummary: $this->filtersSummary($filters, $cooperativeId, $farmerId, $cropCycle),
            summary: array_merge(
                [
                    'Cultures distinctes' => (string) $data->pluck('crop')->unique()->count(),
                    'Récoltes prises en compte' => (string) $data->sum('harvests_count'),
                ],
                $this->quantityByUnitSummary($data, 'Production totale', 'total_quantity'),
            ),
            columns: [
                'crop' => 'Culture',
                'unit' => 'Unité',
                'total_quantity' => 'Quantité totale',
                'average_yield' => 'Rendement moyen',
                'harvests_count' => 'Nb récoltes',
            ],
            rows: $data->all(),
        );
    }

    /**
     * Rapport des récoltes (§18-2/§22) : liste détaillée, une ligne par récolte réelle. En Mode B,
     * seules les récoltes du cycle précis sont incluses — jamais celles d'un autre cycle partageant
     * la même culture/campagne (§22 : "Cycle Maïs 2026 ne doit pas inclure... Riz 2026").
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildHarvests(array $filters, User $user, ?CropCycle $cropCycle): ReportData
    {
        [$cooperativeId, $farmerId] = $this->resolveScope($user, $filters, $cropCycle);

        $rows = $this->scopedHarvestJoin($cooperativeId, $farmerId, $filters, $cropCycle)
            ->join('crops', 'crop_cycles.crop_id', '=', 'crops.id')
            ->leftJoin('crop_varieties', 'crop_cycles.variety_id', '=', 'crop_varieties.id')
            ->join('agricultural_campaigns', 'crop_cycles.campaign_id', '=', 'agricultural_campaigns.id')
            ->select(
                'harvests.harvest_date', 'plots.name as plot_name', 'crops.name as crop_name',
                'crop_varieties.name as variety_name', 'agricultural_campaigns.name as campaign_name',
                'harvests.quantity', 'harvests.unit', 'harvests.yield_value', 'harvests.quality',
            )
            ->orderByDesc('harvests.harvest_date')
            ->get();

        $data = $rows->map(fn ($r) => [
            'harvest_date' => $r->harvest_date,
            'plot' => $r->plot_name,
            'crop' => $r->crop_name,
            'variety' => $r->variety_name ?? '—',
            'campaign' => $r->campaign_name,
            'quantity' => round((float) $r->quantity, 3),
            'unit' => $r->unit,
            'yield_value' => $r->yield_value !== null ? round((float) $r->yield_value, 3) : null,
            'quality' => $r->quality ?? '—',
        ]);

        return new ReportData(
            type: 'harvests',
            title: 'Rapport des récoltes',
            generatedAt: new DateTimeImmutable(),
            filtersSummary: $this->filtersSummary($filters, $cooperativeId, $farmerId, $cropCycle),
            summary: array_merge(
                ['Nombre de récoltes' => (string) $data->count()],
                $this->quantityByUnitSummary($data, 'Quantité totale'),
            ),
            columns: [
                'harvest_date' => 'Date',
                'plot' => 'Parcelle',
                'crop' => 'Culture',
                'variety' => 'Variété',
                'campaign' => 'Campagne',
                'quantity' => 'Quantité',
                'unit' => 'Unité',
                'yield_value' => 'Rendement',
                'quality' => 'Qualité',
            ],
            rows: $data->all(),
        );
    }

    /**
     * Rapport des ventes (§18-3/§23) : liste détaillée, mêmes filtres/périmètre que GET /api/sales
     * (réutilise SaleService::scopedQuery()/applyFilters() — §19, jamais une seconde
     * implémentation qui pourrait diverger). `applyFilters()` filtre déjà `sales.crop_cycle_id`
     * exactement (colonne directe, voir SaleService) : une vente agrégée (`crop_cycle_id` NULL)
     * en est naturellement exclue dès qu'un cycle précis est demandé — aucun cas particulier à
     * coder, jamais une vente agrégée présentée comme rattachée à un cycle (§23).
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildSales(array $filters, User $user, ?CropCycle $cropCycle): ReportData
    {
        $query = $this->sales->scopedQuery($user);
        $this->sales->applyFilters($query, $filters);

        $sales = $query->with(['cooperative', 'crop', 'variety'])->orderByDesc('sale_date')->get();

        $data = $sales->map(fn (Sale $s) => [
            'sale_date' => $s->sale_date?->toDateString(),
            'cooperative' => $s->cooperative?->name ?? '—',
            'crop' => $s->crop?->name ?? '—',
            'variety' => $s->variety?->name ?? '—',
            'buyer_name' => $s->buyer_name ?? '—',
            'quantity' => (float) $s->quantity,
            'unit' => $s->unit,
            'unit_price' => (float) $s->unit_price,
            'total_amount' => (float) $s->total_amount,
            'currency' => $s->currency,
        ]);

        // Une devise homogène (MGA par défaut) est sommable directement ; en présence de
        // plusieurs devises, le total est omis plutôt qu'additionné à tort (même principe que
        // §25 pour les unités de quantité — jamais une conversion inventée).
        $currencies = $data->pluck('currency')->unique();
        $totalAmount = $currencies->count() === 1 ? round($data->sum('total_amount'), 2) : null;

        [$cooperativeId, $farmerId] = $this->resolveScope($user, $filters, $cropCycle);

        return new ReportData(
            type: 'sales',
            title: 'Rapport des ventes',
            generatedAt: new DateTimeImmutable(),
            filtersSummary: $this->filtersSummary($filters, $cooperativeId, $farmerId, $cropCycle),
            summary: array_merge(
                ['Nombre de ventes' => (string) $data->count()],
                $totalAmount !== null ? ["Chiffre d'affaires" => $this->formatAmount($totalAmount, (string) $currencies->first())] : [],
                $this->quantityByUnitSummary($data, 'Quantité vendue'),
            ),
            columns: [
                'sale_date' => 'Date',
                'cooperative' => 'Coopérative',
                'crop' => 'Culture',
                'variety' => 'Variété',
                'buyer_name' => 'Acheteur',
                'quantity' => 'Quantité',
                'unit' => 'Unité',
                'unit_price' => 'Prix unitaire',
                'total_amount' => 'Montant total',
                'currency' => 'Devise',
            ],
            rows: $data->all(),
            totals: $totalAmount !== null ? ['total_amount' => $this->formatAmount($totalAmount, (string) $currencies->first())] : [],
        );
    }

    /**
     * Périmètre de lecture selon le rôle — même principe que DashboardService::resolveScope :
     * jamais élargi via les filtres pour cooperative_manager/farmer. Dupliqué volontairement
     * (au lieu de réutiliser la méthode privée de DashboardService) pour ne pas coupler ce
     * service, entièrement nouveau, à un service existant déjà testé et stable.
     *
     * Mode B (`$cropCycle` fourni) : la coopérative/l'agriculteur sont DÉRIVÉS du cycle (via sa
     * parcelle) plutôt que lus depuis les filtres — cohérent avec resolveCropCycle() qui a déjà
     * vérifié que ce cycle est dans le périmètre du rôle.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveScope(User $user, array $filters, ?CropCycle $cropCycle): array
    {
        if ($cropCycle) {
            return [$cropCycle->plot?->cooperative_id, $cropCycle->plot?->farmer_id];
        }

        return match ($user->role) {
            'cooperative_manager' => [$user->managedCooperative?->id ?? 0, null],
            'farmer' => [null, $user->farmer?->id ?? 0],
            default => [
                ! empty($filters['cooperative_id']) ? (int) $filters['cooperative_id'] : null,
                ! empty($filters['farmer_id']) ? (int) $filters['farmer_id'] : null,
            ],
        };
    }

    /**
     * Base commune des rapports "récoltes"/"production" : jointure harvests -> crop_cycles ->
     * plots, avec périmètre par rôle ET filtres (parcelle/culture/variété/campagne/période)
     * appliqués une seule fois.
     *
     * Mode B (`$cropCycle` fourni) : contrainte exacte sur `harvests.crop_cycle_id` — jamais
     * reconstruite via les attributs dérivés du cycle (crop_id/campaign_id...), qui pourraient
     * coïncider avec un AUTRE cycle réel (§22 : "jamais celles d'un autre cycle partageant la
     * même culture/campagne"). Les filtres coopérative/parcelle/culture/variété/campagne
     * ci-dessous restent appliqués en plus, par cohérence défensive (déjà garantis identiques
     * au cycle par resolveScope()/resolveCropCycle(), donc sans effet observable).
     */
    private function scopedHarvestJoin(?int $cooperativeId, ?int $farmerId, array $filters, ?CropCycle $cropCycle): Builder
    {
        return Harvest::query()
            ->join('crop_cycles', 'harvests.crop_cycle_id', '=', 'crop_cycles.id')
            ->join('plots', 'crop_cycles.plot_id', '=', 'plots.id')
            ->when($cropCycle, fn ($q) => $q->where('harvests.crop_cycle_id', $cropCycle->id))
            ->when($cooperativeId, fn ($q) => $q->where('plots.cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('plots.farmer_id', $farmerId))
            ->when(! empty($filters['plot_id']), fn ($q) => $q->where('plots.id', $filters['plot_id']))
            ->when(! empty($filters['crop_id']), fn ($q) => $q->where('crop_cycles.crop_id', $filters['crop_id']))
            ->when(! empty($filters['variety_id']), fn ($q) => $q->where('crop_cycles.variety_id', $filters['variety_id']))
            ->when(! empty($filters['campaign_id']), fn ($q) => $q->where('crop_cycles.campaign_id', $filters['campaign_id']))
            ->when(! empty($filters['start_date']), fn ($q) => $q->whereDate('harvests.harvest_date', '>=', $filters['start_date']))
            ->when(! empty($filters['end_date']), fn ($q) => $q->whereDate('harvests.harvest_date', '<=', $filters['end_date']));
    }

    /**
     * Libellés humains des filtres réellement appliqués — affichés en en-tête PDF/Excel (§22) et
     * dans le corps CSV (§20), pour qu'un rapport téléchargé reste compréhensible hors contexte.
     *
     * Mode B (`$cropCycle` fourni, §16) : hiérarchie complète Cycle -> Parcelle -> Culture ->
     * Variété -> Agriculteur -> Coopérative -> Campagne, toutes dérivées du même cycle — jamais
     * les filtres indépendants du Mode A (qui n'ont d'ailleurs pas pu être envoyés en
     * contradiction, resolveCropCycle() les aurait déjà rejetés).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function filtersSummary(array $filters, ?int $cooperativeId, ?int $farmerId, ?CropCycle $cropCycle): array
    {
        if ($cropCycle) {
            $farmer = $cropCycle->plot?->farmer;

            return [
                'Cycle cultural / récolte' => $this->cycleLabel($cropCycle),
                'Parcelle' => $cropCycle->plot?->name ?? 'Non spécifiée',
                'Culture' => $cropCycle->crop?->name ?? 'Non spécifiée',
                'Variété' => $cropCycle->variety?->name ?? 'Non spécifiée',
                'Agriculteur' => $farmer ? trim("{$farmer->first_name} {$farmer->last_name}") : 'Non spécifié',
                'Coopérative' => $cropCycle->plot?->cooperative?->name ?? 'Non spécifiée',
                'Campagne' => $cropCycle->campaign ? "{$cropCycle->campaign->name} ({$cropCycle->campaign->year})" : 'Non spécifiée',
            ];
        }

        $summary = [];

        if ($cooperativeId) {
            $summary['Coopérative'] = Cooperative::find($cooperativeId)?->name ?? "#{$cooperativeId}";
        }

        if ($farmerId) {
            $farmer = Farmer::find($farmerId);
            $summary['Agriculteur'] = $farmer ? trim("{$farmer->first_name} {$farmer->last_name}") : "#{$farmerId}";
        }

        if (! empty($filters['crop_id'])) {
            $summary['Culture'] = Crop::find($filters['crop_id'])?->name ?? "#{$filters['crop_id']}";
        }

        if (! empty($filters['campaign_id'])) {
            $campaign = AgriculturalCampaign::find($filters['campaign_id']);
            $summary['Campagne'] = $campaign ? "{$campaign->name} ({$campaign->year})" : "#{$filters['campaign_id']}";
        }

        if (! empty($filters['start_date']) || ! empty($filters['end_date'])) {
            $summary['Période'] = trim(($filters['start_date'] ?? '…').' → '.($filters['end_date'] ?? '…'));
        }

        return $summary;
    }

    /**
     * Libellé désambiguïsant du cycle (§17) : deux cycles peuvent partager parcelle+culture
     * (campagnes différentes) — jamais un "Cycle #12" opaque, toujours parcelle/culture/campagne/
     * statut. Même logique que le frontend (SaleFormPage::cycleLabel()).
     */
    private function cycleLabel(CropCycle $cycle): string
    {
        $statusLabel = match ($cycle->status) {
            'in_progress' => 'en cours',
            'harvested' => 'récolté',
            'failed' => 'échoué',
            default => $cycle->status,
        };
        $base = ($cycle->plot?->name ?? "#{$cycle->plot_id}").' — '.($cycle->crop?->name ?? "#{$cycle->crop_id}");
        $campaign = $cycle->campaign ? "{$cycle->campaign->name} ({$cycle->campaign->year})" : null;

        return $base.' — '.implode(', ', array_filter([$campaign, $statusLabel]));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function quantityByUnitSummary(Collection $rows, string $label, string $quantityKey = 'quantity'): array
    {
        return $rows->groupBy('unit')
            ->map(fn ($group) => round((float) $group->sum($quantityKey), 3))
            ->mapWithKeys(fn ($qty, $unit) => ["{$label} ({$unit})" => number_format($qty, 3, ',', ' ')." {$unit}"])
            ->all();
    }

    private function formatAmount(float $amount, string $currency): string
    {
        return number_format($amount, 2, ',', ' ')." {$currency}";
    }
}

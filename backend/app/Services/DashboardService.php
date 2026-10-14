<?php

namespace App\Services;

use App\Models\AgriculturalCampaign;
use App\Models\Cooperative;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\InputUsage;
use App\Models\Plot;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats du tableau de bord (Phase 7, Module H). Toute la logique de calcul vit ici plutôt
 * que dans le Controller ou côté frontend : les totaux (production, rendement, superficie)
 * sont calculés par la base de données, jamais rapatriés puis recalculés côté client.
 *
 * Règle importante (cahier des charges §24 — ne jamais inventer un résultat) : `harvests.unit`
 * et `inputs.unit` sont des champs texte libres, non normalisés (kg, sac, tonne...). Additionner
 * des quantités d'unités différentes produirait un total mensonger : toutes les sommes de
 * quantités sont donc groupées par unité plutôt que fusionnées en un faux total unique. Seuls
 * area_ha (toujours en hectares, calculé par PostGIS) et cost (toujours en Ariary) sont des
 * grandeurs homogènes que l'on peut sommer directement.
 */
class DashboardService
{
    public function __construct(private readonly SaleService $sales) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, ?int $requestedCooperativeId, ?int $campaignId, ?int $plotId = null): array
    {
        [$cooperativeId, $farmerId] = $this->resolveScope($user, $requestedCooperativeId);

        return [
            'filters' => [
                'cooperative_id' => $cooperativeId,
                'farmer_id' => $farmerId,
                'campaign_id' => $campaignId,
                'plot_id' => $plotId,
            ],
            'kpis' => $this->kpis($cooperativeId, $farmerId, $plotId, $campaignId),
            'harvest_totals_by_unit' => $this->harvestTotalsByUnit($cooperativeId, $farmerId, $campaignId, $plotId),
            'average_yield_by_unit' => $this->averageYieldByUnit($cooperativeId, $farmerId, $campaignId, $plotId),
            'production_by_crop' => $this->productionByCrop($cooperativeId, $farmerId, $campaignId, $plotId),
            'yield_by_crop' => $this->yieldByCrop($cooperativeId, $farmerId, $campaignId, $plotId),
            'production_by_campaign' => $this->productionByCampaign($cooperativeId, $farmerId, $plotId),
            'input_consumption' => $this->inputConsumption($cooperativeId, $farmerId, $campaignId, $plotId),
            'geography' => $this->geography($cooperativeId, $farmerId, $plotId),
            'recent_activity' => $this->recentActivity($cooperativeId, $farmerId, plotId: $plotId),
            'alerts' => $this->alerts($cooperativeId, $farmerId, $plotId),
            // Module M — Ventes agricoles. `sales` réutilise SaleService::statistics() (le MÊME
            // calcul que GET /api/sales/statistics) pour garantir qu'un chiffre d'affaires
            // affiché ici ne diverge jamais de celui du module Rapports pour les mêmes filtres
            // (cahier des charges §27).
            'sales' => $this->sales->statistics($this->scopedSales($cooperativeId, $farmerId, $campaignId, $plotId)),
            'sales_by_campaign' => $this->salesByCampaign($cooperativeId, $farmerId, $plotId),
            'recent_sales' => $this->recentSales($cooperativeId, $farmerId, plotId: $plotId),
        ];
    }

    /**
     * Détermine le périmètre effectif de données visibles selon le rôle. Un cooperative_manager
     * ou un farmer ne peuvent jamais élargir leur périmètre via le paramètre de requête
     * (même logique de forçage que dans les Controllers CRUD existants).
     *
     * @return array{0: ?int, 1: ?int} [cooperativeId, farmerId]
     */
    private function resolveScope(User $user, ?int $requestedCooperativeId): array
    {
        return match ($user->role) {
            'cooperative_manager' => [$user->managedCooperative?->id ?? 0, null],
            'farmer' => [null, $user->farmer?->id ?? 0],
            default => [$requestedCooperativeId, null], // admin, analyst
        };
    }

  /**
 * Calcule les KPI selon le périmètre sélectionné dans le Dashboard.
 *
 * Règles :
 * - aucun filtre : toutes les données accessibles ;
 * - cooperative_id : données de la coopérative ;
 * - plot_id : données de la parcelle sélectionnée ;
 * - campaign_id : campagnes correspondant au périmètre sélectionné ;
 * - les cartes Coopératives et Agriculteurs restent toujours visibles.
 *
 * @return array<string, mixed>
 */
private function kpis(
    ?int $cooperativeId,
    ?int $farmerId,
    ?int $plotId = null,
    ?int $campaignId = null
): array {
    /*
     * ------------------------------------------------------------------
     * 1. PARCELLES
     * ------------------------------------------------------------------
     *
     * C'est le périmètre de référence pour les KPI liés à :
     * - la coopérative
     * - l'agriculteur
     * - la superficie
     *
     * Une campagne ne filtre pas directement les parcelles :
     * une même parcelle peut avoir plusieurs cycles/campagnes.
     */
    $plots = Plot::query()
        ->when(
            $cooperativeId,
            fn ($q) => $q->where('cooperative_id', $cooperativeId)
        )
        ->when(
            $farmerId,
            fn ($q) => $q->where('farmer_id', $farmerId)
        )
        ->when(
            $plotId,
            fn ($q) => $q->where('id', $plotId)
        );

    /*
     * ------------------------------------------------------------------
     * 2. COOPÉRATIVES
     * ------------------------------------------------------------------
     *
     * Sans filtre :
     *     toutes les coopératives.
     *
     * Avec cooperative_id :
     *     uniquement cette coopérative.
     *
     * Avec plot_id :
     *     la coopérative propriétaire de cette parcelle.
     */
    $cooperatives = Cooperative::query()
        ->when(
            $cooperativeId,
            fn ($q) => $q->where('id', $cooperativeId)
        )
        ->when(
            $plotId,
            fn ($q) => $q->whereHas(
                'plots',
                fn ($plotQuery) => $plotQuery->where('id', $plotId)
            )
        );

    /*
     * ------------------------------------------------------------------
     * 3. AGRICULTEURS
     * ------------------------------------------------------------------
     *
     * Sans filtre :
     *     tous les agriculteurs.
     *
     * Avec cooperative_id :
     *     agriculteurs de cette coopérative.
     *
     * Avec plot_id :
     *     agriculteur rattaché à cette parcelle.
     *
     * La carte Agriculteurs reste donc toujours présente.
     */
    $farmers = Farmer::query()
        ->when(
            $cooperativeId,
            fn ($q) => $q->where('cooperative_id', $cooperativeId)
        )
        ->when(
            $farmerId,
            fn ($q) => $q->where('id', $farmerId)
        )
        ->when(
            $plotId,
            fn ($q) => $q->whereHas(
                'plots',
                fn ($plotQuery) => $plotQuery->where('id', $plotId)
            )
        );

    /*
     * ------------------------------------------------------------------
     * 4. CAMPAGNES
     * ------------------------------------------------------------------
     *
     * Une campagne est reliée aux parcelles par :
     *
     * AgriculturalCampaign
     *        ↓ cropCycles()
     * CropCycle
     *        ↓ plot()
     * Plot
     *        ↓ cooperative_id
     * Cooperative
     *
     * Avec plot_id :
     *     seules les campagnes utilisées sur cette parcelle.
     *
     * Avec cooperative_id :
     *     seules les campagnes utilisées sur les parcelles
     *     de cette coopérative.
     *
     * Avec campaign_id :
     *     seule la campagne sélectionnée, si elle appartient
     *     au périmètre demandé.
     */
    $campaigns = AgriculturalCampaign::query()
        ->when(
            $campaignId,
            fn ($q) => $q->where('id', $campaignId)
        )
        ->when(
            $cooperativeId,
            fn ($q) => $q->whereHas(
                'cropCycles.plot',
                fn ($plotQuery) => $plotQuery->where(
                    'cooperative_id',
                    $cooperativeId
                )
            )
        )
        ->when(
            $plotId,
            fn ($q) => $q->whereHas(
                'cropCycles',
                fn ($cycleQuery) => $cycleQuery->where(
                    'plot_id',
                    $plotId
                )
            )
        );

    /*
     * ------------------------------------------------------------------
     * 5. RÉSULTAT FINAL
     * ------------------------------------------------------------------
     */
    return [
        'cooperatives_count' => (clone $cooperatives)->count(),

        'farmers_count' => (clone $farmers)->count(),

        'plots_count' => (clone $plots)->count(),

        'total_area_ha' => (float) (clone $plots)->sum('area_ha'),

        'campaigns_count' => (clone $campaigns)->count(),
    ];
}

    /**
     * @return Collection<int, array{unit: string, total_quantity: float, harvests_count: int}>
     */
    private function harvestTotalsByUnit(?int $cooperativeId, ?int $farmerId, ?int $campaignId, ?int $plotId = null): Collection
    {
        return $this->scopedHarvests($cooperativeId, $farmerId, $campaignId, $plotId)
            ->select('harvests.unit', DB::raw('SUM(harvests.quantity) as total_quantity'), DB::raw('COUNT(*) as harvests_count'))
            ->groupBy('harvests.unit')
            ->orderByDesc('total_quantity')
            ->get()
            ->map(fn ($row) => [
                'unit' => $row->unit,
                'total_quantity' => (float) $row->total_quantity,
                'harvests_count' => (int) $row->harvests_count,
            ]);
    }

    /**
     * @return Collection<int, array{unit: string, average_yield: float, sample_size: int}>
     */
    private function averageYieldByUnit(?int $cooperativeId, ?int $farmerId, ?int $campaignId, ?int $plotId = null): Collection
    {
        return $this->scopedHarvests($cooperativeId, $farmerId, $campaignId, $plotId)
            ->whereNotNull('harvests.yield_value')
            ->select('harvests.unit', DB::raw('AVG(harvests.yield_value) as average_yield'), DB::raw('COUNT(*) as sample_size'))
            ->groupBy('harvests.unit')
            ->get()
            ->map(fn ($row) => [
                'unit' => $row->unit,
                'average_yield' => round((float) $row->average_yield, 3),
                'sample_size' => (int) $row->sample_size,
            ]);
    }

    /**
     * @return Collection<int, array{crop_id: int, crop_name: string, unit: string, total_quantity: float}>
     */
    private function productionByCrop(?int $cooperativeId, ?int $farmerId, ?int $campaignId, ?int $plotId = null): Collection
    {
        return $this->scopedHarvests($cooperativeId, $farmerId, $campaignId, $plotId)
            ->join('crops', 'crop_cycles.crop_id', '=', 'crops.id')
            ->select('crops.id as crop_id', 'crops.name as crop_name', 'harvests.unit', DB::raw('SUM(harvests.quantity) as total_quantity'))
            ->groupBy('crops.id', 'crops.name', 'harvests.unit')
            ->orderByDesc('total_quantity')
            ->get()
            ->map(fn ($row) => [
                'crop_id' => (int) $row->crop_id,
                'crop_name' => $row->crop_name,
                'unit' => $row->unit,
                'total_quantity' => (float) $row->total_quantity,
            ]);
    }

    /**
     * @return Collection<int, array{crop_id: int, crop_name: string, unit: string, average_yield: float}>
     */
    private function yieldByCrop(?int $cooperativeId, ?int $farmerId, ?int $campaignId, ?int $plotId = null): Collection
    {
        return $this->scopedHarvests($cooperativeId, $farmerId, $campaignId, $plotId)
            ->join('crops', 'crop_cycles.crop_id', '=', 'crops.id')
            ->whereNotNull('harvests.yield_value')
            ->select('crops.id as crop_id', 'crops.name as crop_name', 'harvests.unit', DB::raw('AVG(harvests.yield_value) as average_yield'))
            ->groupBy('crops.id', 'crops.name', 'harvests.unit')
            ->orderByDesc('average_yield')
            ->get()
            ->map(fn ($row) => [
                'crop_id' => (int) $row->crop_id,
                'crop_name' => $row->crop_name,
                'unit' => $row->unit,
                'average_yield' => round((float) $row->average_yield, 3),
            ]);
    }

    /**
     * Production dans le temps (par campagne, triée par année) — sert de série pour un graphique
     * d'évolution. Non filtrée par campaign_id (cela viderait la série à un seul point).
     *
     * @return Collection<int, array{campaign_id: int, campaign_name: string, year: int, unit: string, total_quantity: float}>
     */
    private function productionByCampaign(?int $cooperativeId, ?int $farmerId, ?int $plotId = null): Collection
    {
        return $this->scopedHarvests($cooperativeId, $farmerId, null, $plotId)
            ->join('agricultural_campaigns', 'crop_cycles.campaign_id', '=', 'agricultural_campaigns.id')
            ->select(
                'agricultural_campaigns.id as campaign_id',
                'agricultural_campaigns.name as campaign_name',
                'agricultural_campaigns.year',
                'harvests.unit',
                DB::raw('SUM(harvests.quantity) as total_quantity'),
            )
            ->groupBy('agricultural_campaigns.id', 'agricultural_campaigns.name', 'agricultural_campaigns.year', 'harvests.unit')
            ->orderBy('agricultural_campaigns.year')
            ->get()
            ->map(fn ($row) => [
                'campaign_id' => (int) $row->campaign_id,
                'campaign_name' => $row->campaign_name,
                'year' => (int) $row->year,
                'unit' => $row->unit,
                'total_quantity' => (float) $row->total_quantity,
            ]);
    }

    /**
     * @return Collection<int, array{input_id: int, input_name: string, type: string, unit: string, total_quantity: float, total_cost: float}>
     */
    private function inputConsumption(?int $cooperativeId, ?int $farmerId, ?int $campaignId, ?int $plotId = null): Collection
    {
        return InputUsage::query()
            ->join('inputs', 'input_usages.input_id', '=', 'inputs.id')
            ->join('plots', 'input_usages.plot_id', '=', 'plots.id')
            ->when($cooperativeId, fn ($q) => $q->where('plots.cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('plots.farmer_id', $farmerId))
            ->when($campaignId, fn ($q) => $q->where('input_usages.campaign_id', $campaignId))
            ->when($plotId, fn ($q) => $q->where('plots.id', $plotId))
            ->select(
                'inputs.id as input_id',
                'inputs.name as input_name',
                'inputs.type',
                'inputs.unit',
                DB::raw('SUM(input_usages.quantity) as total_quantity'),
                DB::raw('COALESCE(SUM(input_usages.cost), 0) as total_cost'),
            )
            ->groupBy('inputs.id', 'inputs.name', 'inputs.type', 'inputs.unit')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($row) => [
                'input_id' => (int) $row->input_id,
                'input_name' => $row->input_name,
                'type' => $row->type,
                'unit' => $row->unit,
                'total_quantity' => (float) $row->total_quantity,
                'total_cost' => (float) $row->total_cost,
            ]);
    }

    /**
     * Répartition géographique par région (Module H — "statistiques géographiques").
     *
     * @return Collection<int, array{region: string, plots_count: int, total_area_ha: float}>
     */
    private function geography(?int $cooperativeId, ?int $farmerId, ?int $plotId = null): Collection
    {
        return Plot::query()
            ->join('cooperatives', 'plots.cooperative_id', '=', 'cooperatives.id')
            ->when($cooperativeId, fn ($q) => $q->where('plots.cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('plots.farmer_id', $farmerId))
            ->when($plotId, fn ($q) => $q->where('plots.id', $plotId))
            ->select('cooperatives.region', DB::raw('COUNT(plots.id) as plots_count'), DB::raw('COALESCE(SUM(plots.area_ha), 0) as total_area_ha'))
            ->groupBy('cooperatives.region')
            ->orderByDesc('plots_count')
            ->get()
            ->map(fn ($row) => [
                'region' => $row->region,
                'plots_count' => (int) $row->plots_count,
                'total_area_ha' => (float) $row->total_area_ha,
            ]);
    }

    /**
     * Fil d'activité (Dashboard, zone "Activité récente") : les évènements les plus récents,
     * toutes sources confondues, triés par date de création réelle — jamais générés, jamais
     * une liste statique. Chaque type d'évènement est scopé par le même périmètre que le
     * reste du tableau de bord.
     *
     * @return Collection<int, array{type: string, description: string, date: string, entity_id: int}>
     */
    private function recentActivity(?int $cooperativeId, ?int $farmerId, int $limit = 8, ?int $plotId = null): Collection
    {
        // Un agriculteur n'est pas rattaché à une parcelle unique (il peut en posséder
        // plusieurs) : quand une parcelle précise est sélectionnée, "agriculteur ajouté" ne
        // devient pertinent que pour LE fermier réel de cette parcelle (relation directe
        // plot.farmer_id), jamais un fermier deviné — absent si la parcelle n'en a pas.
        $plotFarmerId = $plotId !== null ? Plot::find($plotId)?->farmer_id : null;

        $farmers = Farmer::query()
            ->when($cooperativeId, fn ($q) => $q->where('cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('id', $farmerId))
            ->when($plotId, fn ($q) => $q->where('id', $plotFarmerId ?? 0))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($f) => [
                'type' => 'farmer_created',
                'description' => "Agriculteur ajouté : {$f->first_name} {$f->last_name}",
                'date' => $f->created_at->toIso8601String(),
                'entity_id' => $f->id,
            ]);

        $plots = Plot::query()
            ->when($cooperativeId, fn ($q) => $q->where('cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('farmer_id', $farmerId))
            ->when($plotId, fn ($q) => $q->where('id', $plotId))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'type' => 'plot_created',
                'description' => "Parcelle enregistrée : {$p->name}",
                'date' => $p->created_at->toIso8601String(),
                'entity_id' => $p->id,
            ]);

        $harvests = $this->scopedHarvests($cooperativeId, $farmerId, null, $plotId)
            ->select('harvests.id', 'harvests.quantity', 'harvests.unit', 'harvests.created_at', 'plots.name as plot_name')
            ->latest('harvests.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($h) => [
                'type' => 'harvest_recorded',
                'description' => "Récolte enregistrée sur {$h->plot_name} : {$h->quantity} {$h->unit}",
                'date' => $h->created_at->toIso8601String(),
                'entity_id' => $h->id,
            ]);

        $inputUsages = InputUsage::query()
            ->join('inputs', 'input_usages.input_id', '=', 'inputs.id')
            ->join('plots', 'input_usages.plot_id', '=', 'plots.id')
            ->when($cooperativeId, fn ($q) => $q->where('plots.cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('plots.farmer_id', $farmerId))
            ->when($plotId, fn ($q) => $q->where('plots.id', $plotId))
            ->select('input_usages.id', 'input_usages.quantity', 'inputs.name as input_name', 'inputs.unit', 'plots.name as plot_name', 'input_usages.created_at')
            ->latest('input_usages.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($u) => [
                'type' => 'input_usage_recorded',
                'description' => "Intrant utilisé sur {$u->plot_name} : {$u->input_name} ({$u->quantity} {$u->unit})",
                'date' => $u->created_at->toIso8601String(),
                'entity_id' => $u->id,
            ]);

        return $farmers->concat($plots)->concat($harvests)->concat($inputUsages)
            ->sortByDesc('date')
            ->take($limit)
            ->values();
    }

    /**
     * Alertes (Dashboard, zone "Alertes & recommandations"). Un seul indicateur pour l'instant,
     * calculé directement à partir de données réelles sans seuil arbitraire : une parcelle
     * active sans aucun cycle cultural en cours n'a, par définition, aucune culture suivie —
     * signal simple et non ambigu. D'autres alertes (risque météo, rendement faible...)
     * nécessiteraient des seuils métier qui n'ont pas été validés : volontairement absentes
     * plutôt qu'inventées (cahier des charges §24).
     *
     * @return Collection<int, array{type: string, message: string, entity_id: int}>
     */
    private function alerts(?int $cooperativeId, ?int $farmerId, ?int $plotId = null): Collection
    {
        return Plot::query()
            ->where('status', 'active')
            ->when($cooperativeId, fn ($q) => $q->where('cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('farmer_id', $farmerId))
            ->when($plotId, fn ($q) => $q->where('id', $plotId))
            ->whereDoesntHave('cropCycles', fn ($q) => $q->where('status', 'in_progress'))
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'type' => 'plot_without_active_cycle',
                'message' => "La parcelle « {$p->name} » n'a aucun cycle cultural en cours.",
                'entity_id' => $p->id,
            ]);
    }

    /**
     * Base commune des requêtes "récoltes" utilisée par plusieurs agrégats : jointure
     * harvests -> crop_cycles -> plots, avec le même périmètre (coopérative/agriculteur/campagne)
     * appliqué partout pour rester cohérent entre les indicateurs.
     */
    private function scopedHarvests(?int $cooperativeId, ?int $farmerId, ?int $campaignId, ?int $plotId = null): Builder
    {
        return Harvest::query()
            ->join('crop_cycles', 'harvests.crop_cycle_id', '=', 'crop_cycles.id')
            ->join('plots', 'crop_cycles.plot_id', '=', 'plots.id')
            ->when($cooperativeId, fn ($q) => $q->where('plots.cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('plots.farmer_id', $farmerId))
            ->when($campaignId, fn ($q) => $q->where('crop_cycles.campaign_id', $campaignId))
            ->when($plotId, fn ($q) => $q->where('plots.id', $plotId));
    }

    /**
     * Module M — Ventes agricoles. `cooperative_id`/`farmer_id` sont des colonnes directes sur
     * `sales` (voir choix de conception migration) : pas de jointure nécessaire, contrairement à
     * scopedHarvests() ci-dessus.
     */
    private function scopedSales(?int $cooperativeId, ?int $farmerId, ?int $campaignId, ?int $plotId = null): Builder
    {
        return Sale::query()
            ->when($cooperativeId, fn ($q) => $q->where('cooperative_id', $cooperativeId))
            ->when($farmerId, fn ($q) => $q->where('farmer_id', $farmerId))
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->when($plotId, fn ($q) => $q->where('plot_id', $plotId));
    }

    /**
     * Chiffre d'affaires dans le temps (par campagne, triée par année) — série pour le graphique
     * "Évolution des ventes". Non filtrée par campaign_id, comme productionByCampaign() (cela
     * viderait la série à un seul point).
     *
     * @return Collection<int, array{campaign_id: int, campaign_name: string, year: int, total_amount: float}>
     */
    private function salesByCampaign(?int $cooperativeId, ?int $farmerId, ?int $plotId = null): Collection
    {
        return $this->scopedSales($cooperativeId, $farmerId, null, $plotId)
            ->join('agricultural_campaigns', 'sales.campaign_id', '=', 'agricultural_campaigns.id')
            ->select(
                'agricultural_campaigns.id as campaign_id',
                'agricultural_campaigns.name as campaign_name',
                'agricultural_campaigns.year',
                DB::raw('SUM(sales.total_amount) as total_amount'),
            )
            ->groupBy('agricultural_campaigns.id', 'agricultural_campaigns.name', 'agricultural_campaigns.year')
            ->orderBy('agricultural_campaigns.year')
            ->get()
            ->map(fn ($row) => [
                'campaign_id' => (int) $row->campaign_id,
                'campaign_name' => $row->campaign_name,
                'year' => (int) $row->year,
                'total_amount' => (float) $row->total_amount,
            ]);
    }

    /**
     * Dernières ventes enregistrées (Dashboard, table "Dernières ventes") — mêmes principes que
     * recentActivity() : données réelles, jamais générées.
     *
     * @return Collection<int, array{id: int, sale_date: string, crop_name: ?string, buyer_name: ?string, total_amount: float, currency: string}>
     */
    private function recentSales(?int $cooperativeId, ?int $farmerId, int $limit = 5, ?int $plotId = null): Collection
    {
        return $this->scopedSales($cooperativeId, $farmerId, null, $plotId)
            ->leftJoin('crops', 'sales.crop_id', '=', 'crops.id')
            ->select('sales.id', 'sales.sale_date', 'crops.name as crop_name', 'sales.buyer_name', 'sales.total_amount', 'sales.currency')
            ->latest('sales.sale_date')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'sale_date' => $row->sale_date,
                'crop_name' => $row->crop_name,
                'buyer_name' => $row->buyer_name,
                'total_amount' => (float) $row->total_amount,
                'currency' => $row->currency,
            ]);
    }
}

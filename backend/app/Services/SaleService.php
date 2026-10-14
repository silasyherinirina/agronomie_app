<?php

namespace App\Services;

use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Plot;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Module M — Ventes agricoles. Centralise ce que StoreSaleRequest/UpdateSaleRequest ne peuvent
 * pas exprimer par de simples règles de validation :
 *   - résolution de `cooperative_id` selon le rôle (jamais fait confiance au frontend, §11) ;
 *   - cohérence relationnelle entre les FK optionnelles (§26 : une parcelle/un agriculteur/un
 *     cycle cultural rattachés à une AUTRE coopérative doit être détecté et rejeté) ;
 *   - calcul systématique de `total_amount` (§6 : jamais une valeur envoyée par le client) ;
 *   - statistiques agrégées, toujours groupées par unité (§25 : jamais kg + tonne + sac fusionnés).
 */
class SaleService
{
    private const RELATION_KEYS = ['farmer_id', 'plot_id', 'crop_id', 'variety_id', 'crop_cycle_id', 'campaign_id'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $user): Sale
    {
        // Restructuration : le cycle cultural, quand fourni, est la relation RACINE — toute la
        // chaîne (parcelle -> agriculteur/coopérative, culture, variété, campagne) en est dérivée
        // et vérifiée, jamais choisie indépendamment (mission "cycle cultural comme relation
        // racine"). `crop_cycle_id` reste nullable : la vente agrégée (aucun cycle) est un cas
        // métier séparé, inchangé, où `cooperative_id` reste choisi/forcé comme auparavant.
        $cropCycle = $this->resolveCropCycle($attributes);

        $attributes['cooperative_id'] = $this->resolveCooperativeId($attributes, $user, $cropCycle);
        $this->assertRelationsAreCoherent($attributes, $attributes['cooperative_id'], $cropCycle);

        if ($cropCycle) {
            $attributes = $this->applyCropCycleDerivedFields($attributes, $cropCycle);
        }

        $attributes['total_amount'] = $this->computeTotalAmount((float) $attributes['quantity'], (float) $attributes['unit_price']);

        return Sale::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Sale $sale, array $attributes): Sale
    {
        // cooperative_id est immuable après création : une vente ne change jamais de coopérative
        // (UpdateSaleRequest ne l'accepte déjà pas, ceinture et bretelles ici aussi).
        unset($attributes['cooperative_id']);

        $mergedRelations = array_merge(
            $sale->only(self::RELATION_KEYS),
            array_intersect_key($attributes, array_flip(self::RELATION_KEYS)),
        );
        $cropCycle = $this->resolveCropCycle($mergedRelations);
        $this->assertRelationsAreCoherent($mergedRelations, $sale->cooperative_id, $cropCycle);

        // Réapplique les champs dérivés du cycle (existant ou nouvellement fourni dans cette
        // requête) — idempotent si rien n'a changé, et garantit qu'un `crop_cycle_id` mis à jour
        // recalcule bien toute sa chaîne plutôt que de laisser d'anciennes valeurs orphelines.
        if ($cropCycle) {
            $attributes = $this->applyCropCycleDerivedFields($attributes, $cropCycle);
        }

        if (array_key_exists('quantity', $attributes) || array_key_exists('unit_price', $attributes)) {
            $quantity = (float) ($attributes['quantity'] ?? $sale->quantity);
            $unitPrice = (float) ($attributes['unit_price'] ?? $sale->unit_price);
            $attributes['total_amount'] = $this->computeTotalAmount($quantity, $unitPrice);
        }

        $sale->update($attributes);

        return $sale;
    }

    public function computeTotalAmount(float $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }

    /**
     * Charge le cycle cultural (avec sa parcelle) une seule fois si `crop_cycle_id` est fourni —
     * réutilisé par resolveCooperativeId()/assertRelationsAreCoherent()/applyCropCycleDerivedFields()
     * plutôt que refait à chaque étape. `null` pour une vente agrégée (§14 : cas métier séparé,
     * toujours valide — `crop_cycle_id` reste nullable, voir StoreSaleRequest).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCropCycle(array $attributes): ?CropCycle
    {
        if (empty($attributes['crop_cycle_id'])) {
            return null;
        }

        $cycle = CropCycle::with(['plot', 'crop', 'variety', 'campaign'])->find($attributes['crop_cycle_id']);

        if (! $cycle) {
            throw ValidationException::withMessages([
                'crop_cycle_id' => ['Ce cycle cultural est introuvable.'],
            ]);
        }

        return $cycle;
    }

    /**
     * Restructuration "cycle cultural = relation racine" (§8) : quand un cycle est fourni, c'est
     * LUI qui détermine la coopérative — via crop_cycle -> plot -> cooperative_id, jamais un choix
     * indépendant. Une valeur `cooperative_id` explicitement envoyée en plus doit être cohérente
     * avec cette dérivation, sinon rejetée (jamais silencieusement corrigée : §17/§21, "signaler
     * clairement l'incohérence"). cooperative_manager reste borné à SA coopérative même via un
     * cycle (contrôle d'accès, pas seulement une dérivation).
     *
     * Sans cycle (vente agrégée) : comportement inchangé — cooperative_manager toujours SA
     * coopérative (écrasée silencieusement, même logique que PredictionService::resolveCropKey) ;
     * admin doit la préciser explicitement (aucun défaut implicite).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCooperativeId(array $attributes, User $user, ?CropCycle $cropCycle): int
    {
        if ($cropCycle) {
            $derivedCooperativeId = $cropCycle->plot?->cooperative_id;

            if ($derivedCooperativeId === null) {
                throw ValidationException::withMessages([
                    'crop_cycle_id' => ["Ce cycle cultural n'est rattaché à aucune parcelle valide."],
                ]);
            }

            if ($user->role === 'cooperative_manager' && $derivedCooperativeId !== ($user->managedCooperative?->id ?? 0)) {
                throw ValidationException::withMessages([
                    'crop_cycle_id' => ["Ce cycle cultural n'appartient pas à votre coopérative."],
                ]);
            }

            if (! empty($attributes['cooperative_id']) && (int) $attributes['cooperative_id'] !== $derivedCooperativeId) {
                throw ValidationException::withMessages([
                    'cooperative_id' => ['Incohérent avec la coopérative du cycle cultural sélectionné.'],
                ]);
            }

            return $derivedCooperativeId;
        }

        if ($user->role === 'cooperative_manager') {
            $cooperativeId = $user->managedCooperative?->id;
            if ($cooperativeId === null) {
                throw ValidationException::withMessages([
                    'cooperative_id' => ["Votre compte n'est rattaché à aucune coopérative."],
                ]);
            }

            return $cooperativeId;
        }

        if (empty($attributes['cooperative_id'])) {
            throw ValidationException::withMessages([
                'cooperative_id' => ['La coopérative est obligatoire.'],
            ]);
        }

        return (int) $attributes['cooperative_id'];
    }

    /**
     * §26 : une vente ne doit jamais pouvoir être associée arbitrairement à une autre
     * coopérative que celle qui l'enregistre — vérifié sur toute la chaîne Coopérative ->
     * Agriculteur -> Parcelle -> Cycle cultural, pas seulement en surface. Le backend est seul
     * juge : ces vérifications s'appliquent même pour un admin qui pourrait légitimement créer
     * une vente pour n'importe quelle coopérative, mais jamais avec des FK incohérentes entre elles.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function assertRelationsAreCoherent(array $attributes, int $cooperativeId, ?CropCycle $cropCycle): void
    {
        $errors = [];

        if (! empty($attributes['farmer_id'])) {
            $farmer = Farmer::find($attributes['farmer_id']);
            if (! $farmer || $farmer->cooperative_id !== $cooperativeId) {
                $errors['farmer_id'] = ["Cet agriculteur n'appartient pas à cette coopérative."];
            }
        }

        if (! empty($attributes['plot_id'])) {
            $plot = Plot::find($attributes['plot_id']);
            if (! $plot || $plot->cooperative_id !== $cooperativeId) {
                $errors['plot_id'] = ["Cette parcelle n'appartient pas à cette coopérative."];
            } elseif (! empty($attributes['farmer_id']) && $plot->farmer_id !== null && $plot->farmer_id !== (int) $attributes['farmer_id']) {
                $errors['plot_id'] = ["Cette parcelle n'appartient pas à l'agriculteur sélectionné."];
            }
        }

        // Le cycle cultural est la relation RACINE (§10/§17) : toute valeur explicite envoyée
        // pour un champ qu'il détermine doit lui être identique, sinon rejetée — jamais
        // silencieusement remplacée par la valeur du cycle à cette étape (cf. §23 : "ne pas
        // choisir arbitrairement une valeur ; signaler clairement l'incohérence").
        if ($cropCycle) {
            foreach ([
                'plot_id' => $cropCycle->plot_id,
                'crop_id' => $cropCycle->crop_id,
                'variety_id' => $cropCycle->variety_id,
                'campaign_id' => $cropCycle->campaign_id,
                'farmer_id' => $cropCycle->plot?->farmer_id,
            ] as $field => $expected) {
                if (! empty($attributes[$field]) && $expected !== null && (int) $attributes[$field] !== $expected) {
                    $errors[$field] = ['Incohérent avec le cycle cultural sélectionné.'];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Dérive plot_id/crop_id/variety_id/campaign_id/farmer_id directement depuis le cycle
     * cultural — appelée seulement APRÈS assertRelationsAreCoherent() (donc uniquement quand
     * aucune valeur explicite contradictoire n'a été envoyée) : le backend reste la source de
     * vérité même si le frontend a oublié d'envoyer l'un de ces champs (§17/§20).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyCropCycleDerivedFields(array $attributes, CropCycle $cropCycle): array
    {
        $attributes['plot_id'] = $cropCycle->plot_id;
        $attributes['crop_id'] = $cropCycle->crop_id;
        $attributes['variety_id'] = $cropCycle->variety_id;
        $attributes['campaign_id'] = $cropCycle->campaign_id;
        $attributes['farmer_id'] = $cropCycle->plot?->farmer_id;

        return $attributes;
    }

    /**
     * Périmètre de données visible selon le rôle — même logique que DashboardService::resolveScope
     * et les Controllers CRUD existants (jamais élargi via un paramètre de requête pour
     * cooperative_manager/farmer).
     */
    public function scopedQuery(User $user): Builder
    {
        $query = Sale::query();

        return match ($user->role) {
            'cooperative_manager' => $query->where('cooperative_id', $user->managedCooperative?->id ?? 0),
            'farmer' => $query->where('farmer_id', $user->farmer?->id ?? 0),
            default => $query, // admin, analyst
        };
    }

    /**
     * Filtres additionnels (période/coopérative/agriculteur/culture/campagne) partagés par
     * SaleController (index/statistics) ET ReportDataBuilder (Module Rapports, §19/§23 : mêmes
     * filtres appliqués côté serveur partout, jamais une seconde implémentation qui pourrait
     * diverger). `$filters` utilise les mêmes clés que les paramètres de requête HTTP existants.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        // Module Rapports, restructuration "cycle cultural = relation racine" : `sales.crop_cycle_id`
        // est une colonne directe (voir migration create_sales_table) — filtre exact sur CE cycle
        // précisément, jamais reconstruit via ses attributs dérivés (deux cycles pourraient
        // partager crop_id/campaign_id sans être le même cycle). Une vente agrégée
        // (crop_cycle_id IS NULL) est naturellement exclue par cette égalité, sans cas particulier.
        if (! empty($filters['crop_cycle_id'])) {
            $query->where('crop_cycle_id', $filters['crop_cycle_id']);
        }

        if (! empty($filters['cooperative_id'])) {
            $query->where('cooperative_id', $filters['cooperative_id']);
        }

        if (! empty($filters['farmer_id'])) {
            $query->where('farmer_id', $filters['farmer_id']);
        }

        if (! empty($filters['plot_id'])) {
            $query->where('plot_id', $filters['plot_id']);
        }

        if (! empty($filters['crop_id'])) {
            $query->where('crop_id', $filters['crop_id']);
        }

        if (! empty($filters['variety_id'])) {
            $query->where('variety_id', $filters['variety_id']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('sale_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('sale_date', '<=', $filters['end_date']);
        }

        return $query;
    }

    /**
     * Statistiques agrégées (§9 : GET /api/sales/statistics). `total_amount` est une grandeur
     * monétaire homogène (toujours en `currency`, MGA par défaut) : sommable directement — mais
     * les quantités restent groupées par unité (§25), jamais fusionnées entre kg/tonne/sac.
     *
     * @return array<string, mixed>
     */
    public function statistics(Builder $scoped): array
    {
        $base = clone $scoped;

        // count()/sum()/avg() en 3 requêtes séparées auraient été plus lisibles mais inutilement
        // coûteux : cette agrégation est appelée à la fois par GET /api/sales/statistics ET par
        // le tableau de bord (DashboardService::summary()) — un seul aller-retour SQL ici évite
        // d'ajouter 2 requêtes par affichage du dashboard (cf. QueryCountAuditTest).
        $totals = (clone $base)
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total_amount), 0) as total_amount, COALESCE(AVG(unit_price), 0) as average_unit_price')
            ->first();

        return [
            'sales_count' => (int) $totals->sales_count,
            'total_amount' => (float) $totals->total_amount,
            'average_unit_price' => round((float) $totals->average_unit_price, 2),
            'quantity_by_unit' => (clone $base)
                ->select('unit', DB::raw('SUM(quantity) as total_quantity'), DB::raw('COUNT(*) as sales_count'))
                ->groupBy('unit')
                ->orderByDesc('total_quantity')
                ->get()
                ->map(fn ($row) => [
                    'unit' => $row->unit,
                    'total_quantity' => (float) $row->total_quantity,
                    'sales_count' => (int) $row->sales_count,
                ]),
            'amount_by_crop' => (clone $base)
                ->join('crops', 'sales.crop_id', '=', 'crops.id')
                ->select('crops.id as crop_id', 'crops.name as crop_name', DB::raw('SUM(sales.total_amount) as total_amount'))
                ->groupBy('crops.id', 'crops.name')
                ->orderByDesc('total_amount')
                ->get()
                ->map(fn ($row) => [
                    'crop_id' => (int) $row->crop_id,
                    'crop_name' => $row->crop_name,
                    'total_amount' => (float) $row->total_amount,
                ]),
        ];
    }
}

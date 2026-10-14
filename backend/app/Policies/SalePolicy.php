<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

/**
 * Module M — Ventes agricoles (cahier des charges §11) :
 *   - admin            : accès complet (before()) ;
 *   - cooperative_manager : CRUD limité aux ventes de SA coopérative (cooperative_id direct sur
 *     `sales`, pas besoin de remonter par plot/farmer — voir choix de conception migration) ;
 *   - farmer           : consultation de ses propres ventes uniquement (jamais de création —
 *     l'enregistrement d'une vente reste une opération de gestion de la coopérative) ;
 *   - analyst          : consultation/statistiques de toutes les ventes, jamais de modification.
 *
 * Suppression alignée sur update() (pas de restriction supplémentaire à l'admin, contrairement à
 * HarvestPolicy) : une vente reste soft-deleted (voir migration), donc jamais réellement perdue.
 */
class SalePolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sale $sale): bool
    {
        return match ($user->role) {
            'analyst' => true,
            'cooperative_manager' => $user->managedCooperative?->id === $sale->cooperative_id,
            'farmer' => $user->farmer?->id === $sale->farmer_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === 'cooperative_manager';
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->role === 'cooperative_manager'
            && $user->managedCooperative?->id === $sale->cooperative_id;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $this->update($user, $sale);
    }
}

<?php

namespace App\Policies;

use App\Models\Harvest;
use App\Models\User;

class HarvestPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Harvest $harvest): bool
    {
        $plot = $harvest->cropCycle?->plot;

        return match ($user->role) {
            'analyst' => true,
            'cooperative_manager' => $user->managedCooperative?->id === $plot?->cooperative_id,
            'farmer' => $user->farmer?->id === $plot?->farmer_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === 'cooperative_manager';
    }

    public function update(User $user, Harvest $harvest): bool
    {
        return $user->role === 'cooperative_manager'
            && $user->managedCooperative?->id === $harvest->cropCycle?->plot?->cooperative_id;
    }

    /**
     * Suppression restreinte à l'administrateur pour préserver la traçabilité (Phase 0, §N).
     * Le cooperative_manager peut corriger une récolte (update) mais pas la supprimer.
     */
    public function delete(User $user, Harvest $harvest): bool
    {
        return false;
    }
}

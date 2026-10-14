<?php

namespace App\Policies;

use App\Models\InputUsage;
use App\Models\User;

class InputUsagePolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InputUsage $inputUsage): bool
    {
        return match ($user->role) {
            'analyst' => true,
            'cooperative_manager' => $user->managedCooperative?->id === $inputUsage->plot?->cooperative_id,
            'farmer' => $user->farmer?->id === $inputUsage->plot?->farmer_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === 'cooperative_manager';
    }

    public function update(User $user, InputUsage $inputUsage): bool
    {
        return $user->role === 'cooperative_manager'
            && $user->managedCooperative?->id === $inputUsage->plot?->cooperative_id;
    }

    /**
     * Suppression restreinte à l'administrateur pour préserver la traçabilité (Phase 0, §N).
     */
    public function delete(User $user, InputUsage $inputUsage): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\CropCycle;
use App\Models\User;

class CropCyclePolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CropCycle $cropCycle): bool
    {
        return match ($user->role) {
            'analyst' => true,
            'cooperative_manager' => $user->managedCooperative?->id === $cropCycle->plot?->cooperative_id,
            'farmer' => $user->farmer?->id === $cropCycle->plot?->farmer_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === 'cooperative_manager';
    }

    public function update(User $user, CropCycle $cropCycle): bool
    {
        return $user->role === 'cooperative_manager'
            && $user->managedCooperative?->id === $cropCycle->plot?->cooperative_id;
    }

    public function delete(User $user, CropCycle $cropCycle): bool
    {
        return $this->update($user, $cropCycle);
    }
}

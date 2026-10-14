<?php

namespace App\Policies;

use App\Models\Plot;
use App\Models\User;

class PlotPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Plot $plot): bool
    {
        return match ($user->role) {
            'analyst' => true,
            'cooperative_manager' => $user->managedCooperative?->id === $plot->cooperative_id,
            'farmer' => $user->farmer?->id === $plot->farmer_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === 'cooperative_manager';
    }

    public function update(User $user, Plot $plot): bool
    {
        return $user->role === 'cooperative_manager'
            && $user->managedCooperative?->id === $plot->cooperative_id;
    }

    public function delete(User $user, Plot $plot): bool
    {
        return $this->update($user, $plot);
    }
}

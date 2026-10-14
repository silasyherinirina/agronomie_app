<?php

namespace App\Policies;

use App\Models\Farmer;
use App\Models\User;

class FarmerPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Farmer $farmer): bool
    {
        return match ($user->role) {
            'analyst' => true,
            'cooperative_manager' => $user->managedCooperative?->id === $farmer->cooperative_id,
            'farmer' => $user->id === $farmer->user_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === 'cooperative_manager';
    }

    public function update(User $user, Farmer $farmer): bool
    {
        return $user->role === 'cooperative_manager'
            && $user->managedCooperative?->id === $farmer->cooperative_id;
    }

    public function delete(User $user, Farmer $farmer): bool
    {
        return $this->update($user, $farmer);
    }
}

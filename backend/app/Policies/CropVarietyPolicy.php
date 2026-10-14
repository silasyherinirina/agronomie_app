<?php

namespace App\Policies;

use App\Models\CropVariety;
use App\Models\User;

/**
 * Donnée de référence : lecture ouverte, écriture réservée à l'administrateur (voir before()).
 */
class CropVarietyPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CropVariety $cropVariety): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CropVariety $cropVariety): bool
    {
        return false;
    }

    public function delete(User $user, CropVariety $cropVariety): bool
    {
        return false;
    }
}

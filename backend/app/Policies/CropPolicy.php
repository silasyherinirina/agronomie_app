<?php

namespace App\Policies;

use App\Models\Crop;
use App\Models\User;

/**
 * Donnée de référence (catalogue partagé par toutes les coopératives) : lecture ouverte
 * à tout utilisateur authentifié, écriture réservée à l'administrateur (voir before()).
 */
class CropPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Crop $crop): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Crop $crop): bool
    {
        return false;
    }

    public function delete(User $user, Crop $crop): bool
    {
        return false;
    }
}

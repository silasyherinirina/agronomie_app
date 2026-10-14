<?php

namespace App\Policies;

use App\Models\Input;
use App\Models\User;

/**
 * Donnée de référence (catalogue d'intrants partagé) : lecture ouverte, écriture réservée
 * à l'administrateur (voir before()).
 */
class InputPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Input $input): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Input $input): bool
    {
        return false;
    }

    public function delete(User $user, Input $input): bool
    {
        return false;
    }
}

<?php

namespace App\Policies;

use App\Models\Cooperative;
use App\Models\User;

class CooperativePolicy
{
    /**
     * L'administrateur a tous les droits, sur toutes les méthodes ci-dessous.
     */
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cooperative $cooperative): bool
    {
        return true;
    }

    /**
     * Création réservée à l'administrateur (entité structurante de la plateforme) — voir before().
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Cooperative $cooperative): bool
    {
        return $user->role === 'cooperative_manager'
            && $user->managedCooperative?->id === $cooperative->id;
    }

    /**
     * Suppression réservée à l'administrateur — voir before().
     */
    public function delete(User $user, Cooperative $cooperative): bool
    {
        return false;
    }
}

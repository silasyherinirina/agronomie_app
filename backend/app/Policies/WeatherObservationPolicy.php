<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeatherObservation;

/**
 * Module L — Météo (extension, Phase 11). Même schéma d'autorisation que les autres
 * ressources opérationnelles (Plot, CropCycle) : gestion par coopérative, lecture globale
 * pour l'analyste, lecture restreinte pour l'agriculteur.
 */
class WeatherObservationPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WeatherObservation $observation): bool
    {
        return match ($user->role) {
            'analyst' => true,
            'cooperative_manager' => $user->managedCooperative?->id === $observation->plot?->cooperative_id,
            'farmer' => $user->farmer?->id === $observation->plot?->farmer_id,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === 'cooperative_manager';
    }

    public function update(User $user, WeatherObservation $observation): bool
    {
        return $user->role === 'cooperative_manager'
            && $user->managedCooperative?->id === $observation->plot?->cooperative_id;
    }

    public function delete(User $user, WeatherObservation $observation): bool
    {
        return $this->update($user, $observation);
    }
}

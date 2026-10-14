<?php

namespace App\Policies;

use App\Models\ProductionPrediction;
use App\Models\User;

class ProductionPredictionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionPrediction $prediction): bool
    {
        return match ($user->role) {
            'analyst' => true,
            'cooperative_manager' => $user->managedCooperative?->id === $prediction->plot?->cooperative_id,
            'farmer' => $user->farmer?->id === $prediction->plot?->farmer_id,
            default => false,
        };
    }

    /**
     * Rôle autorisé à demander une prédiction ; l'appartenance de la parcelle à la
     * coopérative gérée est vérifiée dans le Controller (même pattern que CropCyclePolicy),
     * car la parcelle n'est pas encore résolue au moment de l'autorisation de la requête.
     */
    public function create(User $user): bool
    {
        return $user->role === 'cooperative_manager';
    }
}

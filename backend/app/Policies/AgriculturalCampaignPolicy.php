<?php

namespace App\Policies;

use App\Models\AgriculturalCampaign;
use App\Models\User;

/**
 * Donnée de référence temporelle (campagne agricole), commune à toutes les coopératives :
 * lecture ouverte, écriture réservée à l'administrateur (voir before()).
 */
class AgriculturalCampaignPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AgriculturalCampaign $agriculturalCampaign): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AgriculturalCampaign $agriculturalCampaign): bool
    {
        return false;
    }

    public function delete(User $user, AgriculturalCampaign $agriculturalCampaign): bool
    {
        return false;
    }
}

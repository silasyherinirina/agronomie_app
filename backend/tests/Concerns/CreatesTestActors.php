<?php

namespace Tests\Concerns;

use App\Models\Cooperative;
use App\Models\Farmer;
use App\Models\User;

/**
 * Fabrique des utilisateurs/rôles réutilisables entre les tests Feature de l'API
 * (Phase 4), pour éviter de dupliquer la même logique de rôle/rattachement partout.
 */
trait CreatesTestActors
{
    protected function makeUser(string $role = 'farmer'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    protected function makeAdmin(): User
    {
        return $this->makeUser('admin');
    }

    protected function makeAnalyst(): User
    {
        return $this->makeUser('analyst');
    }

    /**
     * Responsable de coopérative, avec sa propre coopérative déjà créée et liée.
     */
    protected function makeCooperativeManager(): array
    {
        $manager = $this->makeUser('cooperative_manager');
        $cooperative = Cooperative::factory()->create(['manager_user_id' => $manager->id]);

        return [$manager, $cooperative];
    }

    /**
     * Agriculteur avec compte utilisateur, rattaché à une coopérative donnée (ou nouvelle).
     */
    protected function makeFarmerUser(?Cooperative $cooperative = null): array
    {
        $cooperative ??= Cooperative::factory()->create();
        $user = $this->makeUser('farmer');
        $farmer = Farmer::factory()->create(['cooperative_id' => $cooperative->id, 'user_id' => $user->id]);

        return [$user, $farmer, $cooperative];
    }
}

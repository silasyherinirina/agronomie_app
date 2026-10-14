<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Jeu de données de démonstration Madagascar (mémoire de fin d'étude) — voir
     * docs/DATASET-MADAGASCAR.md pour la méthode complète et docs/SOURCES-AGRICULTURE-MADAGASCAR.md
     * pour les sources consultées. Chaque Seeder est idempotent (rejouable sans doublon) et NE
     * TOUCHE JAMAIS à la table `users` ni aux données préexistantes qu'il ne possède pas
     * (cahier des charges Phase 22/30/31).
     *
     * Ordre imposé par les dépendances du schéma (une coopérative avant un agriculteur, un
     * agriculteur avant une parcelle, etc.) — voir docs/DATASET-MADAGASCAR.md, "Relations entre
     * les tables". Enveloppé dans une transaction (Phase 23) : une erreur en cours de route ne
     * doit jamais laisser la base dans un état partiellement seedé.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        DB::transaction(function () {
            $this->call([
                CooperativeSeeder::class,
                FarmerSeeder::class,
                CropSeeder::class,
                CropVarietySeeder::class,
                AgriculturalCampaignSeeder::class,
                PlotSeeder::class,
                CropCycleSeeder::class,
                HarvestSeeder::class,
                InputSeeder::class,
                InputUsageSeeder::class,
                WeatherObservationSeeder::class,
                ModelVersionSeeder::class,
                ProductionPredictionSeeder::class,
            ]);
        });
    }
}

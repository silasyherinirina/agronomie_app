<?php

namespace Database\Seeders;

use App\Models\Cooperative;
use App\Models\Farmer;
use Database\Seeders\Support\MadagascarReferenceData as Ref;
use Illuminate\Database\Seeder;

/**
 * Agriculteurs de démonstration répartis sur les coopératives créées par CooperativeSeeder.
 *
 * Identités SYNTHÉTIQUES : prénoms/noms malgaches courants combinés librement (comme "Jean
 * Dupont" en français), jamais reliés à un individu réel recensé — cahier des charges Phase 6.
 * Aucun compte utilisateur n'est créé (user_id reste null) : la relation est nullable et ne
 * l'exige pas, conformément à la règle "ne pas créer de faux utilisateurs" (Phase 30).
 *
 * Idempotent : identifié par (cooperative_id, first_name, last_name).
 */
class FarmerSeeder extends Seeder
{
    public function run(): void
    {
        $cooperatives = Cooperative::where('name', 'like', 'Coopérative Démo %')->orderBy('id')->get();
        if ($cooperatives->isEmpty()) {
            return;
        }

        $firstNames = Ref::FARMER_FIRST_NAMES;
        $lastNames = Ref::FARMER_LAST_NAMES;
        $count = max(count($firstNames), count($lastNames), $cooperatives->count() + 1);

        for ($i = 0; $i < $count; $i++) {
            $cooperative = $cooperatives[$i % $cooperatives->count()];
            $firstName = $firstNames[$i % count($firstNames)];
            $lastName = $lastNames[$i % count($lastNames)];

            // Décalage volontaire de l'index du nom de famille pour éviter de rejouer
            // systématiquement les mêmes paires (Rakoto Rakotondrabe, Rasoa Randrianasolo…).
            $lastName = $lastNames[($i + 7) % count($lastNames)];

            Farmer::firstOrCreate(
                [
                    'cooperative_id' => $cooperative->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ],
                [
                    'join_date' => now()->subDays(365 + $i * 47)->format('Y-m-d'),
                    'status' => 'active',
                ]
            );
        }
    }
}

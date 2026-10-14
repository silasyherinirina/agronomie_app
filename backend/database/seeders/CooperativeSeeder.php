<?php

namespace Database\Seeders;

use App\Models\Cooperative;
use Database\Seeders\Support\MadagascarReferenceData as Ref;
use Illuminate\Database\Seeder;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * Coopératives de démonstration — une par région officielle de Madagascar (23 régions,
 * cf. Ref::REGIONS), pour que le Dashboard/la carte reflètent la diversité géographique réelle
 * du pays sans prétendre reproduire un recensement SOC/officiel des coopératives existantes
 * (aucune liste publique fiable de coopératives agricoles malgaches n'a été trouvée lors de la
 * recherche documentaire — voir docs/SOURCES-AGRICULTURE-MADAGASCAR.md, "Limites"). Le nom
 * "Coopérative Démo …" rend cette origine synthétique explicite (Phase 5 du cahier des
 * charges) ; seuls la région, le district et la commune associés sont des données publiques
 * réelles (Ref::REGION_CENTERS).
 *
 * Idempotent : identifiée par son nom (unique par région ici), donc rejouable sans doublon.
 */
class CooperativeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Ref::REGIONS as $region) {
            $center = Ref::REGION_CENTERS[$region];
            $name = "Coopérative Démo {$region} 01";

            Cooperative::updateOrCreate(
                ['name' => $name],
                [
                    'region' => $region,
                    'district' => $center['district'],
                    'commune' => $center['commune'],
                    // Siège synthétique positionné au centre approximatif du district réel —
                    // jamais une adresse cadastrale réelle (cf. commentaire de classe ci-dessus).
                    'location' => new Point($center['lat'], $center['lng']),
                    'status' => 'active',
                ]
            );
        }
    }
}

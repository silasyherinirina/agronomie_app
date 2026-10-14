<?php

namespace Database\Seeders;

use App\Models\AgriculturalCampaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Campagnes agricoles 2017-2026, deux saisons par an — calendrier agricole malgache réel
 * (grande saison pluviale, riziculture ; contre-saison sèche, cultures irriguées de décrue) :
 * source générale FAO (calendrier agricole de Madagascar), voir
 * docs/SOURCES-AGRICULTURE-MADAGASCAR.md.
 *
 * Idempotent via firstOrCreate keyé sur (year, season) — la contrainte unique de la table
 * (voir migration) est partagée avec des campagnes de test préexistantes (ex. "Campagne 2021",
 * season 'saison sèche') : firstOrCreate laisse ces lignes STRICTEMENT intactes plutôt que de
 * les écraser (contrairement à updateOrCreate), pour ne jamais modifier une donnée que ce
 * Seeder ne possède pas.
 */
class AgriculturalCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        for ($year = 2017; $year <= 2026; $year++) {
            // Grande saison (saison des pluies) : semis ~novembre, récolte du riz ~avril
            // l'année suivante.
            $start = Carbon::createFromDate($year, 11, 1);
            $end = Carbon::createFromDate($year + 1, 4, 30);
            AgriculturalCampaign::firstOrCreate(
                ['year' => $year, 'season' => 'saison des pluies'],
                [
                    'name' => "Campagne {$year}-".($year + 1)." (grande saison)",
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => $end->lt($today) ? 'closed' : 'active',
                ]
            );

            // Contre-saison (saison sèche) : cultures irriguées/de décrue, mai à octobre.
            $start = Carbon::createFromDate($year, 5, 1);
            $end = Carbon::createFromDate($year, 10, 31);
            AgriculturalCampaign::firstOrCreate(
                ['year' => $year, 'season' => 'saison sèche'],
                [
                    'name' => "Campagne {$year} (contre-saison)",
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => $end->lt($today) ? 'closed' : 'active',
                ]
            );
        }
    }
}

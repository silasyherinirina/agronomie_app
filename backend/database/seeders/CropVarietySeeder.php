<?php

namespace Database\Seeders;

use App\Models\Crop;
use App\Models\CropVariety;
use Database\Seeders\Support\MadagascarReferenceData as Ref;
use Illuminate\Database\Seeder;

/**
 * Variétés de riz réellement documentées par la recherche agricole malgache (FOFIFA — voir
 * Ref::DOCUMENTED_RICE_VARIETIES et docs/SOURCES-AGRICULTURE-MADAGASCAR.md pour chaque source).
 *
 * Pour les autres cultures, aucun catalogue variétal complet n'a pu être extrait des sources
 * consultées (SOC-semences.mg inaccessible depuis cet environnement, catalogue national FOFIFA
 * trop volumineux pour être analysé automatiquement — voir docs/DATASET-MADAGASCAR.md,
 * "Limites"). Plutôt que d'inventer un nom de variété à consonance officielle, chacune de ces
 * cultures reçoit une entrée explicitement générique "variété locale" (Phase 8 : "si une
 * variété est synthétique, la marquer comme telle").
 *
 * Idempotent : (crop_id, name) est déjà UNIQUE en base — updateOrCreate dessus.
 */
class CropVarietySeeder extends Seeder
{
    public function run(): void
    {
        $riz = Crop::where('name', 'Riz')->first();
        if ($riz) {
            foreach (Ref::DOCUMENTED_RICE_VARIETIES as $variety) {
                CropVariety::updateOrCreate(
                    ['crop_id' => $riz->id, 'name' => $variety['name']],
                );
            }
        }

        $otherCrops = Crop::whereIn('name', array_keys(Ref::GENERIC_CROP_YIELD_REFERENCE_T_HA))->get();
        foreach ($otherCrops as $crop) {
            CropVariety::updateOrCreate(
                ['crop_id' => $crop->id, 'name' => 'Variété locale (non cataloguée nommément)'],
            );
        }
    }
}

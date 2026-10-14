<?php

namespace Database\Seeders;

use App\Models\AgriculturalCampaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Plot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Deux cycles culturaux par parcelle de démonstration (PlotSeeder) :
 *  - un cycle CLÔTURÉ sur une campagne "grande saison" passée (2017-2025), qui recevra sa
 *    récolte dans HarvestSeeder — historique pluriannuel pour les graphiques d'évolution ;
 *  - un cycle EN COURS sur la campagne "contre-saison" 2026 (active à la date du Seeder),
 *    volontairement SANS récolte — pour que le tableau de bord ait de vraies parcelles actives
 *    "en cours de cycle" (utile aussi à l'alerte "parcelle sans cycle en cours").
 *
 * Chronologie garantie (Phase 11) : planting_date est toujours postérieure au début de la
 * campagne et jamais dans le futur pour un cycle clôturé ; expected_harvest_date =
 * planting_date + crop.cycle_duration_days (à défaut 120 j, valeur médiane générique).
 *
 * Idempotent : (plot_id, campaign_id) est déjà indexé ; on vérifie explicitement l'absence
 * d'un cycle pour ce couple avant d'en créer un nouveau.
 */
class CropCycleSeeder extends Seeder
{
    /** Répartition volontairement dominée par le riz (culture la mieux documentée), avec
     * quelques autres cultures réelles pour la diversité des graphiques. */
    private const CROP_ROTATION = ['Riz', 'Riz', 'Riz', 'Maïs', 'Manioc', 'Haricot', 'Pomme de terre', 'Riz'];

    public function run(): void
    {
        $plots = Plot::whereHas('cooperative', fn ($q) => $q->where('name', 'like', 'Coopérative Démo %'))
            ->orderBy('id')
            ->get();

        $pastCampaigns = AgriculturalCampaign::where('season', 'saison des pluies')
            ->whereBetween('year', [2017, 2025])
            ->orderBy('year')
            ->get();
        $currentCampaign = AgriculturalCampaign::where('year', 2026)->where('season', 'saison sèche')->first();

        if ($plots->isEmpty() || $pastCampaigns->isEmpty() || ! $currentCampaign) {
            return;
        }

        $crops = Crop::whereIn('name', self::CROP_ROTATION)->with('varieties')->get()->keyBy('name');

        foreach ($plots as $i => $plot) {
            $cropName = self::CROP_ROTATION[$i % count(self::CROP_ROTATION)];
            $crop = $crops[$cropName] ?? null;
            if (! $crop) {
                continue;
            }
            $variety = $crop->varieties->isNotEmpty() ? $crop->varieties[$i % $crop->varieties->count()] : null;
            $durationDays = $crop->cycle_duration_days ?? 120;

            // --- Cycle historique clôturé (avec récolte à venir dans HarvestSeeder) ---
            $pastCampaign = $pastCampaigns[$i % $pastCampaigns->count()];
            if (! CropCycle::where('plot_id', $plot->id)->where('campaign_id', $pastCampaign->id)->exists()) {
                $planting = Carbon::parse($pastCampaign->start_date)->addDays(10 + ($i % 15));
                CropCycle::create([
                    'plot_id' => $plot->id,
                    'crop_id' => $crop->id,
                    'variety_id' => $variety?->id,
                    'campaign_id' => $pastCampaign->id,
                    'planting_date' => $planting->toDateString(),
                    'expected_harvest_date' => $planting->copy()->addDays($durationDays)->toDateString(),
                    'status' => 'harvested',
                ]);
            }

            // --- Cycle en cours (campagne active, aucune récolte) ---
            if (! CropCycle::where('plot_id', $plot->id)->where('campaign_id', $currentCampaign->id)->exists()) {
                $planting = Carbon::parse($currentCampaign->start_date)->addDays(5 + ($i % 10));
                if ($planting->lte(Carbon::today())) {
                    CropCycle::create([
                        'plot_id' => $plot->id,
                        'crop_id' => $crop->id,
                        'variety_id' => $variety?->id,
                        'campaign_id' => $currentCampaign->id,
                        'planting_date' => $planting->toDateString(),
                        'expected_harvest_date' => $planting->copy()->addDays($durationDays)->toDateString(),
                        'status' => 'in_progress',
                    ]);
                }
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\CropCycle;
use App\Models\Harvest;
use App\Services\HarvestService;
use Database\Seeders\Support\MadagascarReferenceData as Ref;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Une récolte par cycle cultural "harvested" créé par CropCycleSeeder — quantités calibrées sur
 * des ordres de grandeur agronomiques réels (rendement × superficie PostGIS réelle de la
 * parcelle), jamais une valeur arbitraire :
 *  - Riz : Ref::RICE_YIELD_REFERENCE_T_HA (source CGIAR/Cornell, cf. classe de référence) ;
 *  - autres cultures : Ref::GENERIC_CROP_YIELD_REFERENCE_T_HA (ordre de grandeur FAO général,
 *    moins précisément sourcé — voir docs/DATASET-MADAGASCAR.md).
 *
 * `yield_value` n'est jamais écrit à la main : ce Seeder passe par HarvestService::create(),
 * qui le calcule (quantity / plot.area_ha) exactement comme le ferait une création via l'API
 * (Phase 0, §F) quand yield_source = 'calculated'. Une récolte sur cinq est marquée 'declared'
 * (rendement annoncé par l'agriculteur, légèrement différent du calcul) pour illustrer les deux
 * stratégies réellement supportées par l'application.
 *
 * Idempotent : au plus une récolte par cycle cultural — vérifié explicitement avant création.
 */
class HarvestSeeder extends Seeder
{
    /** Cultures dont la récolte est habituellement exprimée en tonnes (produits bruts
     * volumineux) plutôt qu'en kilogrammes, pour donner un exemple réel de non-mélange
     * d'unités dans les agrégations du tableau de bord. */
    private const TONNE_CROPS = ['Manioc', 'Patate douce', 'Pomme de terre', 'Taro'];

    public function __construct(private readonly HarvestService $harvests) {}

    public function run(): void
    {
        $cycles = CropCycle::with(['plot', 'crop'])
            ->where('status', 'harvested')
            ->whereHas('plot.cooperative', fn ($q) => $q->where('name', 'like', 'Coopérative Démo %'))
            ->orderBy('id')
            ->get();

        foreach ($cycles as $i => $cycle) {
            if (Harvest::where('crop_cycle_id', $cycle->id)->exists()) {
                continue;
            }

            $areaHa = $cycle->plot?->area_ha !== null ? (float) $cycle->plot->area_ha : null;
            if (! $areaHa || $areaHa <= 0) {
                continue;
            }

            $cropName = $cycle->crop->name;
            $yieldRefTHa = $cropName === 'Riz'
                ? Ref::RICE_YIELD_REFERENCE_T_HA['irrigue_grande_saison']
                : (Ref::GENERIC_CROP_YIELD_REFERENCE_T_HA[$cropName] ?? 1.0);

            // Variation plausible ±20 % autour de la référence (aucune parcelle réelle ne
            // produit exactement la moyenne théorique).
            $variation = 0.8 + (($i * 37) % 41) / 100; // déterministe, reproductible entre exécutions
            $quantityKg = round($yieldRefTHa * 1000 * $areaHa * $variation, 2);

            $useTonnes = in_array($cropName, self::TONNE_CROPS, true);
            $quantity = $useTonnes ? round($quantityKg / 1000, 3) : $quantityKg;
            $unit = $useTonnes ? 'tonne' : 'kg';

            $harvestDate = Carbon::parse($cycle->expected_harvest_date ?? $cycle->planting_date)->addDays($i % 5);
            $isDeclared = $i % 5 === 0;

            $this->harvests->create([
                'crop_cycle_id' => $cycle->id,
                'harvest_date' => $harvestDate->toDateString(),
                'quantity' => $quantity,
                'unit' => $unit,
                'yield_source' => $isDeclared ? 'declared' : 'calculated',
                // Valeur déclarée légèrement différente du calcul PostGIS, pour rester plausible
                // (un agriculteur estime rarement son rendement au gramme près).
                'yield_value' => $isDeclared ? round(($quantity / $areaHa) * 0.95, 3) : null,
                'quality' => match ($i % 3) {
                    0 => 'Bonne',
                    1 => 'Moyenne',
                    default => null,
                },
            ]);
        }
    }
}

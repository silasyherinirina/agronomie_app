<?php

namespace Database\Seeders;

use App\Models\CropCycle;
use App\Models\InputUsage;
use App\Models\ModelVersion;
use App\Models\ProductionPrediction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Prédictions de démonstration pour les cycles culturaux "Riz" de démonstration (le service IA
 * actuel — ai-service/ — est entraîné spécifiquement sur le rendement du riz, cf.
 * ModelVersionFactory/ModelVersionSeeder : "Rendement du riz (kg/ha)"). Ce Seeder N'APPELLE PAS
 * le service IA live (FastAPI) : comme pour la météo, dépendre d'un service externe pendant un
 * `db:seed` le rendrait fragile hors connexion (Phase 15/16 du cahier des charges) ; les
 * prédictions sont donc construites directement, avec des variables d'entrée DÉRIVÉES des
 * données réelles du cycle (superficie PostGIS, mois de plantation réel, engrais réellement
 * enregistré dans InputUsageSeeder quand disponible) plutôt qu'inventées au hasard.
 *
 * `is_experimental` reste forcé à `true` par ProductionPredictionResource pour toute ligne sans
 * attribut transitoire (voir ModelVersionSeeder) — le frontend continue donc d'afficher
 * l'avertissement "modèle expérimental" pour ces prédictions de démonstration, exactement comme
 * pour une vraie prédiction issue du service IA.
 *
 * Idempotent : au plus une prédiction par (plot_id, campaign_id).
 */
class ProductionPredictionSeeder extends Seeder
{
    /** Variétés considérées "améliorées" (issues de sélection/croisement documenté) plutôt que
     * traditionnelles — cf. Ref::DOCUMENTED_RICE_VARIETIES et CropVarietySeeder. */
    private const IMPROVED_VARIETIES = ['X265', 'Besalohy (FOFIFA X265-MP3)'];

    public function run(): void
    {
        $modelVersions = ModelVersion::where('external_id', 'like', 'rice-yield-%-demo-%')->orderBy('id')->get();
        if ($modelVersions->isEmpty()) {
            return;
        }

        // Prédictions pour les cycles RÉCOLTÉS (comparaison prédiction/réalité, utile pour une
        // soutenance) ET pour les cycles EN COURS (le cas d'usage réel : prévoir un rendement
        // encore inconnu) — les deux sont légitimes, cf. commentaire de classe.
        $cycles = CropCycle::with(['plot', 'crop', 'variety', 'campaign'])
            ->whereIn('status', ['harvested', 'in_progress'])
            ->whereHas('crop', fn ($q) => $q->where('name', 'Riz'))
            ->whereHas('plot.cooperative', fn ($q) => $q->where('name', 'like', 'Coopérative Démo %'))
            ->orderBy('id')
            ->get();

        foreach ($cycles as $i => $cycle) {
            if (ProductionPrediction::where('plot_id', $cycle->plot_id)->where('campaign_id', $cycle->campaign_id)->exists()) {
                continue;
            }

            $areaHa = $cycle->plot?->area_ha !== null ? (float) $cycle->plot->area_ha : null;
            if (! $areaHa) {
                continue;
            }

            $fertilizerPerHa = InputUsage::where('plot_id', $cycle->plot_id)
                ->where('campaign_id', $cycle->campaign_id)
                ->whereHas('input', fn ($q) => $q->where('type', 'engrais'))
                ->sum('quantity');
            $fertilizerPerHa = $fertilizerPerHa > 0 ? round($fertilizerPerHa / $areaHa, 1) : 120.0;

            $varietyClass = in_array($cycle->variety?->name, self::IMPROVED_VARIETIES, true) ? 'amelioree' : 'traditionnelle';
            $plantingMonth = $cycle->planting_date ? Carbon::parse($cycle->planting_date)->month : 12;

            // Pluviométrie de la grande saison : ordre de grandeur plausible pour les
            // hauts-plateaux/est de Madagascar en saison des pluies (donnée dérivée/plausible,
            // pas une mesure Open-Meteo réelle historique — voir docs/DATASET-MADAGASCAR.md).
            $rainfallMm = 1100 + (($i * 53) % 400);

            $features = [
                'area_ha' => $areaHa,
                'fertilizer_kg_per_ha' => $fertilizerPerHa,
                'rainfall_mm' => $rainfallMm,
                'planting_month' => $plantingMonth,
                'variety_class' => $varietyClass,
            ];

            // Rendement prédit dérivé de la référence riz grande saison (1,7 t/ha, cf.
            // Ref::RICE_YIELD_REFERENCE_T_HA) avec un bonus plausible pour variété améliorée —
            // jamais présenté comme la sortie d'un vrai modèle scikit-learn entraîné.
            $baseYieldKgHa = 1700 * ($varietyClass === 'amelioree' ? 1.15 : 1.0);
            $predictedYield = round($baseYieldKgHa * (0.85 + (($i * 31) % 30) / 100), 3);

            $modelVersion = $modelVersions[$i % $modelVersions->count()];

            ProductionPrediction::create([
                'model_version_id' => $modelVersion->id,
                'crop_id' => $cycle->crop_id,
                'plot_id' => $cycle->plot_id,
                'campaign_id' => $cycle->campaign_id,
                'predicted_yield' => $predictedYield,
                'input_features' => $features,
                'predicted_at' => Carbon::parse($cycle->planting_date)->addDays(30),
            ]);
        }
    }
}

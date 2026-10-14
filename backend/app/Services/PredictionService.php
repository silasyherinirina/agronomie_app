<?php

namespace App\Services;

use App\Models\AgriculturalCampaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\InputUsage;
use App\Models\ModelVersion;
use App\Models\Plot;
use App\Models\ProductionPrediction;
use App\Models\WeatherObservation;

/**
 * Orchestration Phase 10 (Intégration IA) : Laravel → FastAPI → ML → persistance.
 *
 * @see AiServiceClient pour l'appel HTTP au service IA
 */
class PredictionService
{
    public function __construct(private readonly AiServiceClient $client) {}

    /**
     * Suggère des valeurs de variables à partir de données réelles (superficie PostGIS,
     * consommation d'engrais enregistrée, date de plantation du cycle cultural, observations
     * météo saisies manuellement depuis la Phase 11). Une variable reste non dérivable et
     * n'est jamais inventée (cahier des charges §24) :
     *   - variety_class : le catalogue CropVariety (texte libre) n'est pas mappé aux deux
     *     classes du modèle ML synthétique (Phase 9).
     *
     * @return array<string, mixed>
     */
    public function suggestInputs(Plot $plot, ?int $campaignId): array
    {
        $areaHa = $plot->area_ha !== null ? (float) $plot->area_ha : null;

        $cropCycle = $plot->cropCycles()
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->latest('planting_date')
            ->first();

        $fertilizerTotal = (float) InputUsage::query()
            ->where('plot_id', $plot->id)
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId))
            ->whereHas('input', fn ($q) => $q->where('type', 'engrais'))
            ->sum('quantity');

        $fertilizerPerHa = ($areaHa && $areaHa > 0 && $fertilizerTotal > 0)
            ? round($fertilizerTotal / $areaHa, 1)
            : null;

        [$rainfallMm, $rainfallSource] = $this->estimateRainfallMm($plot, $cropCycle, $campaignId);

        return [
            'plot_id' => $plot->id,
            'crop_id' => $cropCycle?->crop_id,
            'area_ha' => $areaHa,
            'fertilizer_kg_per_ha' => $fertilizerPerHa,
            'planting_month' => $cropCycle?->planting_date?->month,
            'rainfall_mm' => $rainfallMm,
            'variety_class' => null,
            'sources' => [
                'area_ha' => $areaHa !== null ? 'Parcelle (superficie PostGIS)' : null,
                'fertilizer_kg_per_ha' => $fertilizerPerHa !== null ? 'Utilisations d\'intrants enregistrées (type engrais)' : null,
                'planting_month' => $cropCycle ? 'Cycle cultural (date de plantation)' : null,
                'rainfall_mm' => $rainfallSource,
                'variety_class' => 'Non déductible du catalogue de variétés — saisie manuelle requise',
            ],
        ];
    }

    /**
     * Module L (Phase 11) : agrège les observations météo réellement saisies pour cette
     * parcelle sur la période du cycle cultural (ou de la campagne à défaut), plutôt que
     * d'estimer/inventer une valeur. Renvoie null si aucune observation n'existe encore —
     * fonctionnalité non bloquante, la saisie manuelle reste toujours possible côté frontend.
     *
     * @return array{0: ?float, 1: string}
     */
    private function estimateRainfallMm(Plot $plot, ?CropCycle $cropCycle, ?int $campaignId): array
    {
        $start = null;
        $end = null;

        if ($cropCycle?->planting_date) {
            $start = $cropCycle->planting_date;
            $end = $cropCycle->expected_harvest_date ?? now();
        } elseif ($campaignId && $campaign = AgriculturalCampaign::find($campaignId)) {
            $start = $campaign->start_date;
            $end = $campaign->end_date ?? now();
        }

        if (! $start) {
            return [null, 'Non disponible — aucun cycle cultural ni campagne pour délimiter la période (Module L, extension).'];
        }

        $query = WeatherObservation::query()
            ->where('plot_id', $plot->id)
            ->whereBetween('observed_date', [$start, $end])
            ->whereNotNull('precipitation');

        $count = (clone $query)->count();
        if ($count === 0) {
            return [null, 'Non disponible — aucune observation météo enregistrée pour cette parcelle sur la période (Module L, extension).'];
        }

        $total = round((float) $query->sum('precipitation'), 1);

        return [$total, "Somme de {$count} observation(s) météo réelle(s) du {$start->toDateString()} au {$end->toDateString()}"];
    }

    /**
     * @param  array<string, mixed>  $features
     */
    public function requestPrediction(array $features, ?int $plotId, int $cropId, ?int $campaignId): ProductionPrediction
    {
        $cropKey = $this->resolveCropKey($cropId);
        $result = $this->client->predict($features, $plotId, $cropId, $campaignId, $cropKey);

        $modelVersion = $this->syncModelVersion($result, $cropId);

        $prediction = ProductionPrediction::create([
            'model_version_id' => $modelVersion->id,
            'crop_id' => $cropId,
            'plot_id' => $plotId,
            'campaign_id' => $campaignId,
            'predicted_yield' => $result['predicted_yield'],
            'input_features' => $features,
            'predicted_at' => $result['predicted_at'],
        ]);

        // Attributs transitoires (non persistés) affichés à l'utilisateur mais non stockés en
        // base — voir ProductionPredictionResource.
        $prediction->setAttribute('unit', $result['unit'] ?? 'kg/ha');
        $prediction->setAttribute('feature_importances', $result['feature_importances'] ?? null);
        $prediction->setAttribute('is_experimental', $result['is_experimental'] ?? true);

        return $prediction;
    }

    /**
     * Architecture multi-cultures : traduit l'identifiant Laravel (`crop_id`, opaque pour le
     * service IA) en la clé stable et PERSISTÉE (`crops.code`) qu'il utilise pour choisir son
     * modèle (voir ai-service/app/ml/crop_registry.py). Ne recalcule plus un slug à la volée
     * (cahier des charges §6 : "mapping explicite et persistant", pas une manipulation de
     * chaîne fragile) — `code` est écrit une seule fois à la création de la culture (voir
     * StoreCropRequest) et ne change plus ensuite.
     *
     * Plus de valeur par défaut 'riz' (§7/§40) : `crop_id` est désormais obligatoire et validé
     * (culture existante ET active) par StorePredictionRequest avant d'arriver ici — un appel
     * avec une culture sans `code` configuré est une erreur de configuration explicite, jamais
     * masquée par un repli silencieux.
     */
    private function resolveCropKey(int $cropId): string
    {
        $crop = Crop::find($cropId);

        if (! $crop || ! $crop->code) {
            throw new \RuntimeException(
                "La culture #{$cropId} n'a pas de code stable (`crop_key`) configuré — ".
                'impossible de sélectionner un modèle IA. Configurez `crops.code` pour cette culture.'
            );
        }

        return $crop->code;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function syncModelVersion(array $result, int $cropId): ModelVersion
    {
        $existing = ModelVersion::where('external_id', $result['model_id'])->first();
        if ($existing) {
            return $existing;
        }

        // Premier usage de ce modèle côté Laravel : récupère ses métadonnées complètes
        // (date d'entraînement, métriques de comparaison) auprès du service IA.
        $modelInfo = $this->client->getModel($result['model_id']);

        $modelVersion = ModelVersion::create([
            'crop_id' => $cropId,
            'external_id' => $result['model_id'],
            'name' => $modelInfo['name'] ?? 'Modèle de prévision',
            'algorithm' => $result['algorithm'],
            'version' => $result['model_version'],
            'trained_at' => $modelInfo['trained_at'] ?? null,
            'metrics' => $modelInfo['metrics'] ?? $result['metrics'],
            'is_active' => true,
        ]);

        // Un seul modèle actif PAR CULTURE (miroir du registre du service IA, voir
        // ModelRegistryService.save_model côté ai-service) : entraîner un modèle Maïs ne doit
        // jamais désactiver le modèle Riz déjà actif — c'était le comportement avant cette
        // extension (désactivation globale), corrigé ici en filtrant sur crop_id.
        ModelVersion::where('id', '!=', $modelVersion->id)
            ->where('crop_id', $cropId)
            ->update(['is_active' => false]);

        return $modelVersion;
    }
}

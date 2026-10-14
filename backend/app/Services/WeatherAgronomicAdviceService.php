<?php

namespace App\Services;

use App\Models\Plot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrateur du module « Conseil agronomique prédictif basé sur la météo ». Ne fait aucun
 * calcul lui-même : coordonne les services spécialisés déjà existants, chacun avec une seule
 * responsabilité (architecture Controller → Service → Model, cahier des charges §17/29-30) :
 *
 *   AgroEcologicalZoneService  : région -> zone -> cultures du catalogue existant
 *   WeatherProviderService     : centroïde + date -> fenêtre météo (historique/actuelle/prévision)
 *   WeatherAnalysisService     : fenêtre brute -> agrégats exploitables
 *   CropRecommendationService  : agrégats + cultures -> scores, niveaux, risques
 *
 * Ne modifie JAMAIS Open-Meteo/le cache existants, ne touche pas aux observations manuelles
 * (lues seulement, jamais écrasées) et ne mélange jamais un score de règles avec une
 * prédiction de rendement IA (§19, §30 — "score de compatibilité indicatif", pas une
 * probabilité scientifique).
 */
class WeatherAgronomicAdviceService
{
    public const DISCLAIMER = "Recommandation indicative basée sur la zone agroécologique et les conditions météorologiques disponibles. Elle ne remplace pas l'avis d'un agronome.";

    public function __construct(
        private readonly AgroEcologicalZoneService $zones,
        private readonly WeatherProviderService $weather,
        private readonly WeatherAnalysisService $analysis,
        private readonly CropRecommendationService $recommendations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function adviceFor(Plot $plot, Carbon $date): array
    {
        $plot->loadMissing('cooperative', 'farmer', 'cropCycles.crop', 'cropCycles.variety', 'cropCycles.campaign');

        $region = $plot->cooperative?->region;
        $zone = $this->zones->zoneForRegion($region);

        $base = [
            'plot' => ['id' => $plot->id, 'name' => $plot->name, 'area_ha' => $plot->area_ha !== null ? (float) $plot->area_ha : null],
            'region' => $region,
            'agroecological_zone' => $zone ? ['key' => $zone['key'], 'name' => $zone['name'], 'climate' => $zone['climate']] : null,
            'active_crop_cycle' => $this->activeCropCycleSummary($plot),
            'date' => $date->toDateString(),
            'disclaimer' => self::DISCLAIMER,
        ];

        // Cas §21/§35 : région non reconnue par la classification -> message honnête, aucun
        // conseil trompeur, jamais de zone/culture devinée.
        if (! $region || ! $zone) {
            return $base + $this->emptyAnalysis(
                null,
                "Impossible de déterminer la zone agroécologique de cette parcelle : la région "
                ."de sa coopérative (\"{$region}\") n'est pas reconnue par la classification "
                ."agroécologique actuelle (première version, cf. config/agronomy.php)."
            );
        }

        $centroid = DB::selectOne(
            'SELECT ST_Y(ST_Centroid(geom)) AS lat, ST_X(ST_Centroid(geom)) AS lng FROM plots WHERE id = ?',
            [$plot->id]
        );

        $window = $this->weather->analysisWindow((float) $centroid->lat, (float) $centroid->lng, $date);
        $analyzed = $this->analysis->analyze($window, $date);

        if ($analyzed['unavailable_reason'] !== null) {
            return $base + $this->emptyAnalysis($analyzed['analysis_type'], $analyzed['unavailable_reason'], $analyzed);
        }

        $zoneCrops = $this->zones->cropsForZone($zone);
        $recommendations = $this->recommendations->recommendationsFor($zoneCrops, $analyzed['summary']);
        $risks = $this->recommendations->detectRisks($analyzed['summary']);

        return $base + [
            'analysis_type' => $analyzed['analysis_type'],
            'window' => ['start' => $analyzed['window_start'], 'end' => $analyzed['window_end']],
            'weather' => $analyzed['day'],
            'weather_summary' => $analyzed['summary'],
            'manual_observation_note' => $this->manualObservationNote($plot, $date),
            'recommendations' => $recommendations,
            'risks' => $risks,
            'summary' => $this->buildSummary($plot, $region, $zone, $date, $analyzed['analysis_type'], $recommendations),
            'advice' => $this->buildAdvice($recommendations, $risks),
            'unavailable_reason' => null,
            'source' => $analyzed['source'],
            'fetched_at' => $analyzed['fetched_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalysis(?string $analysisType, string $reason, ?array $analyzed = null): array
    {
        return [
            'analysis_type' => $analysisType,
            'window' => null,
            'weather' => null,
            'weather_summary' => null,
            'manual_observation_note' => null,
            'recommendations' => [],
            'risks' => [],
            'summary' => $reason,
            'advice' => null,
            'unavailable_reason' => $reason,
            'source' => $analyzed['source'] ?? null,
            'fetched_at' => $analyzed['fetched_at'] ?? null,
        ];
    }

    /**
     * §13/§22 : culture détectée automatiquement depuis un cycle cultural actif de la parcelle
     * (le plus récent en cours), jamais devinée si aucun cycle n'existe.
     *
     * @return array<string, mixed>|null
     */
    private function activeCropCycleSummary(Plot $plot): ?array
    {
        $cycle = $plot->cropCycles
            ->where('status', 'in_progress')
            ->sortByDesc('planting_date')
            ->first();

        if (! $cycle) {
            return null;
        }

        return [
            'crop_cycle_id' => $cycle->id,
            'crop_id' => $cycle->crop_id,
            'crop' => $cycle->crop?->name,
            'variety' => $cycle->variety?->name,
            'campaign_id' => $cycle->campaign_id,
            'campaign' => $cycle->campaign?->name,
            'planting_date' => $cycle->planting_date?->toDateString(),
        ];
    }

    /**
     * §11/§16 : signale une observation manuelle proche de la date analysée SANS jamais la
     * fusionner avec les données Open-Meteo — les deux sources restent visuellement distinctes
     * côté frontend.
     */
    private function manualObservationNote(Plot $plot, Carbon $date): ?string
    {
        $closest = $plot->weatherObservations()->get()
            ->sortBy(fn ($o) => abs(Carbon::parse($o->observed_date)->diffInDays($date, false)))
            ->first();

        if (! $closest) {
            return null;
        }

        $days = abs(Carbon::parse($closest->observed_date)->diffInDays($date));
        if ($days > 10) {
            return null;
        }

        return sprintf(
            'Une observation terrain manuelle du %s est disponible pour cette parcelle et peut compléter cette analyse.',
            Carbon::parse($closest->observed_date)->format('d/m/Y')
        );
    }

    /**
     * @param  list<array{crop: string, score: int, level: string}>  $recommendations
     */
    private function buildSummary(Plot $plot, string $region, array $zone, Carbon $date, string $analysisType, array $recommendations): string
    {
        $typeLabel = match ($analysisType) {
            'historical' => 'observées sur la période (analyse historique)',
            'current' => 'actuelles',
            'forecast' => 'prévues',
            default => '',
        };

        $topLevel = $recommendations[0]['level'] ?? null;
        $qualifier = match ($topLevel) {
            'recommended' => 'globalement favorables',
            'moderate' => 'modérément favorables',
            'unfavorable' => 'peu favorables',
            default => 'à interpréter avec prudence (données insuffisantes)',
        };

        return sprintf(
            "Pour la parcelle %s, située dans la région %s (zone agroécologique : %s), les conditions météorologiques %s pour le %s sont %s aux cultures habituellement adaptées à cette zone.",
            $plot->name,
            $region,
            $zone['name'],
            $typeLabel,
            $date->format('d/m/Y'),
            $qualifier
        );
    }

    /**
     * @param  list<array{crop: string, score: int, level: string}>  $recommendations
     * @param  list<array{message: string}>  $risks
     */
    private function buildAdvice(array $recommendations, array $risks): string
    {
        $best = collect($recommendations)->where('level', 'recommended')->pluck('crop');

        $parts = [
            $best->isNotEmpty()
                ? 'Les cultures suivantes présentent actuellement le meilleur potentiel dans ces conditions : '.$best->join(', ').'.'
                : "Aucune culture de la zone ne ressort comme nettement favorable dans ces conditions précises — envisager une culture moyennement adaptée ou surveiller l'évolution météo.",
        ];

        if (! empty($risks)) {
            $parts[] = 'Points de vigilance : '.collect($risks)->pluck('message')->join(' ');
        }

        return implode(' ', $parts);
    }
}

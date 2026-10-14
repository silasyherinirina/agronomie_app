<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Transforme la fenêtre météo brute (WeatherProviderService::analysisWindow()) en une analyse
 * exploitable par le moteur de conseil agronomique : valeurs du jour ciblé + agrégats sur la
 * fenêtre (cahier des charges §8 — "ne pas baser le conseil uniquement sur une valeur isolée").
 *
 * Ne fait AUCUN appel réseau lui-même : pure transformation de données déjà récupérées.
 */
class WeatherAnalysisService
{
    /**
     * @param  array<string, mixed>  $window  Sortie de WeatherProviderService::analysisWindow().
     * @return array{
     *     analysis_type: string,
     *     unavailable_reason: ?string,
     *     day: ?array<string, mixed>,
     *     summary: ?array<string, mixed>,
     *     source: string, fetched_at: string, window_start: ?string, window_end: ?string
     * }
     */
    public function analyze(array $window, Carbon $targetDate): array
    {
        $base = [
            'analysis_type' => $window['analysis_type'],
            'unavailable_reason' => $window['unavailable_reason'],
            'source' => $window['source'],
            'fetched_at' => $window['fetched_at'],
            'window_start' => $window['window_start'],
            'window_end' => $window['window_end'],
        ];

        if ($window['unavailable_reason'] !== null || empty($window['daily'])) {
            return $base + ['day' => null, 'summary' => null];
        }

        $daily = collect($window['daily']);
        $targetDay = $daily->firstWhere('date', $targetDate->toDateString()) ?? $daily->first();

        $day = [
            'date' => $targetDay['date'],
            'temperature_min' => $targetDay['temperature_min'],
            'temperature_max' => $targetDay['temperature_max'],
            'precipitation' => $targetDay['precipitation_sum'],
            'humidity' => $targetDay['humidity_mean'],
            'wind_speed' => $targetDay['wind_speed_max'],
            'observed_at' => null,
        ];

        // Pour une analyse "présent", les lectures instantanées (plus précises à l'instant T)
        // remplacent l'agrégat journalier pour précipitation/humidité/vent — jamais pour
        // historique/prévision, qui n'ont pas de lecture "current" par nature.
        if ($window['analysis_type'] === 'current' && $window['current']) {
            $day['precipitation'] = $window['current']['precipitation'] ?? $day['precipitation'];
            $day['humidity'] = $window['current']['humidity'] ?? $day['humidity'];
            $day['wind_speed'] = $window['current']['wind_speed'] ?? $day['wind_speed'];
            $day['observed_at'] = $window['current']['observed_at'];
        }

        $temps = $daily->pluck('temperature_max')->filter(fn ($v) => $v !== null);
        $tempsMin = $daily->pluck('temperature_min')->filter(fn ($v) => $v !== null);
        $precips = $daily->pluck('precipitation_sum')->filter(fn ($v) => $v !== null);
        $humidities = $daily->pluck('humidity_mean')->filter(fn ($v) => $v !== null);
        $winds = $daily->pluck('wind_speed_max')->filter(fn ($v) => $v !== null);

        $rainfallAvgPerDay = $precips->isNotEmpty() ? round($precips->avg(), 2) : null;
        $rainfallBands = config('agronomy.rainfall_bands_mm_per_day');
        $rainfallCategory = match (true) {
            $rainfallAvgPerDay === null => null,
            $rainfallAvgPerDay < $rainfallBands['low_max'] => 'low',
            $rainfallAvgPerDay > $rainfallBands['high_min'] => 'high',
            default => 'moderate',
        };

        // Tendance simple : compare la 1re moitié et la 2de moitié de la fenêtre (précipitation
        // cumulée) — indicatif, pas une régression statistique.
        $half = intdiv($daily->count(), 2);
        $trend = null;
        if ($daily->count() >= 4 && $precips->isNotEmpty()) {
            $firstHalf = $precips->take($half)->avg();
            $secondHalf = $precips->skip($half)->avg();
            $trend = match (true) {
                $secondHalf > $firstHalf * 1.2 => 'en hausse',
                $secondHalf < $firstHalf * 0.8 => 'en baisse',
                default => 'stable',
            };
        }

        $summary = [
            'temperature_min' => $tempsMin->isNotEmpty() ? (float) $tempsMin->min() : null,
            'temperature_max' => $temps->isNotEmpty() ? (float) $temps->max() : null,
            'humidity_avg' => $humidities->isNotEmpty() ? round($humidities->avg(), 1) : null,
            'precipitation_cumulative' => $precips->isNotEmpty() ? round($precips->sum(), 2) : null,
            'precipitation_avg_per_day' => $rainfallAvgPerDay,
            'rainfall_category' => $rainfallCategory,
            'wind_speed_avg' => $winds->isNotEmpty() ? round($winds->avg(), 1) : null,
            'wind_speed_max' => $winds->isNotEmpty() ? (float) $winds->max() : null,
            'precipitation_trend' => $trend,
            'days_analyzed' => $daily->count(),
        ];

        return $base + ['day' => $day, 'summary' => $summary];
    }
}

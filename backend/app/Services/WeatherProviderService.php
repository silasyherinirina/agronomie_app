<?php

namespace App\Services;

use App\Exceptions\WeatherProviderException;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Module L — Météo (Phase 11/H). Client vers Open-Meteo (https://open-meteo.com), une API
 * publique réelle ne nécessitant aucune clé — vérifié par un appel réel avant intégration
 * (cahier des charges §24 : ne jamais supposer la disponibilité d'un service externe).
 *
 * Fournisseur configurable (config/services.php: services.weather) : changer de fournisseur
 * plus tard ne demande de modifier que cette classe, pas ses appelants.
 */
class WeatherProviderService
{
    /**
     * Météo actuelle + prévisions à court terme pour une coordonnée donnée.
     *
     * @return array{current: array<string, mixed>, daily: array<int, array<string, mixed>>, source: string, fetched_at: string}
     */
    public function forecast(float $latitude, float $longitude): array
    {
        // Clé de cache arrondie à ~1 km : des parcelles proches partagent la même prévision
        // sans multiplier les appels au fournisseur (cahier des charges §19 — "ajouter un cache").
        $cacheKey = sprintf('weather:forecast:%.2f:%.2f', $latitude, $longitude);

        return Cache::remember($cacheKey, (int) config('services.weather.cache_ttl'), function () use ($latitude, $longitude) {
            $response = $this->send($latitude, $longitude);

            return [
                'current' => [
                    'temperature' => $response['current']['temperature_2m'] ?? null,
                    'humidity' => $response['current']['relative_humidity_2m'] ?? null,
                    'precipitation' => $response['current']['precipitation'] ?? null,
                    'wind_speed' => $response['current']['wind_speed_10m'] ?? null,
                    'observed_at' => $response['current']['time'] ?? null,
                ],
                'daily' => $this->normalizeDaily($response['daily'] ?? []),
                'source' => 'open-meteo',
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function send(float $latitude, float $longitude): array
    {
        try {
            $response = Http::timeout((int) config('services.weather.timeout'))
                ->acceptJson()
                ->get(config('services.weather.base_url'), [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m',
                    'daily' => 'precipitation_sum,temperature_2m_max,temperature_2m_min',
                    'timezone' => 'auto',
                    'forecast_days' => 5,
                ]);
        } catch (ConnectionException $e) {
            throw new WeatherProviderException("Fournisseur météo injoignable : {$e->getMessage()}");
        }

        if ($response->failed()) {
            throw new WeatherProviderException("Le fournisseur météo a renvoyé une erreur (HTTP {$response->status()}).");
        }

        return $response->json();
    }

    /**
     * @param  array<string, array<int, mixed>>  $daily
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDaily(array $daily): array
    {
        $dates = $daily['time'] ?? [];

        return collect($dates)
            ->map(fn ($date, $i) => [
                'date' => $date,
                'precipitation_sum' => $daily['precipitation_sum'][$i] ?? null,
                'temperature_max' => $daily['temperature_2m_max'][$i] ?? null,
                'temperature_min' => $daily['temperature_2m_min'][$i] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Module — Conseil agronomique prédictif basé sur la météo (extension). Fenêtre météo
     * multi-jours autour d'une date choisie, en distinguant STRICTEMENT passé/présent/futur
     * (cahier des charges §3-5, §21) : une date passée interroge l'archive historique
     * Open-Meteo (réanalyse), jamais les prévisions ; une date future interroge les prévisions,
     * jamais l'archive ; seule la date du jour combine "current" + fenêtre courte.
     *
     * N'affecte JAMAIS forecast()/send() ci-dessus, qui continuent d'alimenter la page Météo
     * existante à l'identique.
     *
     * @return array{
     *     analysis_type: 'historical'|'current'|'forecast',
     *     window_start: ?string, window_end: ?string,
     *     daily: array<int, array<string, mixed>>,
     *     current: ?array<string, mixed>,
     *     source: string, fetched_at: string, unavailable_reason: ?string
     * }
     */
    public function analysisWindow(float $latitude, float $longitude, CarbonInterface $date): array
    {
        $windowDays = (int) config('agronomy.analysis_window_days', 3);
        $today = Carbon::today();
        $target = Carbon::parse($date)->startOfDay();

        $analysisType = match (true) {
            $target->lt($today) => 'historical',
            $target->eq($today) => 'current',
            default => 'forecast',
        };

        $cacheKey = sprintf(
            'weather:advice-window:%.3f:%.3f:%s:%s',
            $latitude,
            $longitude,
            $target->toDateString(),
            $analysisType
        );

        return Cache::remember(
            $cacheKey,
            (int) config('services.weather.cache_ttl'),
            fn () => match ($analysisType) {
                'historical' => $this->historicalWindow($latitude, $longitude, $target, $windowDays),
                'current' => $this->currentWindow($latitude, $longitude, $windowDays),
                'forecast' => $this->forecastWindow($latitude, $longitude, $target, $windowDays),
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function historicalWindow(float $lat, float $lng, Carbon $target, int $windowDays): array
    {
        $today = Carbon::today();
        $start = $target->copy()->subDays($windowDays);
        $end = $target->copy()->addDays($windowDays);

        // L'archive ne couvre jamais aujourd'hui/demain : ne jamais faire déborder une analyse
        // "historique" sur des jours qui seraient en réalité une prévision (§3 : "ne jamais
        // présenter cette analyse comme une prévision").
        $latestArchiveDay = $today->copy()->subDay();
        if ($end->gt($latestArchiveDay)) {
            $end = $latestArchiveDay;
        }
        if ($start->gt($end)) {
            return $this->unavailableWindow('historical', "Aucune donnée historique n'est disponible pour cette date (délai de traitement de l'archive météo).");
        }

        try {
            $response = $this->request(config('services.weather.archive_url'), [
                'latitude' => $lat,
                'longitude' => $lng,
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,relative_humidity_2m_mean,wind_speed_10m_max',
                'timezone' => 'auto',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);
        } catch (WeatherProviderException $e) {
            return $this->unavailableWindow('historical', $e->getMessage());
        }

        $daily = $this->normalizeDailyExtended($response['daily'] ?? []);
        if (empty($daily)) {
            return $this->unavailableWindow('historical', "Le fournisseur météo n'a retourné aucune donnée historique pour cette période.");
        }

        return [
            'analysis_type' => 'historical',
            'window_start' => $start->toDateString(),
            'window_end' => $end->toDateString(),
            'daily' => $daily,
            'current' => null,
            'source' => 'open-meteo-archive',
            'fetched_at' => now()->toIso8601String(),
            'unavailable_reason' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentWindow(float $lat, float $lng, int $windowDays): array
    {
        try {
            $response = $this->request(config('services.weather.base_url'), [
                'latitude' => $lat,
                'longitude' => $lng,
                'current' => 'temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m',
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,relative_humidity_2m_mean,wind_speed_10m_max',
                'timezone' => 'auto',
                'past_days' => $windowDays,
                'forecast_days' => $windowDays + 1,
            ]);
        } catch (WeatherProviderException $e) {
            return $this->unavailableWindow('current', $e->getMessage());
        }

        $daily = $this->normalizeDailyExtended($response['daily'] ?? []);

        return [
            'analysis_type' => 'current',
            'window_start' => $daily[0]['date'] ?? null,
            'window_end' => $daily[count($daily) - 1]['date'] ?? null,
            'daily' => $daily,
            'current' => [
                'temperature' => $response['current']['temperature_2m'] ?? null,
                'humidity' => $response['current']['relative_humidity_2m'] ?? null,
                'precipitation' => $response['current']['precipitation'] ?? null,
                'wind_speed' => $response['current']['wind_speed_10m'] ?? null,
                'observed_at' => $response['current']['time'] ?? null,
            ],
            'source' => 'open-meteo-forecast',
            'fetched_at' => now()->toIso8601String(),
            'unavailable_reason' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forecastWindow(float $lat, float $lng, Carbon $target, int $windowDays): array
    {
        $today = Carbon::today();
        $start = $target->copy()->subDays($windowDays);
        if ($start->lt($today)) {
            // La portion passée de la fenêtre n'existe pas côté prévision : on ne mélange
            // jamais avec l'archive au sein d'une même analyse "forecast" (§5).
            $start = $today->copy();
        }
        $end = $target->copy()->addDays($windowDays);

        try {
            $response = $this->request(config('services.weather.base_url'), [
                'latitude' => $lat,
                'longitude' => $lng,
                'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,relative_humidity_2m_mean,wind_speed_10m_max',
                'timezone' => 'auto',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]);
        } catch (WeatherProviderException $e) {
            // Open-Meteo renvoie explicitement "... is out of allowed range ..." quand la date
            // dépasse l'horizon de prévision réellement disponible (~16 jours) — jamais
            // d'invention de données au-delà (§21, cas 5).
            if (str_contains($e->getMessage(), 'out of allowed range')) {
                return $this->unavailableWindow('forecast', "Les prévisions météorologiques disponibles ne couvrent pas encore cette date.");
            }

            return $this->unavailableWindow('forecast', $e->getMessage());
        }

        $daily = $this->normalizeDailyExtended($response['daily'] ?? []);
        if (empty($daily)) {
            return $this->unavailableWindow('forecast', "Les prévisions météorologiques disponibles ne couvrent pas encore cette date.");
        }

        return [
            'analysis_type' => 'forecast',
            'window_start' => $start->toDateString(),
            'window_end' => $end->toDateString(),
            'daily' => $daily,
            'current' => null,
            'source' => 'open-meteo-forecast',
            'fetched_at' => now()->toIso8601String(),
            'unavailable_reason' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableWindow(string $analysisType, string $reason): array
    {
        return [
            'analysis_type' => $analysisType,
            'window_start' => null,
            'window_end' => null,
            'daily' => [],
            'current' => null,
            'source' => 'open-meteo',
            'fetched_at' => now()->toIso8601String(),
            'unavailable_reason' => $reason,
        ];
    }

    /**
     * Requête générique vers un endpoint Open-Meteo (prévision ou archive), en préservant le
     * motif d'erreur exact renvoyé par le fournisseur (ex. date hors plage) plutôt que de le
     * réduire à un simple code HTTP — nécessaire pour distinguer une vraie panne d'une date
     * simplement hors couverture (§21).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $baseUrl, array $params): array
    {
        try {
            $response = Http::timeout((int) config('services.weather.advice_timeout'))
                ->acceptJson()
                ->get($baseUrl, $params);
        } catch (ConnectionException $e) {
            throw new WeatherProviderException("Fournisseur météo injoignable : {$e->getMessage()}");
        }

        if ($response->failed()) {
            $reason = $response->json('reason');
            throw new WeatherProviderException($reason ?: "Le fournisseur météo a renvoyé une erreur (HTTP {$response->status()}).");
        }

        return $response->json();
    }

    /**
     * Comme normalizeDaily(), en ajoutant humidité moyenne et vent maximum journaliers —
     * nécessaires au moteur de conseil agronomique (§8) mais pas à la page Météo existante
     * (qui continue d'utiliser normalizeDaily() via forecast(), inchangé).
     *
     * @param  array<string, array<int, mixed>>  $daily
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDailyExtended(array $daily): array
    {
        $dates = $daily['time'] ?? [];

        return collect($dates)
            ->map(fn ($date, $i) => [
                'date' => $date,
                'temperature_max' => $daily['temperature_2m_max'][$i] ?? null,
                'temperature_min' => $daily['temperature_2m_min'][$i] ?? null,
                'precipitation_sum' => $daily['precipitation_sum'][$i] ?? null,
                'humidity_mean' => $daily['relative_humidity_2m_mean'][$i] ?? null,
                'wind_speed_max' => $daily['wind_speed_10m_max'][$i] ?? null,
            ])
            ->values()
            ->all();
    }
}

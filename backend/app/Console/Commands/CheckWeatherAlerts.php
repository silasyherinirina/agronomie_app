<?php

namespace App\Console\Commands;

use App\Exceptions\WeatherProviderException;
use App\Models\Plot;
use App\Notifications\WeatherAlertNotification;
use App\Services\NotificationDispatchService;
use App\Services\WeatherProviderService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

/**
 * Extension Module L — Alerte météo (§5/§22 du centre de notifications) : détecte, à partir de
 * prévisions RÉELLES Open-Meteo (WeatherProviderService, déjà utilisé par
 * WeatherForecastController), un risque de fortes précipitations sur les prochains jours pour
 * les parcelles activement cultivées, et notifie l'agriculteur + le responsable de coopérative
 * concernés. Jamais de notification générée sans donnée météo réelle en entrée.
 *
 * Planifiée quotidiennement (voir routes/console.php) — nécessite le cron standard Laravel en
 * production (`* * * * * php artisan schedule:run`), documenté dans le rapport de livraison.
 */
class CheckWeatherAlerts extends Command
{
    protected $signature = 'weather:check-alerts';

    protected $description = "Notifie les agriculteurs/coopératives des parcelles actives exposées à de fortes précipitations dans les prochains jours (prévision Open-Meteo réelle).";

    private const HEAVY_RAIN_THRESHOLD_MM = 50.0;

    private const FORECAST_HORIZON_DAYS = 2;

    public function __construct(
        private readonly WeatherProviderService $weather,
        private readonly NotificationDispatchService $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Dédup jour/parcelle : la colonne `data` reste `text` (schéma standard Laravel), donc
        // pas d'opérateur JSON Postgres (`->`) exploitable ici — on charge le petit lot du jour
        // et on filtre en PHP plutôt que d'introduire une dépendance à un type de colonne non
        // standard.
        $alreadyAlertedPlotIds = DatabaseNotification::query()
            ->where('type', WeatherAlertNotification::class)
            ->whereDate('created_at', today())
            ->get()
            ->map(fn (DatabaseNotification $n) => $n->data['entity_id'] ?? null)
            ->filter()
            ->all();

        $plots = Plot::query()
            ->where('status', 'active')
            ->whereHas('cropCycles', fn ($q) => $q->where('status', 'in_progress'))
            ->get();

        $alertsSent = 0;

        foreach ($plots as $plot) {
            if (in_array($plot->id, $alreadyAlertedPlotIds, true)) {
                continue;
            }

            $centroid = DB::selectOne(
                'SELECT ST_Y(ST_Centroid(geom)) AS lat, ST_X(ST_Centroid(geom)) AS lng FROM plots WHERE id = ?',
                [$plot->id]
            );

            if (! $centroid) {
                continue;
            }

            try {
                $forecast = $this->weather->forecast((float) $centroid->lat, (float) $centroid->lng);
            } catch (WeatherProviderException) {
                // Fournisseur indisponible pour cette parcelle : pas d'alerte inventée, on
                // passe simplement à la suivante (revérifié à la prochaine exécution planifiée).
                continue;
            }

            $riskyDay = collect($forecast['daily'] ?? [])
                ->take(self::FORECAST_HORIZON_DAYS)
                ->first(fn (array $day) => ($day['precipitation_sum'] ?? 0) >= self::HEAVY_RAIN_THRESHOLD_MM);

            if (! $riskyDay) {
                continue;
            }

            $this->notifications->weatherAlert($plot, [
                'date' => $riskyDay['date'],
                'precipitation_mm' => (float) $riskyDay['precipitation_sum'],
                'source' => $forecast['source'] ?? 'Open-Meteo',
            ]);

            $alertsSent++;
        }

        $this->info("Alertes météo envoyées : {$alertsSent}.");

        return self::SUCCESS;
    }
}

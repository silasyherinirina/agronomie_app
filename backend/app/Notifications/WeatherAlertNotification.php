<?php

namespace App\Notifications;

use App\Models\Plot;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Notifications\Notification;

/**
 * Alerte météo — déclenchée par la commande planifiée `weather:check-alerts`
 * (App\Console\Commands\CheckWeatherAlerts) à partir d'une prévision réelle Open-Meteo
 * (WeatherProviderService), jamais d'une donnée simulée. `$context` porte le jour et le cumul
 * de précipitations qui ont déclenché l'alerte, pour un message honnête et vérifiable.
 *
 * @phpstan-type WeatherAlertContext array{date: string, precipitation_mm: float, source: string}
 */
class WeatherAlertNotification extends Notification
{
    use BuildsNotificationPayload;

    /**
     * @param  array{date: string, precipitation_mm: float, source: string}  $context
     */
    public function __construct(Plot $plot, array $context)
    {
        $region = $plot->cooperative?->region;
        $location = $region ? "{$plot->name} ({$region})" : $plot->name;

        $this->payload(
            title: 'Alerte météo',
            message: "Des conditions météorologiques défavorables (fortes précipitations) sont prévues pour la parcelle « {$location} ».",
            type: 'weather',
            severity: 'warning',
            icon: 'cloud-sun',
            route: '/weather',
            entityType: 'plot',
            entityId: $plot->id,
            metadata: [
                'plot_id' => $plot->id,
                'forecast_date' => $context['date'] ?? null,
                'precipitation_mm' => $context['precipitation_mm'] ?? null,
                'source' => $context['source'] ?? 'Open-Meteo',
            ],
        );
    }
}

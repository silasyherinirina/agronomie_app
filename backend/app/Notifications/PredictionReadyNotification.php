<?php

namespace App\Notifications;

use App\Models\ProductionPrediction;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Notifications\Notification;

/**
 * Prévision IA disponible (Module J/K) — envoyée à l'agriculteur de la parcelle et au
 * responsable de la coopérative concernée (voir NotificationDispatchService::predictionReady()).
 */
class PredictionReadyNotification extends Notification
{
    use BuildsNotificationPayload;

    public function __construct(ProductionPrediction $prediction)
    {
        $plotName = $prediction->plot?->name ?? 'votre parcelle';

        $this->payload(
            title: 'Prévision disponible',
            message: "Une nouvelle prévision de rendement est disponible pour la parcelle « {$plotName} ».",
            type: 'prediction',
            severity: 'info',
            icon: 'brain-circuit',
            route: '/predictions',
            entityType: 'production_prediction',
            entityId: $prediction->id,
            metadata: [
                'plot_id' => $prediction->plot_id,
                'crop_id' => $prediction->crop_id,
                'predicted_yield' => (float) $prediction->predicted_yield,
                // unit/is_experimental : attributs transitoires posés par PredictionService (non
                // stockés en base) — mêmes valeurs par défaut que ProductionPredictionResource.
                'unit' => $prediction->unit ?? 'kg/ha',
                'is_experimental' => (bool) ($prediction->is_experimental ?? true),
            ],
        );
    }
}

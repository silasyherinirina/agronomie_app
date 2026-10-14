<?php

namespace App\Notifications;

use App\Models\Harvest;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Notifications\Notification;

/**
 * Récolte enregistrée (Module F) — envoyée à l'agriculteur propriétaire de la parcelle
 * concernée (voir NotificationDispatchService::harvestRecorded()).
 */
class HarvestRecordedNotification extends Notification
{
    use BuildsNotificationPayload;

    public function __construct(Harvest $harvest)
    {
        $cropCycle = $harvest->cropCycle;
        $cropName = $cropCycle?->crop?->name ?? 'culture';
        $plotName = $cropCycle?->plot?->name ?? 'votre parcelle';

        $this->payload(
            title: 'Récolte enregistrée',
            message: "La récolte de {$cropName} de la parcelle « {$plotName} » a été enregistrée.",
            type: 'harvest',
            severity: 'info',
            icon: 'wheat',
            route: '/harvests',
            entityType: 'harvest',
            entityId: $harvest->id,
            metadata: [
                'crop_cycle_id' => $harvest->crop_cycle_id,
                'quantity' => (float) $harvest->quantity,
                'unit' => $harvest->unit,
                'harvest_date' => $harvest->harvest_date?->toDateString(),
            ],
        );
    }
}

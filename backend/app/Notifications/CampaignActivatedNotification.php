<?php

namespace App\Notifications;

use App\Models\AgriculturalCampaign;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Notifications\Notification;

/**
 * Campagne agricole activée — événement global (une campagne n'est pas rattachée à une
 * coopérative), envoyé à tous les utilisateurs (voir
 * NotificationDispatchService::campaignActivated()). Déclenchée uniquement sur une transition
 * réelle de statut vers 'active' (voir AgriculturalCampaignController::update()), jamais à
 * chaque modification de la campagne.
 */
class CampaignActivatedNotification extends Notification
{
    use BuildsNotificationPayload;

    public function __construct(AgriculturalCampaign $campaign)
    {
        $this->payload(
            title: 'Campagne agricole',
            message: "La campagne agricole « {$campaign->name} » est maintenant active.",
            type: 'campaign',
            severity: 'info',
            icon: 'calendar-range',
            route: '/campaigns',
            entityType: 'agricultural_campaign',
            entityId: $campaign->id,
            metadata: [
                'year' => $campaign->year,
                'season' => $campaign->season,
                'start_date' => $campaign->start_date?->toDateString(),
                'end_date' => $campaign->end_date?->toDateString(),
            ],
        );
    }
}

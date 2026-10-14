<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Notifications\Concerns\BuildsNotificationPayload;
use Illuminate\Notifications\Notification;

/**
 * Vente enregistrée (Module M) — envoyée à l'agriculteur concerné par la vente, quand celle-ci
 * est rattachée à un `farmer_id` avec un compte utilisateur (voir
 * NotificationDispatchService::saleRecorded()). Une vente agrégée sans agriculteur ne notifie
 * personne — pas de destinataire "par défaut" inventé.
 */
class SaleRecordedNotification extends Notification
{
    use BuildsNotificationPayload;

    public function __construct(Sale $sale)
    {
        $cropName = $sale->crop?->name ?? 'production';

        $this->payload(
            title: 'Nouvelle vente enregistrée',
            message: "Une vente de {$sale->quantity} {$sale->unit} de {$cropName} a été enregistrée.",
            type: 'sale',
            severity: 'info',
            icon: 'banknote',
            route: '/sales',
            entityType: 'sale',
            entityId: $sale->id,
            metadata: [
                'quantity' => (float) $sale->quantity,
                'unit' => $sale->unit,
                'total_amount' => (float) $sale->total_amount,
                'currency' => $sale->currency,
                'sale_date' => $sale->sale_date?->toDateString(),
            ],
        );
    }
}

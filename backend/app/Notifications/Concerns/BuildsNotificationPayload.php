<?php

namespace App\Notifications\Concerns;

/**
 * Mutualise la structure du payload JSON stocké en base pour toutes les notifications de
 * l'application (§4/§5 du centre de notifications) : chaque classe concrète appelle
 * `payload()` dans son constructeur avec les données réelles de l'entité concernée, jamais de
 * valeur inventée. Évite de dupliquer `via()`/`toArray()`/`toDatabase()` dans chacune des
 * classes `App\Notifications\*`.
 */
trait BuildsNotificationPayload
{
    private string $payloadTitle;
    private string $payloadMessage;
    private string $payloadType;
    private string $payloadSeverity;
    private string $payloadIcon;
    private ?string $payloadRoute;
    private ?string $payloadEntityType;
    private int|string|null $payloadEntityId;
    /** @var array<string, mixed> */
    private array $payloadMetadata;

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function payload(
        string $title,
        string $message,
        string $type,
        string $severity,
        string $icon,
        ?string $route,
        ?string $entityType,
        int|string|null $entityId,
        array $metadata = [],
    ): void {
        $this->payloadTitle = $title;
        $this->payloadMessage = $message;
        $this->payloadType = $type;
        $this->payloadSeverity = $severity;
        $this->payloadIcon = $icon;
        $this->payloadRoute = $route;
        $this->payloadEntityType = $entityType;
        $this->payloadEntityId = $entityId;
        $this->payloadMetadata = $metadata;
    }

    /**
     * Canal unique 'database' (§21 : pas de websockets/queue en place, le frontend fait du
     * polling léger sur /notifications/unread-count) — voir NotificationDispatchService.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->payloadTitle,
            'message' => $this->payloadMessage,
            'type' => $this->payloadType,
            'severity' => $this->payloadSeverity,
            'icon' => $this->payloadIcon,
            'route' => $this->payloadRoute,
            'entity_type' => $this->payloadEntityType,
            'entity_id' => $this->payloadEntityId,
            'metadata' => $this->payloadMetadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Miroir du payload construit par App\Notifications\Concerns\BuildsNotificationPayload.
 * `$this->data` est castée en tableau par Illuminate\Notifications\DatabaseNotification —
 * toujours défensif (`?? null`/`?? []`) car ce champ reste un JSON libre en base.
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'severity' => $data['severity'] ?? 'info',
            'icon' => $data['icon'] ?? null,
            'route' => $data['route'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

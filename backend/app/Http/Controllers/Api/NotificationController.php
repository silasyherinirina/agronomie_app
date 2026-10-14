<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Centre de notifications. Toutes les requêtes sont bornées à
 * `$request->user()->notifications()` (relation `Illuminate\Notifications\Notifiable`, déjà sur
 * `User`) : structurellement impossible de lire/modifier la notification d'un autre
 * utilisateur, sans avoir besoin d'une Policy dédiée — la relation fait déjà tout le travail.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/notifications — paginé, plus récentes d'abord (ordre déjà appliqué par la
     * relation `notifications()` du trait Notifiable). `status=unread|read` filtre
     * optionnellement (utilisé par la page "Toutes les notifications").
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->notifications();

        if ($request->query('status') === 'unread') {
            $query->whereNull('read_at');
        } elseif ($request->query('status') === 'read') {
            $query->whereNotNull('read_at');
        }

        return NotificationResource::collection(
            $query->paginate($this->perPage($request, default: 10))
        );
    }

    /**
     * GET /api/notifications/unread-count — uniquement un COUNT SQL (jamais la liste complète
     * juste pour compter), consommé par le badge de la cloche.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->user()->unreadNotifications()->count()]);
    }

    public function show(Request $request, string $notification): NotificationResource
    {
        return new NotificationResource(
            $request->user()->notifications()->findOrFail($notification)
        );
    }

    /**
     * PATCH /api/notifications/{id}/read — idempotent : ne réécrit pas `read_at` si déjà lue.
     */
    public function markAsRead(Request $request, string $notification): NotificationResource
    {
        $record = $request->user()->notifications()->findOrFail($notification);

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return new NotificationResource($record);
    }

    public function markAsUnread(Request $request, string $notification): NotificationResource
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->forceFill(['read_at' => null])->save();

        return new NotificationResource($record);
    }

    /**
     * PATCH /api/notifications/read-all — une seule requête UPDATE (pas de boucle de sauvegarde
     * modèle par modèle) pour rester efficace même avec un historique volumineux.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['count' => 0, 'updated' => $updated]);
    }
}

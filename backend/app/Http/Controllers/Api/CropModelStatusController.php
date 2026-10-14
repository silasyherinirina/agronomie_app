<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiServiceException;
use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Services\AiServiceClient;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/crops/model-status — statut du modèle IA pour chaque culture active (cahier des
 * charges §13/§16). Le frontend n'a plus jamais à deviner ou à coder en dur si une culture
 * dispose d'un modèle entraîné : "culture supportée dans la base" (présente ici) ≠ "modèle IA
 * disponible" (`available: true`).
 */
class CropModelStatusController extends Controller
{
    public function __construct(private readonly AiServiceClient $client) {}

    public function index(): JsonResponse
    {
        $crops = Crop::query()->where('is_active', true)->orderBy('name')->get();

        try {
            $statusByCropKey = collect($this->client->listModelStatus())->keyBy('crop_key');
        } catch (AiServiceException) {
            // Service IA injoignable : la liste des cultures reste utile (sélection dans le
            // formulaire), mais aucun statut de modèle n'est inventé — jamais "available: true"
            // par défaut faute de réponse (cahier des charges §19/§24).
            $statusByCropKey = collect();
        }

        $data = $crops->map(function (Crop $crop) use ($statusByCropKey) {
            $status = $crop->code ? $statusByCropKey->get($crop->code) : null;

            return [
                'id' => $crop->id,
                'name' => $crop->name,
                'category' => $crop->category,
                'cycle_duration_days' => $crop->cycle_duration_days,
                'crop_key' => $crop->code,
                'available' => (bool) ($status['available'] ?? false),
                // ready | inactive | training_required | unavailable (service IA injoignable ou
                // `code` non configuré pour cette culture).
                'status' => $status['status'] ?? 'unavailable',
                'algorithm' => $status['algorithm'] ?? null,
                'version' => $status['version'] ?? null,
                'is_experimental' => $status['is_experimental'] ?? null,
            ];
        });

        return response()->json(['data' => $data->values()]);
    }
}

<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP vers le service IA indépendant (FastAPI + scikit-learn, ai-service/).
 * Laravel ne réalise jamais de calcul ML lui-même : il délègue à ce service et relaie sa
 * réponse (architecture Phase 0 — Laravel → FastAPI → ML).
 */
class AiServiceClient
{
    /**
     * @param  array<string, mixed>  $features
     * @return array<string, mixed>
     */
    public function predict(array $features, ?int $plotId, ?int $cropId, ?int $campaignId, string $cropKey): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('/predict', [
            'plot_id' => $plotId,
            'crop_id' => $cropId,
            // Architecture multi-cultures : c'est CE champ (pas crop_id, un identifiant Laravel
            // opaque pour le service IA) qui détermine réellement quel modèle est chargé côté
            // FastAPI — voir ai-service/app/schemas/prediction.py. Obligatoire : plus de défaut
            // implicite 'riz' (cahier des charges §7/§40) — PredictionService::resolveCropKey()
            // garantit qu'une valeur réelle est toujours fournie avant cet appel.
            'crop_key' => $cropKey,
            'campaign_id' => $campaignId,
            'features' => $features,
        ]));

        if (in_array($response->status(), [422, 503], true)) {
            throw $this->toException($response);
        }

        $response->throw();

        return $response->json();
    }

    /**
     * Traduit l'enveloppe structurée du service IA ({"success": false, "error": {"code",
     * "message", "crop_key"}} — voir ai-service/app/errors.py) en AiServiceException, pour que
     * le contrôleur puisse la relayer au frontend sans repli implicite sur un message générique.
     * `error.code` absent (ex. 422 de validation Pydantic « champ manquant », qui répond avec
     * `detail` au format standard FastAPI, pas notre enveloppe) : on relaie quand même un message
     * exploitable plutôt que de faire échouer la relation de l'erreur elle-même.
     */
    private function toException(Response $response): AiServiceException
    {
        $code = $response->json('error.code');
        $message = $response->json('error.message');
        $cropKey = $response->json('error.crop_key');

        if ($code === null) {
            $message ??= is_string($response->json('detail'))
                ? $response->json('detail')
                : json_encode($response->json('detail'));
        }

        return new AiServiceException(
            $message ?? "Le service IA a refusé la requête (HTTP {$response->status()}).",
            $response->status(),
            $code,
            $cropKey,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getModel(string $modelId): ?array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get("/models/{$modelId}"));

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    /**
     * Statut du modèle pour CHAQUE culture connue du service IA — voir GET /models/status côté
     * ai-service (app/api/models.py). Alimente GET /api/crops/model-status (Module IA multi-
     * cultures, §13/§16) : le frontend n'a plus jamais à deviner si une culture dispose d'un
     * modèle entraîné.
     *
     * @return list<array<string, mixed>>
     */
    public function listModelStatus(): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get('/models/status'));

        if ($response->failed()) {
            throw new AiServiceException(
                "Le service IA n'a pas pu renvoyer le statut des modèles (HTTP {$response->status()}).",
                503,
            );
        }

        return $response->json('data') ?? [];
    }

    private function send(callable $callback): Response
    {
        $http = Http::baseUrl(config('services.ai.base_url'))
            ->timeout((int) config('services.ai.timeout'))
            ->acceptJson();

        try {
            return $callback($http);
        } catch (ConnectionException $e) {
            throw new AiServiceException("Service IA injoignable : {$e->getMessage()}", 503);
        }
    }
}

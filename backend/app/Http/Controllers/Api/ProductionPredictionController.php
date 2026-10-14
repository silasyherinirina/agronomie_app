<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AiServiceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prediction\StorePredictionRequest;
use App\Http\Resources\ProductionPredictionResource;
use App\Models\Plot;
use App\Models\ProductionPrediction;
use App\Services\NotificationDispatchService;
use App\Services\PredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Module J/K — Prévision de production (Phase 10, intégration Laravel → FastAPI → ML).
 */
class ProductionPredictionController extends Controller
{
    public function __construct(
        private readonly PredictionService $predictions,
        private readonly NotificationDispatchService $notifications,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
{
    $this->authorize('viewAny', ProductionPrediction::class);

    $query = ProductionPrediction::query()
        ->with(['plot', 'crop', 'campaign', 'modelVersion']);

    $user = $request->user();

    $query = match ($user->role) {
        'cooperative_manager' => $query->whereHas(
            'plot',
            fn ($q) => $q->where(
                'cooperative_id',
                $user->managedCooperative?->id ?? 0
            )
        ),

        'farmer' => $query->whereHas(
            'plot',
            fn ($q) => $q->where(
                'farmer_id',
                $user->farmer?->id ?? 0
            )
        ),

        default => $query,
    };

    if ($cooperativeId = $request->query('cooperative_id')) {
        $query->whereHas('plot', function ($plotQuery) use ($cooperativeId) {
            $plotQuery->where('cooperative_id', $cooperativeId);
        });
    }

    if ($plotId = $request->query('plot_id')) {
        $query->where('plot_id', $plotId);
    }

    if ($campaignId = $request->query('campaign_id')) {
        $query->where('campaign_id', $campaignId);
    }

    return ProductionPredictionResource::collection(
        $query
            ->latest('predicted_at')
            ->paginate($this->perPage($request))
    );
}

    public function store(StorePredictionRequest $request): ProductionPredictionResource|JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $plot = Plot::findOrFail($data['plot_id']);

        if ($user->role === 'cooperative_manager' && $plot->cooperative_id !== $user->managedCooperative?->id) {
            throw ValidationException::withMessages([
                'plot_id' => ["Cette parcelle n'appartient pas à votre coopérative."],
            ]);
        }

        try {
            $prediction = $this->predictions->requestPrediction(
                $data['features'],
                $data['plot_id'],
                $data['crop_id'],
                $data['campaign_id'] ?? null,
            );
        } catch (AiServiceException $e) {
            // Relaie l'enveloppe structurée du service IA (cahier des charges §9) plutôt qu'un
            // message Laravel générique : le frontend peut distinguer TRAINING_REQUIRED de
            // MODEL_INACTIVE ou UNKNOWN_CROP sans avoir à analyser une chaîne en texte libre.
            return response()->json([
                'success' => false,
                'error' => array_filter([
                    'code' => $e->code(),
                    'message' => $e->getMessage(),
                    'crop_key' => $e->cropKey(),
                ], static fn ($value) => $value !== null),
            ], $e->statusCode());
        }

        $this->notifications->predictionReady($prediction);

        return new ProductionPredictionResource($prediction->load(['plot', 'crop', 'campaign', 'modelVersion']));
    }

    public function show(ProductionPrediction $prediction): ProductionPredictionResource
    {
        $this->authorize('view', $prediction);

        return new ProductionPredictionResource(
            $prediction->load(['plot', 'crop', 'campaign', 'modelVersion'])
        );
    }

    /**
     * Suggère des valeurs de variables à partir de données réelles de la parcelle (superficie,
     * intrants enregistrés, cycle cultural). Certaines variables restent nulles quand elles ne
     * sont pas dérivables (voir PredictionService::suggestInputs).
     */
    public function suggestedInputs(Request $request, Plot $plot): JsonResponse
    {
        $this->authorize('view', $plot);

        $campaignId = $request->filled('campaign_id') ? (int) $request->query('campaign_id') : null;

        return response()->json(['data' => $this->predictions->suggestInputs($plot, $campaignId)]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesReferentialIntegrity;
use App\Http\Controllers\Controller;
use App\Http\Requests\CropCycle\StoreCropCycleRequest;
use App\Http\Requests\CropCycle\UpdateCropCycleRequest;
use App\Http\Resources\CropCycleResource;
use App\Models\CropCycle;
use App\Models\CropVariety;
use App\Models\Plot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CropCycleController extends Controller
{
    use HandlesReferentialIntegrity;

    public function index(Request $request): AnonymousResourceCollection
{
    $this->authorize('viewAny', CropCycle::class);

    // Module M — Ventes agricoles : le cycle cultural est le point d'entrée du formulaire
    // « Nouvelle vente » — toutes ses relations (parcelle -> agriculteur/coopérative, culture,
    // variété, campagne) doivent être résolues en un seul appel, jamais une requête
    // supplémentaire par relation manquante (§16 : "ne pas effectuer plusieurs dizaines de
    // requêtes API pour remplir un seul formulaire"). `variety` était auparavant absent ici
    // (seulement chargé par show()) : toute vente créée depuis ce listing perdait silencieusement
    // la variété du cycle.
    $query = CropCycle::query()
        ->with(['plot.farmer', 'plot.cooperative'])
        ->with('crop')
        ->with('variety')
        ->with('campaign')
        ->withCount('harvests');

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

    // Module M — Ventes agricoles : nécessaire pour que le formulaire « Nouvelle vente » puisse
    // filtrer les cycles culturaux par coopérative sélectionnée (un cycle n'a pas de
    // cooperative_id direct, seulement via sa parcelle — voir Plot::cooperative_id). Sans effet
    // pour cooperative_manager/farmer, déjà scopés ci-dessus.
    if ($cooperativeId = $request->query('cooperative_id')) {
        $query->whereHas('plot', fn ($q) => $q->where('cooperative_id', $cooperativeId));
    }

    if ($plotId = $request->query('plot_id')) {
        $query->where('plot_id', $plotId);
    }

    if ($campaignId = $request->query('campaign_id')) {
        $query->where('campaign_id', $campaignId);
    }

    if ($cropId = $request->query('crop_id')) {
        $query->where('crop_id', $cropId);
    }

    if ($status = $request->query('status')) {
        $query->where('status', $status);
    }

    return CropCycleResource::collection(
    $query
        ->orderByDesc('updated_at')
        ->orderByDesc('id')
        ->paginate($this->perPage($request))
);
}

    public function store(StoreCropCycleRequest $request): CropCycleResource
    {
        $user = $request->user();
        $data = $request->validated();

        $plot = Plot::findOrFail($data['plot_id']);

        if ($user->role === 'cooperative_manager' && $plot->cooperative_id !== $user->managedCooperative?->id) {
            throw ValidationException::withMessages([
                'plot_id' => ["Cette parcelle n'appartient pas à votre coopérative."],
            ]);
        }

        $this->assertVarietyBelongsToCrop($data['variety_id'] ?? null, $data['crop_id']);

        $cropCycle = CropCycle::create($data);

        return new CropCycleResource($cropCycle);
    }

    public function show(CropCycle $cropCycle): CropCycleResource
    {
        $this->authorize('view', $cropCycle);

        return new CropCycleResource(
            $cropCycle->load(['plot.farmer', 'plot.cooperative', 'crop', 'variety', 'campaign'])->loadCount('harvests')
        );
    }

    public function update(UpdateCropCycleRequest $request, CropCycle $cropCycle): CropCycleResource
    {
        $data = $request->validated();

        $this->assertVarietyBelongsToCrop($data['variety_id'] ?? null, $cropCycle->crop_id);

        $cropCycle->update($data);

        return new CropCycleResource($cropCycle);
    }

    public function destroy(CropCycle $cropCycle): JsonResponse
    {
        $this->authorize('delete', $cropCycle);

        return $this->deleteOrConflict(
            fn () => $cropCycle->delete(),
            'Impossible de supprimer ce cycle cultural : des récoltes y sont encore rattachées.'
        );
    }

    private function assertVarietyBelongsToCrop(?int $varietyId, int $cropId): void
    {
        if ($varietyId === null) {
            return;
        }

        $variety = CropVariety::find($varietyId);

        if (! $variety || $variety->crop_id !== $cropId) {
            throw ValidationException::withMessages([
                'variety_id' => ["Cette variété n'appartient pas à la culture sélectionnée."],
            ]);
        }
    }
}

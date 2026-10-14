<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Plot\StorePlotRequest;
use App\Http\Requests\Plot\UpdatePlotRequest;
use App\Http\Resources\AgriculturalCampaignResource;
use App\Http\Resources\PlotResource;
use App\Models\AgriculturalCampaign;
use App\Models\Farmer;
use App\Models\Plot;
use App\Services\PlotService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PlotController extends Controller
{
    public function __construct(private readonly PlotService $plotService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Plot::class);

        $query = Plot::query()
            ->with('cooperative')
            ->with('farmer')
            ->withCount('cropCycles');

        $user = $request->user();

        $query = match ($user->role) {
            'cooperative_manager' => $query->where(
                'cooperative_id',
                $user->managedCooperative?->id ?? 0
            ),
            'farmer' => $query->where(
                'farmer_id',
                $user->farmer?->id ?? 0
            ),
            default => $query, // admin, analyst
        };

        if ($cooperativeId = $request->query('cooperative_id')) {
            $query->where('cooperative_id', $cooperativeId);
        }

        if ($plotId = $request->query('plot_id')) {
            $query->where('id', $plotId);
        }

        if ($campaignId = $request->query('campaign_id')) {
            $query->whereHas('cropCycles', function ($cycleQuery) use ($campaignId) {
                $cycleQuery->where('campaign_id', $campaignId);
            });
        }

        if ($farmerId = $request->query('farmer_id')) {
            $query->where('farmer_id', $farmerId);
        }

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$search}%")
            );
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return PlotResource::collection(
    $query
        ->orderByDesc('updated_at')
        ->orderByDesc('id')
        ->paginate($this->perPage($request))
);
    }

    public function store(StorePlotRequest $request): PlotResource
    {
        $user = $request->user();
        $data = $request->validated();

        $data['cooperative_id'] = $user->role === 'admin'
            ? $data['cooperative_id']
            : $user->managedCooperative?->id;

        $this->assertFarmerBelongsToCooperative($data['farmer_id'] ?? null, $data['cooperative_id']);

        $plot = $this->plotService->create($data);

        return new PlotResource($plot);
    }

    public function show(Plot $plot): PlotResource
    {
        $this->authorize('view', $plot);

        return new PlotResource($plot->load(['cooperative', 'farmer'])->loadCount('cropCycles'));
    }

    public function update(UpdatePlotRequest $request, Plot $plot): PlotResource
    {
        $data = $request->validated();

        $this->assertFarmerBelongsToCooperative($data['farmer_id'] ?? null, $plot->cooperative_id);

        $plot = $this->plotService->update($plot, $data);

        return new PlotResource($plot);
    }

    public function destroy(Plot $plot): Response
    {
        $this->authorize('delete', $plot);

        $plot->delete();

        return response()->noContent();
    }

    /**
     * GET /plots/{plot}/campaigns — campagnes agricoles RÉELLEMENT associées à cette parcelle,
     * via ses cycles culturaux (Plot -> CropCycle -> AgriculturalCampaign), jamais toutes les
     * campagnes globales. Utilisé par le filtre "Campagne" du Dashboard une fois une parcelle
     * précise sélectionnée (cahier des charges — filtre Parcelle → Coopérative/Campagne).
     *
     * whereHas() interroge directement `agricultural_campaigns` (pas une jointure sur
     * crop_cycles) : chaque campagne n'apparaît donc qu'une seule fois, même si plusieurs
     * cycles culturaux de la parcelle y sont rattachés — pas de déduplication manuelle requise.
     */
    public function campaigns(Plot $plot): AnonymousResourceCollection
    {
        $this->authorize('view', $plot);

        $campaigns = AgriculturalCampaign::query()
            ->whereHas('cropCycles', fn ($q) => $q->where('plot_id', $plot->id))
            ->orderByDesc('year')
            ->get();

        return AgriculturalCampaignResource::collection($campaigns);
    }

    private function assertFarmerBelongsToCooperative(?int $farmerId, ?int $cooperativeId): void
    {
        if ($farmerId === null) {
            return;
        }

        $farmer = Farmer::find($farmerId);

        if (! $farmer || $farmer->cooperative_id !== $cooperativeId) {
            throw ValidationException::withMessages([
                'farmer_id' => ["L'agriculteur sélectionné n'appartient pas à cette coopérative."],
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Harvest\StoreHarvestRequest;
use App\Http\Requests\Harvest\UpdateHarvestRequest;
use App\Http\Resources\HarvestResource;
use App\Models\CropCycle;
use App\Models\Harvest;
use App\Services\HarvestService;
use App\Services\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class HarvestController extends Controller
{
    public function __construct(
        private readonly HarvestService $harvestService,
        private readonly NotificationDispatchService $notifications,
    ) {}

        public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Harvest::class);

        $query = Harvest::query();
        $user = $request->user();

        $query = match ($user->role) {
            'cooperative_manager' => $query->whereHas(
                'cropCycle.plot',
                fn ($q) => $q->where(
                    'cooperative_id',
                    $user->managedCooperative?->id ?? 0
                )
            ),
            'farmer' => $query->whereHas(
                'cropCycle.plot',
                fn ($q) => $q->where(
                    'farmer_id',
                    $user->farmer?->id ?? 0
                )
            ),
            default => $query, // admin, analyst
        };

        if ($cropCycleId = $request->query('crop_cycle_id')) {
            $query->where('crop_cycle_id', $cropCycleId);
        }

        if ($farmerId = $request->query('farmer_id')) {
            $query->whereHas(
                'cropCycle.plot',
                fn ($q) => $q->where('farmer_id', $farmerId)
            );
        }

        if ($cooperativeId = $request->query('cooperative_id')) {
            $query->whereHas(
                'cropCycle.plot',
                fn ($q) => $q->where('cooperative_id', $cooperativeId)
            );
        }

        if ($plotId = $request->query('plot_id')) {
            $query->whereHas(
                'cropCycle',
                fn ($q) => $q->where('plot_id', $plotId)
            );
        }

        if ($campaignId = $request->query('campaign_id')) {
            $query->whereHas(
                'cropCycle',
                fn ($q) => $q->where('campaign_id', $campaignId)
            );
        }

        return HarvestResource::collection(
            $query
                ->with(['cropCycle.plot', 'cropCycle.crop'])
                ->latest('harvest_date')
                ->paginate($this->perPage($request))
        );
    }

    public function store(StoreHarvestRequest $request): HarvestResource
    {
        $user = $request->user();
        $data = $request->validated();

        $plot = CropCycle::findOrFail($data['crop_cycle_id'])->plot;

        if ($user->role === 'cooperative_manager' && $plot?->cooperative_id !== $user->managedCooperative?->id) {
            throw ValidationException::withMessages([
                'crop_cycle_id' => ["Ce cycle cultural n'appartient pas à votre coopérative."],
            ]);
        }

        $harvest = $this->harvestService->create($data);
        $this->notifications->harvestRecorded($harvest);

        return new HarvestResource($harvest);
    }

    public function show(Harvest $harvest): HarvestResource
    {
        $this->authorize('view', $harvest);

        return new HarvestResource($harvest->load('cropCycle.plot'));
    }

    public function update(UpdateHarvestRequest $request, Harvest $harvest): HarvestResource
    {
        $harvest = $this->harvestService->update($harvest, $request->validated());

        return new HarvestResource($harvest);
    }

    public function destroy(Harvest $harvest): Response
    {
        $this->authorize('delete', $harvest);

        $harvest->delete();

        return response()->noContent();
    }
}

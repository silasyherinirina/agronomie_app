<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cooperative\StoreCooperativeRequest;
use App\Http\Requests\Cooperative\UpdateCooperativeRequest;
use App\Http\Resources\CooperativeResource;
use App\Models\Cooperative;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use MatanYadaev\EloquentSpatial\Objects\Point;

class CooperativeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Cooperative::class);

        $query = Cooperative::query()->withCount(['farmers', 'plots']);


        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('first_name', 'ilike', "%{$search}%")
                ->orWhere('name', 'ilike', "%{$search}%"));
        }

        if ($region = $request->query('region')) {
            $query->where('region', $region);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
         if ($cooperativeId = $request->query('cooperative_id')) {
    $query->where('id', $cooperativeId);
}

if ($plotId = $request->query('plot_id')) {
    $query->whereHas('plots', function ($plotQuery) use ($plotId) {
        $plotQuery->where('id', $plotId);
    });
}

if ($campaignId = $request->query('campaign_id')) {
    $query->whereHas('plots.cropCycles', function ($cycleQuery) use ($campaignId) {
        $cycleQuery->where('campaign_id', $campaignId);
    });
}

        $perPage = $this->perPage($request);

return CooperativeResource::collection(
    $query
        ->orderByDesc('updated_at')
        ->orderByDesc('id')
        ->paginate($perPage)
);
    }

    public function store(StoreCooperativeRequest $request): CooperativeResource
    {
        $cooperative = Cooperative::create($this->mapAttributes($request->validated()));

        return new CooperativeResource($cooperative);
    }

    public function show(Cooperative $cooperative): CooperativeResource
    {
        $this->authorize('view', $cooperative);

        return new CooperativeResource($cooperative->loadCount(['farmers', 'plots'])->load('manager'));
    }

    public function update(UpdateCooperativeRequest $request, Cooperative $cooperative): CooperativeResource
    {
        $cooperative->update($this->mapAttributes($request->validated()));

        return new CooperativeResource($cooperative);
    }

    public function destroy(Cooperative $cooperative): Response
    {
        $this->authorize('delete', $cooperative);

        $cooperative->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapAttributes(array $validated): array
    {
        if (array_key_exists('geometry', $validated)) {
            $validated['location'] = $validated['geometry'] ? Point::fromArray($validated['geometry']) : null;
            unset($validated['geometry']);
        }

        return $validated;
    }
}

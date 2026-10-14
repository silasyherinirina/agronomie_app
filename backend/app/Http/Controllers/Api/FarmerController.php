<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farmer\StoreFarmerRequest;
use App\Http\Requests\Farmer\UpdateFarmerRequest;
use App\Http\Resources\FarmerResource;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FarmerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Farmer::class);

        $query = Farmer::query()
            ->with('cooperative')
            ->withCount('plots');

        $user = $request->user();

        $query = match ($user->role) {
            'cooperative_manager' => $query->where(
                'cooperative_id',
                $user->managedCooperative?->id ?? 0
            ),
            'farmer' => $query->where('user_id', $user->id),
            default => $query,
        };

        if ($cooperativeId = $request->query('cooperative_id')) {
            $query->where('cooperative_id', $cooperativeId);
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

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('first_name', 'ilike', "%{$search}%")
                ->orWhere('last_name', 'ilike', "%{$search}%")
            );
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return FarmerResource::collection(
    $query
        ->orderByDesc('updated_at')
        ->orderByDesc('id')
        ->paginate($this->perPage($request))
);
    }

    public function store(StoreFarmerRequest $request): FarmerResource
    {
        $user = $request->user();
        $data = $request->validated();

        // Un cooperative_manager ne peut créer un agriculteur que dans sa propre coopérative,
        // quelle que soit la valeur envoyée par le client.
        $data['cooperative_id'] = $user->role === 'admin'
            ? $data['cooperative_id']
            : $user->managedCooperative?->id;

        $farmer = Farmer::create($data);

        return new FarmerResource($farmer);
    }

    public function show(Farmer $farmer): FarmerResource
    {
        $this->authorize('view', $farmer);

        return new FarmerResource($farmer->load('cooperative')->loadCount('plots'));
    }

    public function update(UpdateFarmerRequest $request, Farmer $farmer): FarmerResource
    {
        $data = $request->validated();
        unset($data['cooperative_id']); // non modifiable après création (rattachement fixé à l'adhésion)

        $farmer->update($data);

        return new FarmerResource($farmer);
    }

    public function destroy(Farmer $farmer): Response
    {
        $this->authorize('delete', $farmer);

        $farmer->delete();

        return response()->noContent();
    }
}

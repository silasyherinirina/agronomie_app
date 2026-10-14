<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesReferentialIntegrity;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgriculturalCampaign\StoreAgriculturalCampaignRequest;
use App\Http\Requests\AgriculturalCampaign\UpdateAgriculturalCampaignRequest;
use App\Http\Resources\AgriculturalCampaignResource;
use App\Models\AgriculturalCampaign;
use App\Services\NotificationDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgriculturalCampaignController extends Controller
{
    use HandlesReferentialIntegrity;

    public function __construct(private readonly NotificationDispatchService $notifications) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AgriculturalCampaign::class);

        $query = AgriculturalCampaign::query();

        $query = AgriculturalCampaign::query();

if ($year = $request->query('year')) {
    $query->where('year', $year);
}

if ($status = $request->query('status')) {
    $query->where('status', $status);
}

if ($campaignId = $request->query('campaign_id')) {
    $query->where('id', $campaignId);
}

if ($plotId = $request->query('plot_id')) {
    $query->whereHas('cropCycles', function ($cycleQuery) use ($plotId) {
        $cycleQuery->where('plot_id', $plotId);
    });
}

if ($cooperativeId = $request->query('cooperative_id')) {
    $query->whereHas('cropCycles.plot', function ($plotQuery) use ($cooperativeId) {
        $plotQuery->where('cooperative_id', $cooperativeId);
    });
}

        return AgriculturalCampaignResource::collection(
            $query->orderByDesc('year')->paginate($this->perPage($request))
        );
    }

    public function store(StoreAgriculturalCampaignRequest $request): AgriculturalCampaignResource
    {
        $campaign = AgriculturalCampaign::create($request->validated());

        // Une campagne créée directement active (comportement par défaut du modèle) est déjà
        // une transition réelle vers 'active' — même règle que update() ci-dessous.
        if ($campaign->status === 'active') {
            $this->notifications->campaignActivated($campaign);
        }

        return new AgriculturalCampaignResource($campaign);
    }

    public function show(AgriculturalCampaign $agriculturalCampaign): AgriculturalCampaignResource
    {
        $this->authorize('view', $agriculturalCampaign);

        return new AgriculturalCampaignResource($agriculturalCampaign);
    }

    public function update(UpdateAgriculturalCampaignRequest $request, AgriculturalCampaign $agriculturalCampaign): AgriculturalCampaignResource
    {
        $wasActive = $agriculturalCampaign->status === 'active';

        $agriculturalCampaign->update($request->validated());

        // Notifie seulement sur une vraie transition vers 'active' (jamais à chaque
        // modification de la campagne, ni si elle était déjà active — cahier des charges §5).
        if (! $wasActive && $agriculturalCampaign->status === 'active') {
            $this->notifications->campaignActivated($agriculturalCampaign);
        }

        return new AgriculturalCampaignResource($agriculturalCampaign);
    }

    public function destroy(AgriculturalCampaign $agriculturalCampaign): JsonResponse
    {
        $this->authorize('delete', $agriculturalCampaign);

        return $this->deleteOrConflict(
            fn () => $agriculturalCampaign->delete(),
            'Impossible de supprimer cette campagne : elle est encore référencée par des cycles culturaux ou des utilisations d\'intrants.'
        );
    }
}

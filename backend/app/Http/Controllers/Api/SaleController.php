<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesReferentialIntegrity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\NotificationDispatchService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Module M — Ventes agricoles. Le périmètre de données (coopérative/agriculteur) est déterminé
 * par SaleService::scopedQuery() selon le rôle, jamais élargi via les paramètres de requête pour
 * cooperative_manager/farmer (même garde-fou que HarvestController/DashboardController).
 */
class SaleController extends Controller
{
    use HandlesReferentialIntegrity;

    public function __construct(
        private readonly SaleService $sales,
        private readonly NotificationDispatchService $notifications,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sale::class);

        $query = $this->sales->scopedQuery($request->user())
            ->with(['cooperative', 'farmer', 'plot', 'crop', 'variety', 'campaign']);

        $this->sales->applyFilters($query, $request->query());

        return SaleResource::collection($query->latest('sale_date')->paginate($this->perPage($request)));
    }

    public function store(StoreSaleRequest $request): SaleResource
    {
        $sale = $this->sales->create($request->validated(), $request->user());
        $this->notifications->saleRecorded($sale);

        return new SaleResource($sale->load(['cooperative', 'farmer', 'plot', 'crop', 'variety', 'campaign']));
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorize('view', $sale);

        return new SaleResource($sale->load(['cooperative', 'farmer', 'plot', 'crop', 'variety', 'campaign']));
    }

    public function update(UpdateSaleRequest $request, Sale $sale): SaleResource
    {
        $sale = $this->sales->update($sale, $request->validated());

        return new SaleResource($sale->load(['cooperative', 'farmer', 'plot', 'crop', 'variety', 'campaign']));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $this->authorize('delete', $sale);

        return $this->deleteOrConflict(
            fn () => $sale->delete(),
            'Impossible de supprimer cette vente : elle est encore référencée ailleurs.'
        );
    }

    /**
     * GET /api/sales/statistics — chiffre d'affaires, quantités par unité, répartition par
     * culture, sur le même périmètre et les mêmes filtres que l'index (§27 : cohérence avec le
     * dashboard, jamais un calcul divergent pour les mêmes filtres).
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        $query = $this->sales->scopedQuery($request->user());
        $this->sales->applyFilters($query, $request->query());

        return response()->json(['data' => $this->sales->statistics($query)]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesReferentialIntegrity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crop\StoreCropRequest;
use App\Http\Requests\Crop\UpdateCropRequest;
use App\Http\Resources\CropResource;
use App\Models\Crop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CropController extends Controller
{
    use HandlesReferentialIntegrity;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Crop::class);

        $query = Crop::query()->withCount('varieties');

        // Architecture multi-cultures §8/§21 : les cultures désactivées (résidus de tests
        // exécutés par le passé directement sur la base de développement, ex. "cupiditate
        // (test)") ne doivent jamais apparaître dans une interface de production — jamais
        // supprimées (elles restent utilisables par les tests automatisés qui les créent),
        // seulement masquées par défaut. Un administrateur peut les faire réapparaître
        // explicitement (diagnostic/nettoyage) via ?include_inactive=1.
        $includeInactive = $request->boolean('include_inactive') && $request->user()->role === 'admin';
        if (! $includeInactive) {
            $query->where('is_active', true);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        return CropResource::collection(
    $query
        ->orderByDesc('updated_at')
        ->orderByDesc('id')
        ->paginate($this->perPage($request))
);
    }

    public function store(StoreCropRequest $request): CropResource
    {
        return new CropResource(Crop::create($request->validated()));
    }

    public function show(Crop $crop): CropResource
    {
        $this->authorize('view', $crop);

        return new CropResource($crop->load('varieties'));
    }

    public function update(UpdateCropRequest $request, Crop $crop): CropResource
    {
        $crop->update($request->validated());

        return new CropResource($crop);
    }

    public function destroy(Crop $crop): JsonResponse
    {
        $this->authorize('delete', $crop);

        return $this->deleteOrConflict(
            fn () => $crop->delete(),
            'Impossible de supprimer cette culture : elle est encore utilisée par des cycles culturaux.'
        );
    }
}

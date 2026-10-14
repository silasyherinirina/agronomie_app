<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesReferentialIntegrity;
use App\Http\Controllers\Controller;
use App\Http\Requests\CropVariety\StoreCropVarietyRequest;
use App\Http\Requests\CropVariety\UpdateCropVarietyRequest;
use App\Http\Resources\CropVarietyResource;
use App\Models\CropVariety;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CropVarietyController extends Controller
{
    use HandlesReferentialIntegrity;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CropVariety::class);

        $query = CropVariety::query()->with('crop');

        if ($cropId = $request->query('crop_id')) {
            $query->where('crop_id', $cropId);
        }

        return CropVarietyResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(StoreCropVarietyRequest $request): CropVarietyResource
    {
        return new CropVarietyResource(CropVariety::create($request->validated()));
    }

    public function show(CropVariety $cropVariety): CropVarietyResource
    {
        $this->authorize('view', $cropVariety);

        return new CropVarietyResource($cropVariety->load('crop'));
    }

    public function update(UpdateCropVarietyRequest $request, CropVariety $cropVariety): CropVarietyResource
    {
        $cropVariety->update($request->validated());

        return new CropVarietyResource($cropVariety);
    }

    public function destroy(CropVariety $cropVariety): JsonResponse
    {
        $this->authorize('delete', $cropVariety);

        return $this->deleteOrConflict(
            fn () => $cropVariety->delete(),
            'Impossible de supprimer cette variété : elle est encore utilisée par des cycles culturaux.'
        );
    }
}

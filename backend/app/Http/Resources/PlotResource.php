<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cooperative_id' => $this->cooperative_id,
            'cooperative' => new CooperativeResource($this->whenLoaded('cooperative')),
            'farmer_id' => $this->farmer_id,
            'farmer' => new FarmerResource($this->whenLoaded('farmer')),
            'name' => $this->name,
            'geometry' => $this->geom?->toArray(),
            'area_ha' => $this->area_ha !== null ? (float) $this->area_ha : null,
            'status' => $this->status,
            'crop_cycles_count' => $this->whenCounted('cropCycles'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

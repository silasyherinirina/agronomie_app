<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HarvestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'crop_cycle_id' => $this->crop_cycle_id,
            'crop_cycle' => new CropCycleResource($this->whenLoaded('cropCycle')),
            'harvest_date' => $this->harvest_date?->toDateString(),
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'yield_value' => $this->yield_value !== null ? (float) $this->yield_value : null,
            'yield_source' => $this->yield_source,
            'quality' => $this->quality,
            'observations' => $this->observations,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

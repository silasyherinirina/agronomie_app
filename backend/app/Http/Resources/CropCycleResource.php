<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CropCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plot_id' => $this->plot_id,
            'plot' => new PlotResource($this->whenLoaded('plot')),
            'crop_id' => $this->crop_id,
            'crop' => new CropResource($this->whenLoaded('crop')),
            'variety_id' => $this->variety_id,
            'variety' => new CropVarietyResource($this->whenLoaded('variety')),
            'campaign_id' => $this->campaign_id,
            'campaign' => new AgriculturalCampaignResource($this->whenLoaded('campaign')),
            'planting_date' => $this->planting_date?->toDateString(),
            'expected_harvest_date' => $this->expected_harvest_date?->toDateString(),
            'status' => $this->status,
            'harvests_count' => $this->whenCounted('harvests'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

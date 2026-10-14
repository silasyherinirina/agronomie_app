<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InputUsageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'input_id' => $this->input_id,
            'input' => new InputResource($this->whenLoaded('input')),
            'plot_id' => $this->plot_id,
            'plot' => new PlotResource($this->whenLoaded('plot')),
            'campaign_id' => $this->campaign_id,
            'campaign' => new AgriculturalCampaignResource($this->whenLoaded('campaign')),
            'farmer_id' => $this->farmer_id,
            'farmer' => new FarmerResource($this->whenLoaded('farmer')),
            'quantity' => (float) $this->quantity,
            'cost' => $this->cost !== null ? (float) $this->cost : null,
            'date' => $this->date?->toDateString(),
            'observations' => $this->observations,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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
            'plot_id' => $this->plot_id,
            'plot' => new PlotResource($this->whenLoaded('plot')),
            'crop_id' => $this->crop_id,
            'crop' => new CropResource($this->whenLoaded('crop')),
            'variety_id' => $this->variety_id,
            'variety' => new CropVarietyResource($this->whenLoaded('variety')),
            'crop_cycle_id' => $this->crop_cycle_id,
            'campaign_id' => $this->campaign_id,
            'campaign' => new AgriculturalCampaignResource($this->whenLoaded('campaign')),
            'buyer_name' => $this->buyer_name,
            'buyer_contact' => $this->buyer_contact,
            'sale_date' => $this->sale_date?->toDateString(),
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'observations' => $this->observations,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

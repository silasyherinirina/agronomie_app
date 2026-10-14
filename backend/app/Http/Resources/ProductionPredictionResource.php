<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionPredictionResource extends JsonResource
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
            'campaign_id' => $this->campaign_id,
            'campaign' => new AgriculturalCampaignResource($this->whenLoaded('campaign')),
            'predicted_yield' => (float) $this->predicted_yield,
            // unit/feature_importances/is_experimental : attributs transitoires posés par
            // PredictionService juste après l'appel au service IA (non stockés en base).
            'unit' => $this->unit ?? 'kg/ha',
            'input_features' => $this->input_features,
            'feature_importances' => $this->feature_importances ?? null,
            'is_experimental' => $this->is_experimental ?? true,
            'model' => $this->whenLoaded('modelVersion', fn () => [
                'id' => $this->modelVersion->external_id,
                'name' => $this->modelVersion->name,
                'algorithm' => $this->modelVersion->algorithm,
                'version' => $this->modelVersion->version,
                'trained_at' => $this->modelVersion->trained_at,
                'metrics' => $this->modelVersion->metrics,
            ]),
            'predicted_at' => $this->predicted_at,
            'created_at' => $this->created_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CropResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Identifiant stable pour le service IA (ex. "pomme_de_terre") — voir
            // PredictionService::resolveCropKey(). Peut être null pour une culture créée avant
            // cette extension et jamais réconciliée manuellement.
            'code' => $this->code,
            'category' => $this->category,
            'cycle_duration_days' => $this->cycle_duration_days,
            'is_active' => (bool) $this->is_active,
            'varieties' => CropVarietyResource::collection($this->whenLoaded('varieties')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

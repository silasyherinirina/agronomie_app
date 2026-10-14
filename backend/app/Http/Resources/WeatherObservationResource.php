<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Module L — Météo (extension, Phase 11). Observations saisies manuellement ; aucun
 * fournisseur externe n'est branché pour l'instant (voir WeatherObservationController).
 */
class WeatherObservationResource extends JsonResource
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
            'observed_date' => $this->observed_date?->toDateString(),
            'temperature' => $this->temperature !== null ? (float) $this->temperature : null,
            'precipitation' => $this->precipitation !== null ? (float) $this->precipitation : null,
            'humidity' => $this->humidity !== null ? (float) $this->humidity : null,
            'source' => $this->source,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

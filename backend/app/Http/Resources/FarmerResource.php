<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmerResource extends JsonResource
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
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'join_date' => $this->join_date?->toDateString(),
            'status' => $this->status,
            'plots_count' => $this->whenCounted('plots'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

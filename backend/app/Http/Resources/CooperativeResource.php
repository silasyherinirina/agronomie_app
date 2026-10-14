<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CooperativeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'region' => $this->region,
            'district' => $this->district,
            'commune' => $this->commune,
            'geometry' => $this->location?->toArray(),
            'manager_user_id' => $this->manager_user_id,
            'manager' => new UserResource($this->whenLoaded('manager')),
            'status' => $this->status,
            'farmers_count' => $this->whenCounted('farmers'),
            'plots_count' => $this->whenCounted('plots'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

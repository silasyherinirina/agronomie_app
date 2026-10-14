<?php

namespace App\Http\Requests\Plot;

use App\Models\Plot;
use App\Rules\WithinMadagascarBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Plot::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Ignoré pour un cooperative_manager : forcé côté contrôleur à sa propre coopérative.
            'cooperative_id' => ['sometimes', 'integer', Rule::exists('cooperatives', 'id')],
            'farmer_id' => ['nullable', 'integer', Rule::exists('farmers', 'id')],
            'name' => ['required', 'string', 'max:255'],
            // GeoJSON Polygon : {"type": "Polygon", "coordinates": [[[lng,lat], ...]]}
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', Rule::in(['Polygon'])],
            'geometry.coordinates' => ['required', 'array', 'min:1'],
            'geometry.coordinates.*' => ['array', 'min:4'],
            'geometry.coordinates.*.*' => [new WithinMadagascarBounds],
            // 'sometimes' : si absent, on laisse le défaut SQL ('active') s'appliquer.
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}

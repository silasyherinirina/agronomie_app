<?php

namespace App\Http\Requests\Cooperative;

use App\Models\Cooperative;
use App\Rules\WithinMadagascarBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCooperativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Cooperative::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'region' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            // GeoJSON Point : {"type": "Point", "coordinates": [lng, lat]}
            'geometry' => ['nullable', 'array'],
            'geometry.type' => ['required_with:geometry', Rule::in(['Point'])],
            'geometry.coordinates' => ['required_with:geometry', 'array', 'size:2', new WithinMadagascarBounds],
            'manager_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            // 'sometimes' : si absent, on laisse le défaut SQL ('active') s'appliquer plutôt
            // que d'insérer explicitement NULL (validated() inclurait sinon la clé à null).
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}

<?php

namespace App\Http\Requests\Plot;

use App\Rules\WithinMadagascarBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plot'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'farmer_id' => ['nullable', 'integer', Rule::exists('farmers', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'geometry' => ['sometimes', 'array'],
            'geometry.type' => ['required_with:geometry', Rule::in(['Polygon'])],
            'geometry.coordinates' => ['required_with:geometry', 'array', 'min:1'],
            'geometry.coordinates.*' => ['array', 'min:4'],
            'geometry.coordinates.*.*' => [new WithinMadagascarBounds],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}

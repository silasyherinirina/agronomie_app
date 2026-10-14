<?php

namespace App\Http\Requests\Cooperative;

use App\Rules\WithinMadagascarBounds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCooperativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('cooperative'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'region' => ['sometimes', 'required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'geometry' => ['nullable', 'array'],
            'geometry.type' => ['required_with:geometry', Rule::in(['Point'])],
            'geometry.coordinates' => ['required_with:geometry', 'array', 'size:2', new WithinMadagascarBounds],
            'manager_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}

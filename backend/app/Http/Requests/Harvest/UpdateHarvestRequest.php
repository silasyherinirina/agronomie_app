<?php

namespace App\Http\Requests\Harvest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('harvest'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'harvest_date' => ['sometimes', 'required', 'date'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'yield_source' => ['sometimes', Rule::in(['calculated', 'declared'])],
            'yield_value' => ['required_if:yield_source,declared', 'nullable', 'numeric', 'min:0'],
            'quality' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
        ];
    }
}

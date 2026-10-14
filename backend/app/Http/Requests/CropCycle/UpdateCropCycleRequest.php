<?php

namespace App\Http\Requests\CropCycle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCropCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('crop_cycle'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'variety_id' => ['nullable', 'integer', Rule::exists('crop_varieties', 'id')],
            'planting_date' => ['nullable', 'date'],
            'expected_harvest_date' => ['nullable', 'date', 'after_or_equal:planting_date'],
            'status' => ['sometimes', Rule::in(['in_progress', 'harvested', 'failed'])],
        ];
    }
}

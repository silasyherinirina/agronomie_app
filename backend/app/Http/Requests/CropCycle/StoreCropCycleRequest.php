<?php

namespace App\Http\Requests\CropCycle;

use App\Models\CropCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCropCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CropCycle::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plot_id' => ['required', 'integer', Rule::exists('plots', 'id')],
            'crop_id' => ['required', 'integer', Rule::exists('crops', 'id')],
            'variety_id' => ['nullable', 'integer', Rule::exists('crop_varieties', 'id')],
            'campaign_id' => ['required', 'integer', Rule::exists('agricultural_campaigns', 'id')],
            'planting_date' => ['nullable', 'date'],
            'expected_harvest_date' => ['nullable', 'date', 'after_or_equal:planting_date'],
            // 'sometimes' : si absent, on laisse le défaut SQL ('in_progress') s'appliquer.
            'status' => ['sometimes', 'nullable', Rule::in(['in_progress', 'harvested', 'failed'])],
        ];
    }
}

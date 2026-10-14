<?php

namespace App\Http\Requests\InputUsage;

use App\Models\InputUsage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInputUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', InputUsage::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'input_id' => ['required', 'integer', Rule::exists('inputs', 'id')],
            'plot_id' => ['required', 'integer', Rule::exists('plots', 'id')],
            'campaign_id' => ['required', 'integer', Rule::exists('agricultural_campaigns', 'id')],
            'farmer_id' => ['nullable', 'integer', Rule::exists('farmers', 'id')],
            'quantity' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'observations' => ['nullable', 'string'],
        ];
    }
}

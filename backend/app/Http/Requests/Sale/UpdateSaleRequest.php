<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('sale'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // cooperative_id volontairement absent : une vente ne change jamais de coopérative
            // après création (SaleService l'ignore explicitement en update, voir ce service).
            'farmer_id' => ['sometimes', 'nullable', 'integer', Rule::exists('farmers', 'id')],
            'plot_id' => ['sometimes', 'nullable', 'integer', Rule::exists('plots', 'id')],
            'crop_id' => ['sometimes', 'nullable', 'integer', Rule::exists('crops', 'id')],
            'variety_id' => ['sometimes', 'nullable', 'integer', Rule::exists('crop_varieties', 'id')],
            'crop_cycle_id' => ['sometimes', 'nullable', 'integer', Rule::exists('crop_cycles', 'id')],
            'campaign_id' => ['sometimes', 'nullable', 'integer', Rule::exists('agricultural_campaigns', 'id')],
            'buyer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'buyer_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sale_date' => ['sometimes', 'required', 'date'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'observations' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

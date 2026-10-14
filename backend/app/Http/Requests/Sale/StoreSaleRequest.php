<?php

namespace App\Http\Requests\Sale;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
    }

    /**
     * Validation de forme uniquement (existence/type). La normalisation de `cooperative_id`
     * selon le rôle (jamais fait confiance au frontend, cahier des charges §11) et la cohérence
     * relationnelle entre les FK (§26 : plot/farmer/crop_cycle doivent réellement appartenir à la
     * même coopérative) sont de la responsabilité de SaleService::resolveAttributes(), pas de
     * cette classe — voir ce service pour le détail des règles.
     *
     * `total_amount` est volontairement ABSENT de ces règles : une valeur envoyée par le
     * frontend pour ce champ est silencieusement ignorée (jamais lue), le backend le recalcule
     * systématiquement (§6).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cooperative_id' => ['nullable', 'integer', Rule::exists('cooperatives', 'id')],
            'farmer_id' => ['nullable', 'integer', Rule::exists('farmers', 'id')],
            'plot_id' => ['nullable', 'integer', Rule::exists('plots', 'id')],
            'crop_id' => ['nullable', 'integer', Rule::exists('crops', 'id')],
            'variety_id' => ['nullable', 'integer', Rule::exists('crop_varieties', 'id')],
            'crop_cycle_id' => ['nullable', 'integer', Rule::exists('crop_cycles', 'id')],
            'campaign_id' => ['nullable', 'integer', Rule::exists('agricultural_campaigns', 'id')],
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_contact' => ['nullable', 'string', 'max:255'],
            'sale_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'observations' => ['nullable', 'string'],
        ];
    }
}

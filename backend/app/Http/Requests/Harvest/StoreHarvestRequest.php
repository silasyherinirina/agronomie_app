<?php

namespace App\Http\Requests\Harvest;

use App\Models\Harvest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Harvest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crop_cycle_id' => ['required', 'integer', Rule::exists('crop_cycles', 'id')],
            'harvest_date' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            // 'sometimes' : si absent, on laisse le défaut SQL ('calculated') s'appliquer —
            // important ici car HarvestService lit aussi ce champ pour choisir sa stratégie.
            'yield_source' => ['sometimes', 'nullable', Rule::in(['calculated', 'declared'])],
            // Obligatoire uniquement si l'utilisateur choisit de déclarer le rendement lui-même.
            'yield_value' => ['required_if:yield_source,declared', 'nullable', 'numeric', 'min:0'],
            'quality' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
        ];
    }
}

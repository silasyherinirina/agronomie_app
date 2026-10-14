<?php

namespace App\Http\Requests\AgriculturalCampaign;

use App\Models\AgriculturalCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgriculturalCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AgriculturalCampaign::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'season' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            // 'sometimes' : si absent, on laisse le défaut SQL ('active') s'appliquer plutôt
            // que d'insérer explicitement NULL (validated() inclurait sinon la clé à null).
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'closed'])],
        ];
    }
}

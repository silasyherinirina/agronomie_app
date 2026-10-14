<?php

namespace App\Http\Requests\Farmer;

use App\Models\Farmer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Farmer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Ignoré pour un cooperative_manager : la coopérative est forcée côté contrôleur
            // à celle qu'il gère réellement, pour éviter toute élévation de privilège.
            'cooperative_id' => ['sometimes', 'integer', Rule::exists('cooperatives', 'id')],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), 'unique:farmers,user_id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'join_date' => ['nullable', 'date'],
            // 'sometimes' : si absent, on laisse le défaut SQL ('active') s'appliquer.
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}

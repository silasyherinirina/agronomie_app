<?php

namespace App\Http\Requests\Crop;

use App\Models\Crop;
use App\Support\CropKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCropRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Crop::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:crops,name'],
            // Identifiant stable pour le service IA (§6 : "explicite et persistant", jamais
            // recalculé à la volée). Optionnel à la création : auto-généré une seule fois à
            // partir du nom si omis (voir withValidator ci-dessous), puis figé.
            'code' => ['nullable', 'string', 'max:255', 'unique:crops,code'],
            'category' => ['nullable', 'string', 'max:255'],
            'cycle_duration_days' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->filled('code') || ! $this->filled('name')) {
                return;
            }

            $generated = CropKey::fromName($this->input('name'));
            if (Crop::where('code', $generated)->exists()) {
                $validator->errors()->add('code', "Le code généré automatiquement (\"{$generated}\") est déjà utilisé — précisez un `code` explicite.");
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $data['code'] ??= CropKey::fromName($data['name']);

        return $data;
    }
}

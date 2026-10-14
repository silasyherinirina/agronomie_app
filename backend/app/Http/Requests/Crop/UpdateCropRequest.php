<?php

namespace App\Http\Requests\Crop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCropRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('crop'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('crops', 'name')->ignore($this->route('crop')?->id)],
            // §6 : le code reste modifiable explicitement par un administrateur (ex. corriger
            // une collision), mais n'est jamais recalculé automatiquement à la mise à jour.
            'code' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('crops', 'code')->ignore($this->route('crop')?->id)],
            'category' => ['nullable', 'string', 'max:255'],
            'cycle_duration_days' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

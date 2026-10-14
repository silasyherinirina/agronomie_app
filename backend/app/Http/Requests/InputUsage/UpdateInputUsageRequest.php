<?php

namespace App\Http\Requests\InputUsage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInputUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('input_usage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'date' => ['sometimes', 'required', 'date'],
            'observations' => ['nullable', 'string'],
        ];
    }
}

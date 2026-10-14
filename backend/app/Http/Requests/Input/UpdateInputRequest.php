<?php

namespace App\Http\Requests\Input;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('input'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['semence', 'engrais', 'phytosanitaire', 'autre'])],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
        ];
    }
}

<?php

namespace App\Http\Requests\Input;

use App\Models\Input;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Input::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['semence', 'engrais', 'phytosanitaire', 'autre'])],
            'unit' => ['required', 'string', 'max:50'],
        ];
    }
}

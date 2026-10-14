<?php

namespace App\Http\Requests\Farmer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('farmer'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmerId = $this->route('farmer')?->id;

        return [
            'cooperative_id' => ['sometimes', 'integer', Rule::exists('cooperatives', 'id')],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('farmers', 'user_id')->ignore($farmerId)],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'join_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}

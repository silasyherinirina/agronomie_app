<?php

namespace App\Http\Requests\CropVariety;

use App\Models\CropVariety;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCropVarietyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CropVariety::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crop_id' => ['required', 'integer', Rule::exists('crops', 'id')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('crop_varieties', 'name')->where(fn ($q) => $q->where('crop_id', $this->input('crop_id'))),
            ],
        ];
    }
}

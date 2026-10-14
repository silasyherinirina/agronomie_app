<?php

namespace App\Http\Requests\CropVariety;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCropVarietyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('crop_variety'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cropId = $this->input('crop_id', $this->route('crop_variety')?->crop_id);

        return [
            'crop_id' => ['sometimes', 'integer', Rule::exists('crops', 'id')],
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('crop_varieties', 'name')
                    ->where(fn ($q) => $q->where('crop_id', $cropId))
                    ->ignore($this->route('crop_variety')?->id),
            ],
        ];
    }
}

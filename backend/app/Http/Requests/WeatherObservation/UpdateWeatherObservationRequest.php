<?php

namespace App\Http\Requests\WeatherObservation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWeatherObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('weather_observation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'observed_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'temperature' => ['nullable', 'numeric', 'min:-10', 'max:55'],
            'precipitation' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}

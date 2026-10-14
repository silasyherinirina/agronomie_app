<?php

namespace App\Http\Requests\WeatherObservation;

use App\Models\WeatherObservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Saisie manuelle d'une observation météo (Module L, Phase 11 — extension non bloquante).
 * Aucun fournisseur météo externe n'est intégré : ces valeurs doivent provenir d'une mesure
 * réelle saisie par l'utilisateur (station, relevé terrain...), jamais générées ou estimées
 * automatiquement (cahier des charges §24).
 */
class StoreWeatherObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WeatherObservation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Ignoré pour un cooperative_manager si la parcelle n'appartient pas à sa
            // coopérative : vérifié explicitement dans le Controller (même pattern que
            // CropCycleController/ProductionPredictionController).
            'plot_id' => ['required', 'integer', Rule::exists('plots', 'id')],
            'observed_date' => ['required', 'date', 'before_or_equal:today'],
            'temperature' => ['nullable', 'numeric', 'min:-10', 'max:55'],
            'precipitation' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            if (empty($data['temperature']) && empty($data['precipitation']) && empty($data['humidity'])) {
                $validator->errors()->add(
                    'temperature',
                    'Au moins une mesure (température, précipitations ou humidité) est requise.'
                );
            }
        });
    }
}

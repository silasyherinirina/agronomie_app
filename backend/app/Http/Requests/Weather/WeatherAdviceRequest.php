<?php

namespace App\Http\Requests\Weather;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Module — Conseil agronomique prédictif basé sur la météo. `date` est OBLIGATOIRE (cahier des
 * charges §3) : elle détermine seule si l'analyse sera historique, actuelle ou prévisionnelle.
 * Aucune borne de date n'est imposée ici : une date trop ancienne ou trop lointaine reste une
 * requête valide, simplement sans donnée disponible (message honnête produit par le service,
 * jamais une erreur 422 — §21).
 */
class WeatherAdviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('plot'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}

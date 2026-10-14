<?php

namespace App\Http\Requests\Prediction;

use App\Models\ProductionPrediction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductionPrediction::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plot_id' => ['required', 'integer', Rule::exists('plots', 'id')],
            // Architecture multi-cultures : OBLIGATOIRE — plus de repli implicite sur le riz
            // (cahier des charges §7/§40 : "aucune culture sélectionnée => pas de prédiction
            // possible"). `Rule::exists` avec 'where' vérifie en un seul passage que la culture
            // existe ET qu'elle est active (§20 : "culture inactive => prédiction refusée").
            'crop_id' => ['required', 'integer', Rule::exists('crops', 'id')->where('is_active', true)],
            'campaign_id' => ['nullable', 'integer', Rule::exists('agricultural_campaigns', 'id')],
            // Variables du modèle ML (Phase 9, app/ml/features.py côté service IA) — bornes
            // alignées sur celles du schéma Pydantic pour échouer côté Laravel avant l'appel HTTP.
            'features' => ['required', 'array'],
            'features.area_ha' => ['required', 'numeric', 'gt:0', 'max:50'],
            'features.fertilizer_kg_per_ha' => ['required', 'numeric', 'min:0', 'max:1000'],
            'features.rainfall_mm' => ['required', 'numeric', 'min:0', 'max:5000'],
            'features.planting_month' => ['required', 'integer', 'min:1', 'max:12'],
            'features.variety_class' => ['required', Rule::in(['traditionnelle', 'amelioree'])],
        ];
    }
}

<?php

namespace App\Http\Requests\Report;

use App\Services\Reports\ReportService;
use App\Services\Reports\UnknownReportTypeException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tout utilisateur authentifié peut générer un rapport : le périmètre des données
        // (coopérative/agriculteur) est appliqué par ReportDataBuilder selon son rôle, jamais
        // élargi via les filtres — même principe que DashboardController (§13/§33 : un
        // utilisateur ne doit jamais pouvoir télécharger les données d'une autre coopérative).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(ReportService::SUPPORTED_FORMATS)],
            // Restructuration "cycle cultural = relation racine" (§1/§10) : filtre prioritaire —
            // quand présent, ReportDataBuilder en dérive/valide toutes les autres dimensions
            // (Mode B). Les filtres ci-dessous restent fonctionnels indépendamment en son absence
            // (Mode A, §10 : "les filtres globaux peuvent être utilisés selon les fonctionnalités
            // déjà prévues") — capacité backend existante volontairement conservée.
            'crop_cycle_id' => ['nullable', 'integer', 'exists:crop_cycles,id'],
            'cooperative_id' => ['nullable', 'integer', 'exists:cooperatives,id'],
            'farmer_id' => ['nullable', 'integer', 'exists:farmers,id'],
            'plot_id' => ['nullable', 'integer', 'exists:plots,id'],
            'crop_id' => ['nullable', 'integer', 'exists:crops,id'],
            'variety_id' => ['nullable', 'integer', 'exists:crop_varieties,id'],
            'campaign_id' => ['nullable', 'integer', 'exists:agricultural_campaigns,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! in_array($this->route('type'), UnknownReportTypeException::SUPPORTED_TYPES, true)) {
                $validator->errors()->add('type', "Type de rapport inconnu : '{$this->route('type')}'.");
            }
        });
    }
}

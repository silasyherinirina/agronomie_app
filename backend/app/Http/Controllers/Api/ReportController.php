<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\GenerateReportRequest;
use App\Services\Reports\ReportService;
use App\Services\Reports\UnknownReportFormatException;
use App\Services\Reports\UnknownReportTypeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module Rapports (§17) : un seul point d'entrée pour les 3 formats d'export, tous alimentés
 * par les mêmes données (voir ReportService). `type` fait partie de l'URL (ressource), `format`
 * et les filtres sont des paramètres de requête — GET /api/reports/{type}?format=csv&....
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function generate(GenerateReportRequest $request, string $type): Response
    {
        try {
            return $this->reports->generate($type, $request->validated('format'), $request->validated(), $request->user());
        } catch (UnknownReportTypeException|UnknownReportFormatException $e) {
            // Défense en profondeur : GenerateReportRequest valide déjà `type`/`format` avant
            // d'arriver ici — ce chemin ne devrait normalement jamais s'exécuter.
            abort(422, $e->getMessage());
        }
    }
}

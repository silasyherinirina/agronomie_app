<?php

namespace App\Services\Reports\Formatters;

use App\Services\Reports\ReportData;
use Illuminate\Http\Response;

/**
 * Export PDF (§22), via dompdf (barryvdh/laravel-dompdf — pur PHP, aucun binaire externe requis
 * contrairement à une solution basée sur un navigateur headless). Le gabarit
 * resources/views/reports/report.blade.php est partagé par les 3 types de rapports : seules les
 * données (ReportData) changent, jamais la mise en page (§22/§23).
 */
class PdfFormatter
{
    public function format(ReportData $data): Response
    {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('reports.report', ['data' => $data]);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download("{$data->suggestedFilename()}.pdf");
    }
}

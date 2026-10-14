<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Formatters\CsvFormatter;
use App\Services\Reports\Formatters\ExcelFormatter;
use App\Services\Reports\Formatters\PdfFormatter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Point d'entrée unique du Module Rapports (§17/§23) :
 *
 *     ReportController -> ReportService -> ReportDataBuilder -> ReportFormatter (CSV/Excel/PDF)
 *
 * Garantit que les 3 formats représentent TOUJOURS les mêmes données pour les mêmes filtres —
 * un seul ReportDataBuilder::build() par génération, jamais une requête différente par format.
 */
class ReportService
{
    public const SUPPORTED_FORMATS = ['csv', 'xlsx', 'pdf'];

    public function __construct(
        private readonly ReportDataBuilder $dataBuilder,
        private readonly CsvFormatter $csv,
        private readonly ExcelFormatter $excel,
        private readonly PdfFormatter $pdf,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function generate(string $type, string $format, array $filters, User $user): Response
    {
        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new UnknownReportFormatException($format);
        }

        $data = $this->dataBuilder->build($type, $filters, $user);

        return match ($format) {
            'csv' => $this->csv->format($data),
            'xlsx' => $this->excel->format($data),
            'pdf' => $this->pdf->format($data),
        };
    }
}

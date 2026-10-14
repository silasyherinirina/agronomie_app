<?php

namespace App\Services\Reports\Formatters;

use App\Services\Reports\ReportData;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV (§20) : UTF-8 (avec BOM pour qu'Excel le détecte automatiquement — sans lui, Excel
 * interprète les accents comme du Windows-1252 et affiche des caractères corrompus), séparateur
 * point-virgule (convention Excel FR, où la virgule est déjà le séparateur décimal), en-têtes
 * explicites, dates/nombres déjà formatés par ReportDataBuilder.
 */
class CsvFormatter
{
    public function format(ReportData $data): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($data) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 : condition documentée ci-dessus, pas un artefact oublié.
            fwrite($handle, "\xEF\xBB\xBF");

            $this->writeLine($handle, [$data->title]);
            $this->writeLine($handle, ['Généré le', $data->generatedAt->format('d/m/Y H:i')]);
            foreach ($data->filtersSummary as $label => $value) {
                $this->writeLine($handle, [$label, $value]);
            }
            $this->writeLine($handle, []);

            foreach ($data->summary as $label => $value) {
                $this->writeLine($handle, [$label, $value]);
            }
            $this->writeLine($handle, []);

            $this->writeLine($handle, array_values($data->columns));
            foreach ($data->rows as $row) {
                $this->writeLine($handle, $this->formatRow($data, $row));
            }

            if ($data->totals !== []) {
                $this->writeLine($handle, []);
                $totalsRow = array_map(fn ($key) => $data->totals[$key] ?? '', array_keys($data->columns));
                $totalsRow[0] = 'TOTAL';
                $this->writeLine($handle, $totalsRow);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$data->suggestedFilename()}.csv\"");

        return $response;
    }

    /**
     * @param  resource  $handle
     * @param  list<mixed>  $fields
     */
    private function writeLine($handle, array $fields): void
    {
        // Enclosure/escape explicites (RFC 4180 : guillemets doublés) — requis depuis PHP 8.5,
        // qui déprécie l'appel à fputcsv() sans `$escape` fourni explicitement (son défaut va
        // changer dans une version future).
        fputcsv($handle, $fields, ';', '"', '"');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function formatRow(ReportData $data, array $row): array
    {
        return array_map(function ($key) use ($row) {
            $value = $row[$key] ?? null;

            return match (true) {
                $value === null => '—',
                is_float($value) => number_format($value, 3, ',', ''),
                default => (string) $value,
            };
        }, array_keys($data->columns));
    }
}

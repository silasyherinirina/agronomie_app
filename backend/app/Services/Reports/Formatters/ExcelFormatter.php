<?php

namespace App\Services\Reports\Formatters;

use App\Services\Reports\ReportData;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Export Excel (§21), openspout — bibliothèque de streaming légère choisie plutôt que
 * PhpSpreadsheet/maatwebsite-excel : ces derniers exigent l'extension `ext-gd` (absente de cet
 * environnement) uniquement pour la manipulation d'images/graphiques, jamais utilisée ici (aucun
 * graphique embarqué dans un rapport tabulaire). openspout n'a aucune dépendance binaire.
 *
 * Deux feuilles (minimum demandé, §21) :
 *   1. "Résumé"      — titre, date de génération, filtres appliqués, indicateurs clés (KPI).
 *   2. Nom du type    — tableau détaillé avec en-têtes en gras, largeurs de colonnes ajustées,
 *      et une ligne TOTAL en pied de tableau quand `ReportData::$totals` est renseigné.
 */
class ExcelFormatter
{
    private const SHEET_NAMES = [
        'production' => 'Production',
        'harvests' => 'Récoltes',
        'sales' => 'Ventes',
    ];

    public function format(ReportData $data): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'report_').'.xlsx';

        $writer = new Writer();
        $writer->openToFile($tempPath);

        $this->writeSummarySheet($writer, $data);

        $writer->addNewSheetAndMakeItCurrent();
        $writer->getCurrentSheet()->setName(self::SHEET_NAMES[$data->type] ?? 'Données');
        $this->writeDataSheet($writer, $data);

        $writer->close();

        $filename = "{$data->suggestedFilename()}.xlsx";

        return response()
            ->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend()
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    }

    private function writeSummarySheet(Writer $writer, ReportData $data): void
    {
        $writer->getCurrentSheet()->setName('Résumé');

        $titleStyle = new Style(fontBold: true, fontSize: 14);
        $sectionStyle = new Style(fontBold: true, backgroundColor: 'F0F0F0');

        $writer->addRow(Row::fromValuesWithStyle(['AGRICULTURE MADA'], $titleStyle));
        $writer->addRow(Row::fromValues([$data->title]));
        $writer->addRow(Row::fromValues(['Généré le', $data->generatedAt->format('d/m/Y H:i')]));
        $writer->addRow(Row::fromValues([]));

        if ($data->filtersSummary !== []) {
            $writer->addRow(Row::fromValuesWithStyle(['Filtres appliqués'], $sectionStyle));
            foreach ($data->filtersSummary as $label => $value) {
                $writer->addRow(Row::fromValues([$label, $value]));
            }
            $writer->addRow(Row::fromValues([]));
        }

        $writer->addRow(Row::fromValuesWithStyle(['Indicateurs'], $sectionStyle));
        foreach ($data->summary as $label => $value) {
            $writer->addRow(Row::fromValues([$label, $value]));
        }

        $writer->getCurrentSheet()->setColumnWidth(28, 1);
        $writer->getCurrentSheet()->setColumnWidth(24, 2);
    }

    private function writeDataSheet(Writer $writer, ReportData $data): void
    {
        $headerStyle = new Style(fontBold: true, backgroundColor: 'D9EAD3');

        $writer->addRow(Row::fromValuesWithStyle(array_values($data->columns), $headerStyle));

        foreach ($data->rows as $row) {
            $writer->addRow(Row::fromValues($this->formatRow($data, $row)));
        }

        if ($data->totals !== []) {
            $totalsStyle = new Style(fontBold: true, backgroundColor: 'FFF2CC');
            $totalsRow = array_map(fn ($key) => $data->totals[$key] ?? '', array_keys($data->columns));
            $totalsRow[0] = 'TOTAL';
            $writer->addRow(Row::fromValuesWithStyle($totalsRow, $totalsStyle));
        }

        foreach (array_keys($data->columns) as $index => $key) {
            $writer->getCurrentSheet()->setColumnWidth(18, $index + 1);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private function formatRow(ReportData $data, array $row): array
    {
        return array_map(fn ($key) => $row[$key] ?? '—', array_keys($data->columns));
    }
}

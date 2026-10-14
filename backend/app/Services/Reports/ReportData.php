<?php

namespace App\Services\Reports;

use DateTimeImmutable;

/**
 * Module Rapports — représentation neutre et commune à TOUS les formats d'export (§23 :
 * "éviter une logique différente pour chaque format... PDF = Excel = CSV"). ReportDataBuilder
 * produit UNE seule instance par génération ; CsvFormatter/ExcelFormatter/PdfFormatter ne font
 * que la sérialiser différemment — aucun ne recalcule quoi que ce soit à partir de la base.
 */
final class ReportData
{
    /**
     * @param  array<string, string>  $filtersSummary  Libellés humains des filtres appliqués (ex. "Coopérative" => "MIAVOTRA"), pour l'en-tête PDF/Excel.
     * @param  array<string, string>  $summary  KPI résumé (ex. "Chiffre d'affaires" => "3 750 000 MGA").
     * @param  array<string, string>  $columns  Colonnes du tableau détaillé, clé technique => en-tête affiché, dans l'ordre d'affichage.
     * @param  list<array<string, mixed>>  $rows  Une entrée par ligne, indexée par les mêmes clés que `columns`.
     * @param  array<string, string>  $totals  Total pré-formaté par colonne sommable (clé de `columns`), absent pour les colonnes non sommables.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly DateTimeImmutable $generatedAt,
        public readonly array $filtersSummary,
        public readonly array $summary,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $totals = [],
    ) {}

    /**
     * Nom de fichier suggéré (sans extension) — dérivé du type et de l'instant de génération
     * pour rester unique et traçable, jamais un nom générique "export.csv".
     */
    public function suggestedFilename(): string
    {
        return sprintf('%s-%s', $this->type, $this->generatedAt->format('Ymd-His'));
    }
}

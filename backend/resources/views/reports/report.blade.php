<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $data->title }}</title>
    <style>
        {{-- dompdf ne supporte qu'un sous-ensemble de CSS (pas de flexbox/grid) : mise en page
             volontairement simple, table-based, pour un rendu fiable (§22). --}}
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 2px; color: #1f7a4d; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #1f7a4d; border-bottom: 1px solid #d0d0d0; padding-bottom: 3px; }
        .subtitle { font-size: 13px; margin: 0 0 14px; color: #444; }
        .meta { font-size: 10px; color: #666; margin-bottom: 10px; }
        table.filters, table.summary { border-collapse: collapse; margin-bottom: 6px; }
        table.filters td, table.summary td { padding: 2px 10px 2px 0; font-size: 10.5px; }
        table.filters td:first-child, table.summary td:first-child { font-weight: bold; color: #333; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th { background: #1f7a4d; color: #fff; text-align: left; padding: 5px 6px; font-size: 10px; text-transform: uppercase; }
        table.data td { padding: 4px 6px; font-size: 10px; border-bottom: 1px solid #e5e5e5; }
        table.data tr:nth-child(even) td { background: #f7f7f7; }
        table.data tfoot td { font-weight: bold; background: #fff2cc; border-top: 2px solid #d0a000; }
        .footer { margin-top: 16px; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <h1>AGRICULTURE MADA</h1>
    <p class="subtitle">{{ strtoupper($data->title) }}</p>
    <p class="meta">Généré le {{ $data->generatedAt->format('d/m/Y à H:i') }}</p>

    @if (count($data->filtersSummary) > 0)
        <h2>Filtres appliqués</h2>
        <table class="filters">
            @foreach ($data->filtersSummary as $label => $value)
                <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Résumé</h2>
    <table class="summary">
        @foreach ($data->summary as $label => $value)
            <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <h2>Détail</h2>
    <table class="data">
        <thead>
            <tr>
                @foreach ($data->columns as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($data->rows as $row)
                <tr>
                    @foreach (array_keys($data->columns) as $key)
                        @php $value = $row[$key] ?? null; @endphp
                        <td>
                            @if ($value === null)
                                —
                            @elseif (is_float($value))
                                {{ number_format($value, 3, ',', ' ') }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($data->columns) }}">Aucune donnée pour les filtres sélectionnés.</td></tr>
            @endforelse
        </tbody>
        @if (count($data->totals) > 0)
            <tfoot>
                <tr>
                    @foreach (array_keys($data->columns) as $index => $key)
                        <td>{{ $index === 0 ? 'TOTAL' : ($data->totals[$key] ?? '') }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <p class="footer">
        Agriculture Mada — Plateforme intelligente de gestion agricole pour les coopératives agricoles à Madagascar.
        Rapport généré automatiquement à partir des données enregistrées sur la plateforme.
    </p>
</body>
</html>

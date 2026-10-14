import { useState } from 'react'
import { Banknote, Download, FileSpreadsheet, FileText, TrendingUp, Wheat } from 'lucide-react'
import { PageHeader } from '../../components/PageHeader'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { ReportCard } from './ReportCard'
import { ReportFilters } from './ReportFilters'
import { downloadReport, type ReportFilters as ReportFiltersValue, type ReportFormat, type ReportType } from './api'
import type { NormalizedError } from '../../services/http'

const REPORT_TYPES: { type: ReportType; label: string; description: string; icon: typeof Wheat }[] = [
  { type: 'production', label: 'Production', description: 'Quantités et rendements agrégés par culture.', icon: TrendingUp },
  { type: 'harvests', label: 'Récoltes', description: 'Liste détaillée des récoltes enregistrées.', icon: Wheat },
  { type: 'sales', label: 'Ventes', description: 'Liste détaillée des ventes et chiffre d\'affaires.', icon: Banknote },
]

const FORMATS: { format: ReportFormat; label: string; icon: typeof FileText }[] = [
  { format: 'pdf', label: 'PDF', icon: FileText },
  { format: 'xlsx', label: 'Excel', icon: FileSpreadsheet },
  { format: 'csv', label: 'CSV', icon: Download },
]

/**
 * Module Rapports (§35) — "Choisir un rapport" -> filtres -> format -> génération. Les 3 formats
 * représentent toujours les mêmes données pour les mêmes filtres (§23, garanti côté backend par
 * ReportService/ReportDataBuilder) : ce composant ne fait que déclencher le téléchargement.
 */
export function ReportsPage() {
  const [type, setType] = useState<ReportType>('sales')
  const [format, setFormat] = useState<ReportFormat>('pdf')
  const [filters, setFilters] = useState<ReportFiltersValue>({})
  const [generating, setGenerating] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  async function handleGenerate() {
    setGenerating(true)
    setError(null)
    try {
      await downloadReport(type, format, filters)
    } catch (err) {
      setError(err as NormalizedError)
    } finally {
      setGenerating(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader title="Rapports" description="Analysez et exportez les données agricoles de votre organisation." />

      <div className="flex flex-col gap-6 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <section className="flex flex-col gap-3">
          <h2 className="text-sm font-semibold text-ink">Choisir un rapport</h2>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {REPORT_TYPES.map((r) => (
              <ReportCard key={r.type} icon={r.icon} label={r.label} description={r.description} selected={type === r.type} onSelect={() => setType(r.type)} />
            ))}
          </div>
        </section>

        <section className="flex flex-col gap-3">
          <h2 className="text-sm font-semibold text-ink">Filtres</h2>
          <ReportFilters filters={filters} onChange={setFilters} />
        </section>

        <section className="flex flex-col gap-3">
          <h2 className="text-sm font-semibold text-ink">Format</h2>
          <div className="flex gap-2">
            {FORMATS.map((f) => (
              <Button key={f.format} type="button" variant={format === f.format ? 'primary' : 'secondary'} onClick={() => setFormat(f.format)}>
                <f.icon size={16} aria-hidden />
                {f.label}
              </Button>
            ))}
          </div>
        </section>

        {error && <Alert>{error.message}</Alert>}

        <div>
          {/* `loading` du Button générique remplacerait tout le libellé par "Chargement…" — le
              cahier des charges (§34) demande précisément "Préparation du rapport…" pendant la
              génération, donc on gère l'état manuellement plutôt que via cette prop. */}
          <Button type="button" onClick={() => void handleGenerate()} disabled={generating}>
            <Download size={16} aria-hidden />
            {generating ? 'Préparation du rapport…' : 'Générer le rapport'}
          </Button>
        </div>
      </div>
    </div>
  )
}

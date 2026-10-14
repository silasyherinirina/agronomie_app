import { useMemo } from 'react'
import { Link2 } from 'lucide-react'
import { useOptions } from '../../hooks/useOptions'
import { cropCyclesApi } from '../cropCycles/api'
import type { CropCycle } from '../../types/api'
import type { ReportFilters as ReportFiltersValue } from './api'

interface ReportFiltersProps {
  filters: ReportFiltersValue
  onChange: (filters: ReportFiltersValue) => void
}

// §17 : deux cycles peuvent partager parcelle+culture (campagnes différentes) — jamais "Cycle
// #12" opaque, toujours de quoi les distinguer (même logique que SaleFormPage::cycleLabel()).
const CYCLE_STATUS_LABEL: Record<CropCycle['status'], string> = { in_progress: 'en cours', harvested: 'récolté', failed: 'échoué' }
function cycleLabel(c: CropCycle): string {
  const base = `${c.plot?.name ?? c.plot_id} — ${c.crop?.name ?? c.crop_id}`
  const campaign = c.campaign ? `${c.campaign.name} (${c.campaign.year})` : null
  const details = [campaign, CYCLE_STATUS_LABEL[c.status]].filter(Boolean).join(', ')
  return details ? `${base} — ${details}` : base
}

interface DerivedFieldProps {
  label: string
  value: string | null
  placeholder: string
  linked: boolean
}

/** Champ dérivé (§4/§16) : toujours désactivé — soit "Toutes/Tous" en mode global, soit la
 * valeur unique du cycle sélectionné, jamais une sélection libre. */
function DerivedField({ label, value, placeholder, linked }: DerivedFieldProps) {
  return (
    <label className="flex flex-col gap-1 text-xs text-muted">
      <span className="flex items-center gap-1">
        {label}
        {linked && <Link2 size={11} aria-hidden className="text-primary" />}
      </span>
      <select aria-label={label} value={value ? '1' : ''} disabled className="min-w-[9rem]">
        <option value="">{placeholder}</option>
        {value && <option value="1">{value}</option>}
      </select>
    </label>
  )
}

/**
 * Restructuration "cycle cultural = relation racine" (§1-§9) : le Cycle cultural / récolte est
 * l'unique filtre relationnel interactif. Toutes les autres dimensions (parcelle, culture,
 * variété, agriculteur, coopérative, campagne) sont dérivées de lui et affichées en lecture
 * seule — jamais des filtres indépendants (§1 : "La Coopérative n'est PLUS le premier filtre").
 * La période reste orthogonale (temporelle), applicable dans les deux modes.
 *
 * Chargement unique (§24) : tous les cycles avec leurs relations complètes (plot.farmer,
 * plot.cooperative, crop, variety, campaign — voir CropCycleController::index()) en un seul
 * appel ; sélectionner un cycle ne déclenche plus aucune requête supplémentaire.
 */
export function ReportFilters({ filters, onChange }: ReportFiltersProps) {
  const { options: cropCycles, loading: loadingCycles } = useOptions(() => cropCyclesApi.list({ per_page: 200 }), [])

  const selectedCycle = useMemo(
    () => cropCycles.find((c) => c.id === filters.crop_cycle_id) ?? null,
    [cropCycles, filters.crop_cycle_id],
  )

  // §18 : valeurs explicites — `undefined` (jamais "all"/"tous"/0) pour le mode global.
  function handleCycleChange(value: string) {
    onChange({ ...filters, crop_cycle_id: value ? Number(value) : undefined })
  }

  const derived = selectedCycle
    ? {
        plotName: selectedCycle.plot?.name ?? null,
        cropName: selectedCycle.crop?.name ?? null,
        varietyName: selectedCycle.variety?.name ?? null,
        farmerName: selectedCycle.plot?.farmer?.full_name ?? null,
        cooperativeName: selectedCycle.plot?.cooperative?.name ?? null,
        campaignLabel: selectedCycle.campaign ? `${selectedCycle.campaign.name} (${selectedCycle.campaign.year})` : null,
      }
    : null

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1 text-xs font-medium text-ink">
          Cycle cultural / récolte
          <select
            aria-label="Cycle cultural / récolte"
            value={filters.crop_cycle_id ?? ''}
            onChange={(e) => handleCycleChange(e.target.value)}
            disabled={loadingCycles}
            className="min-w-[16rem]"
          >
            <option value="">Tous les cycles</option>
            {cropCycles.map((c) => (
              <option key={c.id} value={c.id}>
                {cycleLabel(c)}
              </option>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1 text-xs text-muted">
          Période — début
          <input type="date" aria-label="Période — début" value={filters.start_date ?? ''} onChange={(e) => onChange({ ...filters, start_date: e.target.value || undefined })} />
        </label>
        <label className="flex flex-col gap-1 text-xs text-muted">
          Période — fin
          <input type="date" aria-label="Période — fin" value={filters.end_date ?? ''} onChange={(e) => onChange({ ...filters, end_date: e.target.value || undefined })} />
        </label>
      </div>

      {/* §16 : hiérarchie dérivée du cycle — jamais surchargée, un petit indicateur 🔗 suffit
          à faire comprendre que ces valeurs proviennent automatiquement du cycle sélectionné. */}
      <div className="flex flex-wrap items-end gap-3 rounded-md bg-canvas p-3">
        <DerivedField label="Parcelle" value={derived?.plotName ?? null} placeholder="Toutes" linked={!!derived} />
        <DerivedField label="Culture" value={derived?.cropName ?? null} placeholder="Toutes" linked={!!derived} />
        <DerivedField label="Variété" value={derived?.varietyName ?? null} placeholder="Toutes" linked={!!derived} />
        <DerivedField label="Agriculteur" value={derived?.farmerName ?? null} placeholder="Tous" linked={!!derived} />
        <DerivedField label="Coopérative" value={derived?.cooperativeName ?? null} placeholder="Toutes" linked={!!derived} />
        <DerivedField label="Campagne agricole" value={derived?.campaignLabel ?? null} placeholder="Toutes" linked={!!derived} />
      </div>
    </div>
  )
}

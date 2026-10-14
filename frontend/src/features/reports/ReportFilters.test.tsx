import { useState } from 'react'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ReportFilters } from './ReportFilters'
import type { CropCycle, Plot } from '../../types/api'
import type { ReportFilters as ReportFiltersValue } from './api'

function emptyPage<T>(data: T[] = []) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: null, last_page: 1, per_page: 15, to: null, total: data.length },
  }
}

const cropCyclesListMock = vi.hoisted(() => vi.fn())
vi.mock('../cropCycles/api', () => ({ cropCyclesApi: { list: cropCyclesListMock } }))

// Cas réel audité : Coopérative Démo Bongolava 01, Parcelle Tsiroanomandidy 04, Maïs/Miaritra,
// agriculteur Voahangy Ratsimbazafy.
const PLOT: Plot = {
  id: 33,
  cooperative_id: 59,
  cooperative: { id: 59, name: 'Coopérative Démo Bongolava 01', region: 'Bongolava' } as Plot['cooperative'],
  farmer_id: 19,
  farmer: { id: 19, cooperative_id: 59, first_name: 'Voahangy', last_name: 'Ratsimbazafy', full_name: 'Voahangy Ratsimbazafy', status: 'active' } as Plot['farmer'],
  name: 'Parcelle Tsiroanomandidy 04',
  geometry: null,
  area_ha: null,
  status: 'active',
} as Plot

function makeCycle(id: number, overrides: Partial<CropCycle> = {}): CropCycle {
  return {
    id,
    plot_id: PLOT.id,
    plot: PLOT,
    crop_id: 32,
    crop: { id: 32, name: 'Maïs' } as CropCycle['crop'],
    variety_id: 17,
    variety: { id: 17, name: 'Miaritra' } as CropCycle['variety'],
    campaign_id: 40,
    campaign: { id: 40, name: 'Campagne 2026', year: 2026 } as CropCycle['campaign'],
    status: 'in_progress',
    planting_date: null,
    expected_harvest_date: null,
    created_at: '',
    updated_at: '',
    ...overrides,
  } as CropCycle
}

const CYCLE_A = makeCycle(77)

/** Enveloppe avec état réel : ReportFilters est un composant contrôlé — sans elle, `onChange`
 * ne ferait jamais boucler la nouvelle valeur vers `filters`, comme dans ReportsPage réel. */
function ControlledReportFilters({ onChangeSpy }: { onChangeSpy: (f: ReportFiltersValue) => void }) {
  const [filters, setFilters] = useState<ReportFiltersValue>({})
  return (
    <ReportFilters
      filters={filters}
      onChange={(next) => {
        setFilters(next)
        onChangeSpy(next)
      }}
    />
  )
}

function renderFilters(cycles: CropCycle[]) {
  cropCyclesListMock.mockResolvedValue(emptyPage(cycles))
  const onChange = vi.fn()
  render(<ControlledReportFilters onChangeSpy={onChange} />)
  return { onChange }
}

describe('ReportFilters — le cycle cultural comme relation racine', () => {
  it('TEST 1 — page chargée : cycle disponible, filtres dérivés cohérents avec le mode global initial', async () => {
    renderFilters([CYCLE_A])

    await waitFor(() => expect(screen.getByLabelText('Cycle cultural / récolte')).toBeInTheDocument())
    expect(screen.getByLabelText('Cycle cultural / récolte')).not.toBeDisabled()
    for (const [label, placeholder] of [
      ['Parcelle', 'Toutes'],
      ['Culture', 'Toutes'],
      ['Variété', 'Toutes'],
      ['Agriculteur', 'Tous'],
      ['Coopérative', 'Toutes'],
      ['Campagne agricole', 'Toutes'],
    ] as const) {
      const select = screen.getByLabelText(label) as HTMLSelectElement
      expect(select).toBeDisabled()
      expect(within(select).getByText(placeholder)).toBeInTheDocument()
    }
  })

  it('TEST 2 — sélection du cycle : toutes les dimensions sont automatiquement définies', async () => {
    const user = userEvent.setup()
    renderFilters([CYCLE_A])
    await waitFor(() => expect(screen.getByLabelText('Cycle cultural / récolte')).toBeInTheDocument())

    await user.selectOptions(screen.getByLabelText('Cycle cultural / récolte'), '77')

    expect(within(screen.getByLabelText('Parcelle')).getByText('Parcelle Tsiroanomandidy 04')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Culture')).getByText('Maïs')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Variété')).getByText('Miaritra')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Agriculteur')).getByText('Voahangy Ratsimbazafy')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Coopérative')).getByText('Coopérative Démo Bongolava 01')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Campagne agricole')).getByText('Campagne 2026 (2026)')).toBeInTheDocument()
  })

  it('TEST 3 — changement de cycle : toutes les relations dérivées changent', async () => {
    const user = userEvent.setup()
    const otherPlot = { ...PLOT, id: 40, name: 'Parcelle B', farmer: { ...PLOT.farmer, full_name: 'Autre agriculteur' } } as Plot
    const cycleB = makeCycle(200, { plot_id: 40, plot: otherPlot, crop: { id: 5, name: 'Riz' } as CropCycle['crop'] })
    renderFilters([CYCLE_A, cycleB])
    await waitFor(() => expect(screen.getByLabelText('Cycle cultural / récolte')).toBeInTheDocument())

    await user.selectOptions(screen.getByLabelText('Cycle cultural / récolte'), '77')
    expect(within(screen.getByLabelText('Culture')).getByText('Maïs')).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Cycle cultural / récolte'), '200')

    expect(within(screen.getByLabelText('Culture')).getByText('Riz')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Parcelle')).getByText('Parcelle B')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Agriculteur')).getByText('Autre agriculteur')).toBeInTheDocument()
    expect(screen.queryByText('Maïs')).not.toBeInTheDocument()
  })

  it('TEST 4 — retour à « Tous les cycles » : retour au mode global', async () => {
    const user = userEvent.setup()
    renderFilters([CYCLE_A])
    await waitFor(() => expect(screen.getByLabelText('Cycle cultural / récolte')).toBeInTheDocument())
    await user.selectOptions(screen.getByLabelText('Cycle cultural / récolte'), '77')
    expect(within(screen.getByLabelText('Culture')).getByText('Maïs')).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Cycle cultural / récolte'), '')

    expect(within(screen.getByLabelText('Culture')).getByText('Toutes')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Coopérative')).getByText('Toutes')).toBeInTheDocument()
    expect(screen.queryByText('Maïs')).not.toBeInTheDocument()
  })

  it('TEST 5 — impossible de sélectionner une relation incompatible avec le cycle : les champs dérivés n\'offrent qu\'une seule option', async () => {
    const user = userEvent.setup()
    renderFilters([CYCLE_A])
    await waitFor(() => expect(screen.getByLabelText('Cycle cultural / récolte')).toBeInTheDocument())
    await user.selectOptions(screen.getByLabelText('Cycle cultural / récolte'), '77')

    for (const label of ['Parcelle', 'Culture', 'Variété', 'Agriculteur', 'Coopérative', 'Campagne agricole']) {
      const select = screen.getByLabelText(label) as HTMLSelectElement
      expect(select).toBeDisabled()
      expect(within(select).getAllByRole('option').filter((o) => (o as HTMLOptionElement).value !== '')).toHaveLength(1)
    }
  })
})

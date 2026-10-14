import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { SaleFormPage } from './SaleFormPage'
import * as useAuthModule from '../../hooks/useAuth'
import type { CropCycle, Farmer, Plot, User } from '../../types/api'

vi.mock('../../hooks/useAuth')

function emptyPage<T>(data: T[] = []) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: null, last_page: 1, per_page: 15, to: null, total: data.length },
  }
}

const cropCyclesListMock = vi.hoisted(() => vi.fn())
const salesCreateMock = vi.hoisted(() => vi.fn())

vi.mock('./api', async () => {
  const actual = await vi.importActual<typeof import('./api')>('./api')
  return { ...actual, salesApi: { get: vi.fn(), create: salesCreateMock, update: vi.fn() } }
})
vi.mock('../cooperatives/api', () => ({ cooperativesApi: { list: vi.fn().mockResolvedValue(emptyPage()) } }))
vi.mock('../farmers/api', () => ({ farmersApi: { list: vi.fn().mockResolvedValue(emptyPage()) } }))
vi.mock('../plots/api', () => ({ plotsApi: { list: vi.fn().mockResolvedValue(emptyPage()) } }))
vi.mock('../cropCycles/api', () => ({ cropCyclesApi: { list: cropCyclesListMock } }))
vi.mock('../crops/api', () => ({
  cropsApi: { list: vi.fn().mockResolvedValue(emptyPage()) },
  cropVarietiesApi: { list: vi.fn().mockResolvedValue(emptyPage()) },
}))
vi.mock('../campaigns/api', () => ({ campaignsApi: { list: vi.fn().mockResolvedValue(emptyPage()) } }))

function mockUser(overrides: Partial<User>): User {
  return {
    id: 1,
    name: 'Admin',
    email: 'admin@example.com',
    role: 'admin',
    farmer_id: null,
    managed_cooperative_id: null,
    email_verified_at: '2026-08-31T00:00:00.000000Z',
    created_at: '',
    ...overrides,
  }
}

const authDefaults = {
  loading: false,
  sessionExpired: false,
  clearSessionExpired: vi.fn(),
  login: vi.fn(),
  register: vi.fn(),
  logout: vi.fn(),
  setUser: vi.fn(),
}

// Cas réel audité : Coopérative Démo Bongolava 01 (id=59), Parcelle Tsiroanomandidy 04 (id=33,
// agriculteur Voahangy Ratsimbazafy id=19), culture Maïs, deux cycles distincts (campagnes
// différentes) — voir §22/§24.
const PLOT: Plot = {
  id: 33,
  cooperative_id: 59,
  cooperative: { id: 59, name: 'Coopérative Démo Bongolava 01', region: 'Bongolava' } as Plot['cooperative'],
  farmer_id: 19,
  farmer: { id: 19, cooperative_id: 59, first_name: 'Voahangy', last_name: 'Ratsimbazafy', full_name: 'Voahangy Ratsimbazafy', status: 'active' } as Farmer,
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

const CYCLE_COMPLETE = makeCycle(77)
const CYCLE_PAST = makeCycle(76, { campaign_id: 31, campaign: { id: 31, name: 'Campagne 2020-2021', year: 2020 } as CropCycle['campaign'], status: 'harvested' })
const CYCLE_NO_VARIETY = makeCycle(90, { variety_id: null, variety: null })

function renderNewSaleForm() {
  return render(
    <MemoryRouter initialEntries={['/sales/new']}>
      <Routes>
        <Route path="/sales/new" element={<SaleFormPage />} />
      </Routes>
    </MemoryRouter>,
  )
}

function renderWithCycles(cycles: CropCycle[]) {
  cropCyclesListMock.mockResolvedValue(emptyPage(cycles))
  vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })
  return renderNewSaleForm()
}

describe('SaleFormPage — le cycle cultural comme relation racine', () => {
  it('TEST 1 — au chargement : le cycle est actif, tous les champs dérivés sont désactivés', async () => {
    renderWithCycles([CYCLE_COMPLETE])

    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())
    expect(screen.getByLabelText(/^Cycle cultural/)).not.toBeDisabled()
    expect(screen.getByLabelText('Parcelle')).toBeDisabled()
    expect(screen.getByLabelText('Culture')).toBeDisabled()
    expect(screen.getByLabelText('Variété')).toBeDisabled()
    expect(screen.getByLabelText('Agriculteur')).toBeDisabled()
    expect(screen.getByLabelText('Coopérative')).toBeDisabled()
    expect(screen.getByLabelText('Campagne agricole')).toBeDisabled()
    expect(screen.getByText("Sélectionnez d'abord un cycle cultural / une récolte.")).toBeInTheDocument()
  })

  it('TEST 2/6/22 — sélectionner le cycle Bongolava/Tsiroanomandidy remplit automatiquement toute la chaîne', async () => {
    const user = userEvent.setup()
    renderWithCycles([CYCLE_COMPLETE])
    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())

    await user.selectOptions(screen.getByLabelText(/^Cycle cultural/), '77')

    expect(within(screen.getByLabelText('Parcelle')).getByText('Parcelle Tsiroanomandidy 04')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Culture')).getByText('Maïs')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Variété')).getByText('Miaritra')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Agriculteur')).getByText('Voahangy Ratsimbazafy')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Coopérative')).getByText('Coopérative Démo Bongolava 01')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Campagne agricole')).getByText('Campagne 2026 (2026)')).toBeInTheDocument()
  })

  it('TEST 24 — deux cycles réels partageant parcelle/culture restent des options distinctes (jamais un doublon supprimé)', async () => {
    renderWithCycles([CYCLE_PAST, CYCLE_COMPLETE])
    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())

    const options = within(screen.getByLabelText(/^Cycle cultural/))
      .getAllByRole('option')
      .map((o) => o.textContent)

    expect(options).toContain('Parcelle Tsiroanomandidy 04 — Maïs — Campagne 2020-2021 (2020), récolté')
    expect(options).toContain('Parcelle Tsiroanomandidy 04 — Maïs — Campagne 2026 (2026), en cours')
  })

  it('TEST 3 — changer de cycle remplace toutes les relations dérivées, sans rien conserver de l\'ancien', async () => {
    const user = userEvent.setup()
    const otherPlot = { ...PLOT, id: 40, name: 'Parcelle B', farmer: { ...PLOT.farmer, full_name: 'Autre agriculteur' } } as Plot
    const cycleB = makeCycle(200, {
      plot_id: 40,
      plot: otherPlot,
      crop: { id: 5, name: 'Riz' } as CropCycle['crop'],
      variety: { id: 9, name: 'X265' } as CropCycle['variety'],
    })
    renderWithCycles([CYCLE_COMPLETE, cycleB])
    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())

    await user.selectOptions(screen.getByLabelText(/^Cycle cultural/), '77')
    expect(within(screen.getByLabelText('Culture')).getByText('Maïs')).toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText(/^Cycle cultural/), '200')

    expect(within(screen.getByLabelText('Culture')).getByText('Riz')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Variété')).getByText('X265')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Parcelle')).getByText('Parcelle B')).toBeInTheDocument()
    expect(within(screen.getByLabelText('Agriculteur')).getByText('Autre agriculteur')).toBeInTheDocument()
    expect(screen.queryByText('Maïs')).not.toBeInTheDocument()
    expect(screen.queryByText('Miaritra')).not.toBeInTheDocument()
  })

  it('TEST 4 — aucun cycle sélectionné : toutes les relations dérivées restent désactivées', async () => {
    renderWithCycles([CYCLE_COMPLETE])
    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())

    expect(screen.getByLabelText('Parcelle')).toBeDisabled()
    expect(screen.getByLabelText('Coopérative')).toBeDisabled()
    expect(screen.getByLabelText('Campagne agricole')).toBeDisabled()
  })

  it('TEST 5 — un cycle sans variété affiche « Non spécifiée » (jamais un faux identifiant)', async () => {
    const user = userEvent.setup()
    renderWithCycles([CYCLE_NO_VARIETY])
    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())

    await user.selectOptions(screen.getByLabelText(/^Cycle cultural/), '90')

    expect(within(screen.getByLabelText('Variété')).getByText('Non spécifiée')).toBeInTheDocument()
  })

  it("TEST 7/8/9 — impossible de choisir coopérative/parcelle/agriculteur incompatibles : les champs dérivés n'offrent qu'une seule option", async () => {
    const user = userEvent.setup()
    renderWithCycles([CYCLE_COMPLETE])
    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())
    await user.selectOptions(screen.getByLabelText(/^Cycle cultural/), '77')

    for (const label of ['Parcelle', 'Agriculteur', 'Coopérative']) {
      const select = screen.getByLabelText(label) as HTMLSelectElement
      expect(select).toBeDisabled()
      // Une seule valeur possible (+ l'option vide inutilisée) : aucune alternative sélectionnable.
      expect(within(select).getAllByRole('option').filter((o) => (o as HTMLOptionElement).value !== '')).toHaveLength(1)
    }
  })

  it('TEST 10 — la soumission envoie un payload cohérent avec le cycle sélectionné', async () => {
    const user = userEvent.setup()
    salesCreateMock.mockResolvedValue({ id: 1 })
    renderWithCycles([CYCLE_COMPLETE])
    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())

    await user.selectOptions(screen.getByLabelText(/^Cycle cultural/), '77')
    await user.type(screen.getByLabelText(/^Date de vente/), '2026-09-11')
    await user.clear(screen.getByLabelText(/^Quantité/))
    await user.type(screen.getByLabelText(/^Quantité/), '500')
    await user.clear(screen.getByLabelText(/^Prix unitaire/))
    await user.type(screen.getByLabelText(/^Prix unitaire/), '2000')

    await user.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => expect(salesCreateMock).toHaveBeenCalled())
    const payload = salesCreateMock.mock.calls[0][0]
    expect(payload.crop_cycle_id).toBe(77)
    expect(payload.plot_id).toBe(33)
    expect(payload.crop_id).toBe(32)
    expect(payload.variety_id).toBe(17)
    expect(payload.farmer_id).toBe(19)
    expect(payload.cooperative_id).toBe(59)
    expect(payload.campaign_id).toBe(40)
  })

  it('§14 — le mode "vente agrégée" reste disponible comme cas métier séparé, jamais une fausse option du menu Cycle', async () => {
    const user = userEvent.setup()
    renderWithCycles([CYCLE_COMPLETE])
    await waitFor(() => expect(screen.getByLabelText(/^Cycle cultural/)).toBeInTheDocument())

    const cycleOptionLabels = within(screen.getByLabelText(/^Cycle cultural/))
      .getAllByRole('option')
      .map((o) => o.textContent)
    // Pas de fausse option "Aucun (vente agrégée)" mélangée aux vrais cycles (§14).
    expect(cycleOptionLabels.some((t) => t?.toLowerCase().includes('agrégée'))).toBe(false)

    await user.click(screen.getByRole('button', { name: /vente agrégée/i }))

    expect(screen.getByLabelText(/^Coopérative/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Coopérative/)).not.toBeDisabled()
    expect(screen.queryByLabelText(/^Cycle cultural/)).not.toBeInTheDocument()
  })
})

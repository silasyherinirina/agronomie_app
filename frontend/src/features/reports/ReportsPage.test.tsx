import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ReportsPage } from './ReportsPage'
import * as useAuthModule from '../../hooks/useAuth'
import type { CropCycle, Plot, User } from '../../types/api'

vi.mock('../../hooks/useAuth')

const downloadReportMock = vi.hoisted(() => vi.fn())
vi.mock('./api', async () => {
  const actual = await vi.importActual<typeof import('./api')>('./api')
  return { ...actual, downloadReport: downloadReportMock }
})

function emptyPage<T>(data: T[] = []) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: null, last_page: 1, per_page: 15, to: null, total: data.length },
  }
}

// Restructuration "cycle cultural = relation racine" : ReportFilters ne consulte plus que
// l'endpoint des cycles culturaux (toutes les autres dimensions en sont dérivées, voir
// ReportFilters.tsx) — plus besoin de mocker cooperatives/farmers/plots/crops/campaigns ici.
const cropCyclesListMock = vi.hoisted(() => vi.fn())
vi.mock('../cropCycles/api', () => ({ cropCyclesApi: { list: cropCyclesListMock } }))

const PLOT: Plot = { id: 33, cooperative_id: 59, name: 'Parcelle Tsiroanomandidy 04', farmer_id: 19, area_ha: null, status: 'active' } as Plot
const CYCLE: CropCycle = {
  id: 77,
  plot_id: 33,
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
} as CropCycle

function mockUser(overrides: Partial<User>): User {
  return {
    id: 1,
    name: 'Test',
    email: 't@example.com',
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

describe('ReportsPage', () => {
  beforeEach(() => {
    cropCyclesListMock.mockResolvedValue(emptyPage())
  })

  it('defaults to the sales report in PDF and lets the user switch report type and format', async () => {
    const user = userEvent.setup()
    downloadReportMock.mockResolvedValue(undefined)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    render(<ReportsPage />)

    await user.click(screen.getByRole('button', { name: /Récoltes/i }))
    await user.click(screen.getByRole('button', { name: 'Excel' }))
    await user.click(screen.getByRole('button', { name: /Générer le rapport/i }))

    await waitFor(() => expect(downloadReportMock).toHaveBeenCalledWith('harvests', 'xlsx', {}))
  })

  it('shows a clear error message instead of a raw technical error when generation fails', async () => {
    const user = userEvent.setup()
    downloadReportMock.mockRejectedValue({ status: 503, message: "Le service de génération de rapports est indisponible." })
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    render(<ReportsPage />)
    await user.click(screen.getByRole('button', { name: /Générer le rapport/i }))

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/indisponible/i))
  })

  /*
   * ================================================================================
   * Restructuration "cycle cultural = relation racine" (§25, TEST 6-9).
   * ================================================================================
   */

  async function selectCycleAndFormat(user: ReturnType<typeof userEvent.setup>, format: 'PDF' | 'Excel' | 'CSV') {
    render(<ReportsPage />)
    await waitFor(() => expect(screen.getByLabelText('Cycle cultural / récolte')).toBeInTheDocument())
    await user.selectOptions(screen.getByLabelText('Cycle cultural / récolte'), '77')
    if (format !== 'PDF') {
      await user.click(screen.getByRole('button', { name: format }))
    }
    await user.click(screen.getByRole('button', { name: /Générer le rapport/i }))
    await waitFor(() => expect(downloadReportMock).toHaveBeenCalled())
  }

  it('TEST 6 — génération PDF avec un cycle spécifique', async () => {
    const user = userEvent.setup()
    cropCyclesListMock.mockResolvedValue(emptyPage([CYCLE]))
    downloadReportMock.mockResolvedValue(undefined)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    await selectCycleAndFormat(user, 'PDF')

    expect(downloadReportMock).toHaveBeenCalledWith('sales', 'pdf', { crop_cycle_id: 77 })
  })

  it('TEST 7 — génération Excel avec un cycle spécifique', async () => {
    const user = userEvent.setup()
    cropCyclesListMock.mockResolvedValue(emptyPage([CYCLE]))
    downloadReportMock.mockResolvedValue(undefined)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    await selectCycleAndFormat(user, 'Excel')

    expect(downloadReportMock).toHaveBeenCalledWith('sales', 'xlsx', { crop_cycle_id: 77 })
  })

  it('TEST 8 — génération CSV avec un cycle spécifique', async () => {
    const user = userEvent.setup()
    cropCyclesListMock.mockResolvedValue(emptyPage([CYCLE]))
    downloadReportMock.mockResolvedValue(undefined)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    await selectCycleAndFormat(user, 'CSV')

    expect(downloadReportMock).toHaveBeenCalledWith('sales', 'csv', { crop_cycle_id: 77 })
  })

  it('TEST 9 — les trois formats reçoivent exactement le même périmètre de filtres pour le même cycle', async () => {
    const user = userEvent.setup()
    cropCyclesListMock.mockResolvedValue(emptyPage([CYCLE]))
    downloadReportMock.mockResolvedValue(undefined)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    render(<ReportsPage />)
    await waitFor(() => expect(screen.getByLabelText('Cycle cultural / récolte')).toBeInTheDocument())
    await user.selectOptions(screen.getByLabelText('Cycle cultural / récolte'), '77')

    const buttonNames: Record<'PDF' | 'Excel' | 'CSV', string> = { PDF: 'PDF', Excel: 'Excel', CSV: 'CSV' }
    for (const format of ['PDF', 'Excel', 'CSV'] as const) {
      downloadReportMock.mockClear()
      await user.click(screen.getByRole('button', { name: buttonNames[format] }))
      await user.click(screen.getByRole('button', { name: /Générer le rapport/i }))
      await waitFor(() => expect(downloadReportMock).toHaveBeenCalled())

      const [, , filters] = downloadReportMock.mock.calls[0]
      expect(filters).toEqual({ crop_cycle_id: 77 })
    }
  })
})

import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { SalesListPage } from './SalesListPage'
import * as useAuthModule from '../../hooks/useAuth'
import type { Sale, SaleStatistics, User } from '../../types/api'

vi.mock('../../hooks/useAuth')

function emptyPage<T>(data: T[] = []) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: null, last_page: 1, per_page: 15, to: null, total: data.length },
  }
}

const salesApiMocks = vi.hoisted(() => ({
  list: vi.fn(),
  get: vi.fn(),
  create: vi.fn(),
  update: vi.fn(),
  remove: vi.fn(),
}))
const getSaleStatisticsMock = vi.hoisted(() => vi.fn())

vi.mock('./api', () => ({
  salesApi: salesApiMocks,
  getSaleStatistics: getSaleStatisticsMock,
}))

vi.mock('../cooperatives/api', () => ({ cooperativesApi: { list: vi.fn().mockResolvedValue(emptyPage()) } }))
vi.mock('../farmers/api', () => ({ farmersApi: { list: vi.fn().mockResolvedValue(emptyPage()) } }))
vi.mock('../crops/api', () => ({ cropsApi: { list: vi.fn().mockResolvedValue(emptyPage()) } }))
vi.mock('../campaigns/api', () => ({ campaignsApi: { list: vi.fn().mockResolvedValue(emptyPage()) } }))

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

function makeSale(overrides: Partial<Sale> = {}): Sale {
  return {
    id: 1,
    cooperative_id: 1,
    cooperative: { id: 1, name: 'Coopérative MIAVOTRA' } as Sale['cooperative'],
    farmer_id: null,
    farmer: null,
    plot_id: null,
    plot: null,
    crop_id: 1,
    crop: { id: 1, name: 'Riz' } as Sale['crop'],
    variety_id: null,
    variety: null,
    crop_cycle_id: null,
    campaign_id: null,
    campaign: null,
    buyer_name: 'Grossiste Andraharo',
    buyer_contact: null,
    sale_date: '2026-09-08',
    quantity: 1500,
    unit: 'kg',
    unit_price: 2500,
    total_amount: 3_750_000,
    currency: 'MGA',
    observations: null,
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

const statistics: SaleStatistics = {
  sales_count: 1,
  total_amount: 3_750_000,
  average_unit_price: 2500,
  quantity_by_unit: [{ unit: 'kg', total_quantity: 1500, sales_count: 1 }],
  amount_by_crop: [{ crop_id: 1, crop_name: 'Riz', total_amount: 3_750_000 }],
}

function renderPage() {
  return render(
    <MemoryRouter>
      <SalesListPage />
    </MemoryRouter>,
  )
}

describe('SalesListPage', () => {
  it('shows a loading state before the sales list resolves', () => {
    salesApiMocks.list.mockReturnValue(new Promise(() => {}))
    getSaleStatisticsMock.mockReturnValue(new Promise(() => {}))
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    renderPage()

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('renders sales rows with the server-computed total amount and KPI summary', async () => {
    salesApiMocks.list.mockResolvedValue(emptyPage([makeSale()]))
    getSaleStatisticsMock.mockResolvedValue(statistics)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    renderPage()

    await waitFor(() => expect(screen.getByText('Grossiste Andraharo')).toBeInTheDocument())
    // Le montant apparaît deux fois : dans le KPI "Chiffre d'affaires" et dans la ligne du
    // tableau — les deux doivent afficher exactement le total calculé par le serveur.
    await waitFor(() => expect(screen.getAllByText('3 750 000 MGA').length).toBe(2))
  })

  it('shows the API error message when the sales list fails to load', async () => {
    salesApiMocks.list.mockRejectedValue({ isAxiosError: true, response: { status: 500, data: {} } })
    getSaleStatisticsMock.mockResolvedValue(statistics)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })

    renderPage()

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument())
  })

  it('hides the "Nouvelle vente" action from a farmer', async () => {
    salesApiMocks.list.mockResolvedValue(emptyPage())
    getSaleStatisticsMock.mockResolvedValue({ ...statistics, sales_count: 0, total_amount: 0, quantity_by_unit: [], amount_by_crop: [] })
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'farmer', farmer_id: 1 }) })

    renderPage()

    await waitFor(() => expect(screen.getByText('Aucune vente enregistrée.')).toBeInTheDocument())
    expect(screen.queryByRole('link', { name: /Nouvelle vente/i })).not.toBeInTheDocument()
  })
})

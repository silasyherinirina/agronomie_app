import { useEffect } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import L from 'leaflet'
import { DashboardPage } from './DashboardPage'
import * as useAuthModule from '../../hooks/useAuth'
import type { AgriculturalCampaign, DashboardSummary, GeoPolygon, Plot, ProductionPrediction, User } from '../../types/api'

vi.mock('../../hooks/useAuth')

// Carte Leaflet : pas de vrai DOM tile-layer en jsdom, donc pas la vraie carte, mais un objet
// "prêt" minimal (voir le garde `_mapPane` dans DashboardPage) exposant un `fitBounds` espionné
// — c'est précisément la donnée transmise au composant Map que le §30 demande de vérifier à
// défaut de pouvoir tester Leaflet lui-même. `addLayer` en no-op : la vraie API Leaflet
// (`L.layerGroup()`, `L.polygon()`) reste utilisée telle quelle, seule la classe `Map` réelle
// est remplacée.
const fitBoundsMock = vi.hoisted(() => vi.fn())

vi.mock('../../components/LeafletMap', () => ({
  LeafletMap: ({ onReady }: { onReady: (map: unknown) => void }) => {
    useEffect(() => {
      onReady({ _mapPane: {}, fitBounds: fitBoundsMock, addLayer: vi.fn() })
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])
    return null
  },
}))

function emptyPage<T>(data: T[] = []) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: null, last_page: 1, per_page: 15, to: null, total: data.length },
  }
}

const dashboardApiMocks = vi.hoisted(() => ({ getDashboardSummary: vi.fn() }))
vi.mock('./api', () => dashboardApiMocks)

const cooperativesApiMocks = vi.hoisted(() => ({ cooperativesApi: { list: vi.fn() } }))
vi.mock('../cooperatives/api', () => cooperativesApiMocks)

const campaignsApiMocks = vi.hoisted(() => ({ campaignsApi: { list: vi.fn() } }))
vi.mock('../campaigns/api', () => campaignsApiMocks)

const plotsApiMocks = vi.hoisted(() => ({ plotsApi: { list: vi.fn() }, getPlotCampaigns: vi.fn() }))
vi.mock('../plots/api', () => plotsApiMocks)

const predictionsApiMocks = vi.hoisted(() => ({ predictionsApi: { list: vi.fn() } }))
vi.mock('../predictions/api', () => predictionsApiMocks)

const weatherApiMocks = vi.hoisted(() => ({ getPlotWeather: vi.fn() }))
vi.mock('../weather/api', () => weatherApiMocks)

function mockUser(overrides: Partial<User> = {}): User {
  return {
    id: 1,
    name: 'Admin Test',
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

function makeSummary(overrides: Partial<DashboardSummary> = {}): DashboardSummary {
  return {
    filters: { cooperative_id: null, farmer_id: null, campaign_id: null, plot_id: null },
    kpis: { cooperatives_count: 1, farmers_count: 1, plots_count: 1, total_area_ha: 1, campaigns_count: 1 },
    harvest_totals_by_unit: [],
    average_yield_by_unit: [],
    production_by_crop: [],
    yield_by_crop: [],
    production_by_campaign: [],
    input_consumption: [],
    geography: [],
    recent_activity: [],
    alerts: [],
    sales: { sales_count: 0, total_amount: 0, average_unit_price: 0, quantity_by_unit: [], amount_by_crop: [] },
    sales_by_campaign: [],
    recent_sales: [],
    ...overrides,
  }
}

function makePlot(overrides: Partial<Plot> = {}): Plot {
  return {
    id: 10,
    cooperative_id: 59,
    cooperative: { id: 59, name: 'Coopérative Démo Bongolava 01' } as Plot['cooperative'],
    farmer_id: null,
    farmer: null,
    name: 'Parcelle Tsiroanomandidy 04',
    geometry: null,
    area_ha: 2,
    status: 'active',
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

function makeCampaign(overrides: Partial<AgriculturalCampaign> = {}): AgriculturalCampaign {
  return {
    id: 6,
    name: 'Campagne 2026',
    year: 2026,
    season: null,
    start_date: '2026-04-01',
    end_date: null,
    status: 'active',
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

function makePrediction(overrides: Partial<ProductionPrediction> = {}): ProductionPrediction {
  return {
    id: 1,
    plot_id: 10,
    plot: { id: 10, name: 'Parcelle Tsiroanomandidy 04' } as Plot,
    crop_id: 1,
    crop: { id: 1, name: 'Riz' } as ProductionPrediction['crop'],
    campaign_id: null,
    campaign: undefined,
    predicted_yield: 1996.8,
    unit: 'kg/ha',
    input_features: {
      area_ha: 1,
      fertilizer_kg_per_ha: 50,
      rainfall_mm: 1200,
      planting_month: 11,
      variety_class: 'traditionnelle',
    },
    feature_importances: null,
    is_experimental: true,
    model: { id: 'm1', name: 'linear_regression', algorithm: 'linear_regression', version: '1.0.0', trained_at: null, metrics: { mae: 1, rmse: 1, r2: 0.5 } },
    predicted_at: '2026-09-11T13:59:38.000000Z',
    created_at: '2026-09-11T13:59:38.000000Z',
    ...overrides,
  }
}

function renderDashboard() {
  return render(
    <MemoryRouter initialEntries={['/dashboard']}>
      <DashboardPage />
    </MemoryRouter>,
  )
}

describe('DashboardPage — filtre Parcelle', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser() })
    dashboardApiMocks.getDashboardSummary.mockResolvedValue(makeSummary())
    cooperativesApiMocks.cooperativesApi.list.mockResolvedValue(emptyPage([{ id: 1, name: 'Autre coopérative' }]))
    campaignsApiMocks.campaignsApi.list.mockResolvedValue(emptyPage([{ id: 1, name: 'Autre campagne', year: 2024 }]))
    plotsApiMocks.plotsApi.list.mockResolvedValue(emptyPage([makePlot()]))
    predictionsApiMocks.predictionsApi.list.mockResolvedValue(emptyPage())
    weatherApiMocks.getPlotWeather.mockResolvedValue(null)
  })

  it('starts in global mode: "Toutes les parcelles" with the full cooperative/campaign lists', async () => {
    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    expect(plotSelect).toHaveValue('')

    const coopSelect = screen.getByRole('combobox', { name: 'Filtrer par coopérative' })
    expect(coopSelect).toHaveDisplayValue('Toutes les coopératives')
    await waitFor(() => expect(screen.getByText('Autre coopérative')).toBeInTheDocument())
  })

  it('selecting a plot derives its real cooperative and narrows the campaign list', async () => {
    const user = userEvent.setup()
    plotsApiMocks.getPlotCampaigns.mockResolvedValue([makeCampaign()])
    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '10')

    // Coopérative dérivée automatiquement (relation réelle plot.cooperative_id) — jamais une
    // autre coopérative de la liste globale.
    const coopSelect = screen.getByRole('combobox', { name: 'Filtrer par coopérative' })
    await waitFor(() => expect(coopSelect).toHaveValue('59'))
    expect(screen.getByText('Coopérative Démo Bongolava 01')).toBeInTheDocument()
    expect(screen.queryByText('Autre coopérative')).not.toBeInTheDocument()

    // Campagne : uniquement celles réellement associées à la parcelle (endpoint dédié).
    await waitFor(() => expect(plotsApiMocks.getPlotCampaigns).toHaveBeenCalledWith(10))
    await waitFor(() => expect(screen.getByText('Campagne 2026 (2026)')).toBeInTheDocument())
    expect(screen.queryByText('Autre campagne (2024)')).not.toBeInTheDocument()

    await waitFor(() =>
      expect(dashboardApiMocks.getDashboardSummary).toHaveBeenLastCalledWith(
        expect.objectContaining({ plot_id: 10, cooperative_id: 59 }),
      ),
    )
  })

  it('switching from one plot to another immediately recomputes cooperative and campaigns', async () => {
    const user = userEvent.setup()
    const otherPlot = makePlot({
      id: 20,
      name: 'Parcelle Antsirabe 02',
      cooperative_id: 61,
      cooperative: { id: 61, name: 'Coopérative Démo Vakinankaratra 01' } as Plot['cooperative'],
    })
    plotsApiMocks.plotsApi.list.mockResolvedValue(emptyPage([makePlot(), otherPlot]))
    plotsApiMocks.getPlotCampaigns
      .mockResolvedValueOnce([makeCampaign()])
      .mockResolvedValueOnce([makeCampaign({ id: 7, name: 'Campagne 2027', year: 2027 })])
    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '10')
    await waitFor(() => expect(screen.getByText('Campagne 2026 (2026)')).toBeInTheDocument())

    await user.selectOptions(plotSelect, '20')

    const coopSelect = screen.getByRole('combobox', { name: 'Filtrer par coopérative' })
    await waitFor(() => expect(coopSelect).toHaveValue('61'))
    expect(screen.getByText('Coopérative Démo Vakinankaratra 01')).toBeInTheDocument()
    await waitFor(() => expect(screen.getByText('Campagne 2027 (2027)')).toBeInTheDocument())
    // L'ancienne campagne (liée à la parcelle précédente) ne doit plus apparaître.
    expect(screen.queryByText('Campagne 2026 (2026)')).not.toBeInTheDocument()
  })

  it('shows a disabled "no campaign" state when the plot has none', async () => {
    const user = userEvent.setup()
    plotsApiMocks.getPlotCampaigns.mockResolvedValue([])
    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '10')

    const campaignSelect = screen.getByRole('combobox', { name: 'Filtrer par campagne' })
    await waitFor(() => expect(campaignSelect).toBeDisabled())
    expect(screen.getByText('Aucune campagne associée')).toBeInTheDocument()
  })

  it('shows an error state with a retry action when loading plot relations fails', async () => {
    const user = userEvent.setup()
    plotsApiMocks.getPlotCampaigns.mockRejectedValueOnce(new Error('network')).mockResolvedValueOnce([makeCampaign()])
    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '10')

    await screen.findByText('Impossible de charger les relations de cette parcelle.')
    await user.click(screen.getByRole('button', { name: 'Réessayer' }))

    await waitFor(() => expect(screen.getByText('Campagne 2026 (2026)')).toBeInTheDocument())
  })

  it('returning to "Toutes les parcelles" restores the free global filters', async () => {
    const user = userEvent.setup()
    plotsApiMocks.getPlotCampaigns.mockResolvedValue([makeCampaign()])
    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '10')
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Filtrer par coopérative' })).toHaveValue('59'))

    await user.selectOptions(plotSelect, '')

    const coopSelect = screen.getByRole('combobox', { name: 'Filtrer par coopérative' })
    expect(coopSelect).toHaveValue('')
    await waitFor(() => expect(screen.getByText('Autre coopérative')).toBeInTheDocument())
    expect(screen.getByText('Autre campagne (2024)')).toBeInTheDocument()
  })

  // --- Carte "Aperçu des parcelles" : la Parcelle seule doit déclencher la carte -----------

  function squareAround(lng: number, lat: number, delta = 0.01): GeoPolygon {
    return {
      type: 'Polygon',
      coordinates: [
        [
          [lng, lat],
          [lng + delta, lat],
          [lng + delta, lat + delta],
          [lng, lat + delta],
          [lng, lat],
        ],
      ],
    }
  }

  it('fits the map to the selected plot only, without requiring a campaign', async () => {
    const user = userEvent.setup()
    const nearbyPlot = makePlot({ id: 10, geometry: squareAround(47.5, -18.88) })
    // Même coopérative que `nearbyPlot`, mais géographiquement très éloignée : reproduit
    // exactement le bug (la carte fitBounds-ait sur TOUTE la coopérative dérivée, jamais
    // seulement sur la parcelle réellement sélectionnée).
    const farAwayPlot = makePlot({ id: 11, name: 'Parcelle lointaine', geometry: squareAround(49.5, -20.5) })
    plotsApiMocks.plotsApi.list.mockResolvedValue(emptyPage([nearbyPlot, farAwayPlot]))

    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '10')

    await waitFor(() => expect(fitBoundsMock).toHaveBeenCalled())

    // Aucune campagne sélectionnée à aucun moment ici : la carte doit pourtant déjà réagir.
    const bounds = fitBoundsMock.mock.calls.at(-1)?.[0] as L.LatLngBounds
    expect(bounds.contains(L.latLng(-18.875, 47.505))).toBe(true)
    expect(bounds.contains(L.latLng(-20.495, 49.505))).toBe(false)
  })

  it('immediately refits the map when switching from one plot to another', async () => {
    const user = userEvent.setup()
    const plotA = makePlot({ id: 10, geometry: squareAround(47.5, -18.88) })
    const plotB = makePlot({ id: 20, name: 'Parcelle Antsirabe 02', geometry: squareAround(47.03, -19.87) })
    plotsApiMocks.plotsApi.list.mockResolvedValue(emptyPage([plotA, plotB]))

    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '10')
    await waitFor(() => expect(fitBoundsMock).toHaveBeenCalled())

    await user.selectOptions(plotSelect, '20')

    await waitFor(() => {
      const bounds = fitBoundsMock.mock.calls.at(-1)?.[0] as L.LatLngBounds
      expect(bounds.contains(L.latLng(-19.865, 47.035))).toBe(true)
      expect(bounds.contains(L.latLng(-18.875, 47.505))).toBe(false)
    })
  })

  it('shows a message instead of guessing a location when the selected plot has no geometry', async () => {
    const user = userEvent.setup()
    plotsApiMocks.plotsApi.list.mockResolvedValue(emptyPage([makePlot({ geometry: null })]))
    renderDashboard()

    const plotSelect = await screen.findByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '10')

    expect(await screen.findByText('Localisation indisponible pour cette parcelle.')).toBeInTheDocument()
    expect(screen.queryByText('Aucune parcelle géolocalisée pour le moment.')).not.toBeInTheDocument()
  })
})

describe('DashboardPage — carte Prévision IA liée au filtre Parcelle', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser() })
    dashboardApiMocks.getDashboardSummary.mockResolvedValue(makeSummary())
    cooperativesApiMocks.cooperativesApi.list.mockResolvedValue(emptyPage())
    campaignsApiMocks.campaignsApi.list.mockResolvedValue(emptyPage())
    plotsApiMocks.plotsApi.list.mockResolvedValue(
      emptyPage([makePlot(), makePlot({ id: 20, name: 'Parcelle Antsirabe 02', cooperative_id: 61 })]),
    )
    plotsApiMocks.getPlotCampaigns.mockResolvedValue([])
    predictionsApiMocks.predictionsApi.list.mockResolvedValue(emptyPage())
    weatherApiMocks.getPlotWeather.mockResolvedValue(null)
  })

  it('shows the latest global prediction while "Toutes les parcelles" is selected', async () => {
    predictionsApiMocks.predictionsApi.list.mockResolvedValue(emptyPage([makePrediction()]))
    renderDashboard()

    expect(await screen.findByText('1 996,8 kg/ha')).toBeInTheDocument()
    expect(predictionsApiMocks.predictionsApi.list).toHaveBeenCalledWith(
      expect.objectContaining({ plot_id: undefined }),
    )
  })

  it('shows only the selected plot\'s latest prediction, never the global one', async () => {
    const user = userEvent.setup()
    predictionsApiMocks.predictionsApi.list
      .mockResolvedValueOnce(emptyPage([makePrediction()])) // chargement initial (global)
      .mockResolvedValueOnce(
        emptyPage([makePrediction({ id: 2, plot_id: 20, predicted_yield: 2325.8, plot: { id: 20, name: 'Parcelle Antsirabe 02' } as Plot })]),
      )
    renderDashboard()
    await screen.findByText('1 996,8 kg/ha')

    const plotSelect = screen.getByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '20')

    await waitFor(() =>
      expect(predictionsApiMocks.predictionsApi.list).toHaveBeenLastCalledWith(
        expect.objectContaining({ plot_id: 20 }),
      ),
    )
    expect(await screen.findByText('2 325,8 kg/ha')).toBeInTheDocument()
    expect(screen.queryByText('1 996,8 kg/ha')).not.toBeInTheDocument()
  })

  it('shows a plot-specific empty state, with no fallback to another plot\'s prediction', async () => {
    const user = userEvent.setup()
    predictionsApiMocks.predictionsApi.list
      .mockResolvedValueOnce(emptyPage([makePrediction()])) // global : une prédiction existe
      .mockResolvedValueOnce(emptyPage()) // la parcelle choisie n'en a aucune
    renderDashboard()
    await screen.findByText('1 996,8 kg/ha')

    const plotSelect = screen.getByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '20')

    expect(await screen.findByText("Aucune prédiction IA n'a encore été réalisée pour Parcelle Antsirabe 02.")).toBeInTheDocument()
    expect(screen.queryByText('1 996,8 kg/ha')).not.toBeInTheDocument()
  })

  it('never lets a stale response from a previous plot overwrite the latest selection', async () => {
    const user = userEvent.setup()
    let resolveFirst!: (value: ReturnType<typeof emptyPage<ProductionPrediction>>) => void
    predictionsApiMocks.predictionsApi.list
      .mockResolvedValueOnce(emptyPage()) // chargement initial (global, vide)
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            resolveFirst = resolve
          }),
      )
      .mockResolvedValueOnce(emptyPage([makePrediction({ id: 2, plot_id: 20, predicted_yield: 2325.8 })]))
    renderDashboard()
    await waitFor(() => expect(predictionsApiMocks.predictionsApi.list).toHaveBeenCalledTimes(1))

    const plotSelect = screen.getByRole('combobox', { name: 'Filtrer par parcelle' })
    // Sélection rapide A puis B avant que la réponse de A n'arrive.
    await user.selectOptions(plotSelect, '10')
    await user.selectOptions(plotSelect, '20')

    await waitFor(() => expect(screen.getByText('2 325,8 kg/ha')).toBeInTheDocument())

    // La réponse tardive de la sélection précédente (A) arrive ensuite : elle ne doit rien changer.
    resolveFirst(emptyPage([makePrediction({ id: 1, plot_id: 10, predicted_yield: 1996.8 })]))
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(screen.getByText('2 325,8 kg/ha')).toBeInTheDocument()
    expect(screen.queryByText('1 996,8 kg/ha')).not.toBeInTheDocument()
  })

  it('shows a loading skeleton instead of stale data while switching plots', async () => {
    const user = userEvent.setup()
    predictionsApiMocks.predictionsApi.list.mockResolvedValueOnce(emptyPage([makePrediction()])).mockReturnValueOnce(new Promise(() => {}))
    renderDashboard()
    await screen.findByText('1 996,8 kg/ha')

    const plotSelect = screen.getByRole('combobox', { name: 'Filtrer par parcelle' })
    await user.selectOptions(plotSelect, '20')

    expect(screen.queryByText('1 996,8 kg/ha')).not.toBeInTheDocument()
  })
})

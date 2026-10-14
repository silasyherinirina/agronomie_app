import { useCallback, useEffect, useState, type FormEvent } from 'react'
import {
  AlertTriangle,
  CheckCircle2,
  CloudRain,
  Droplets,
  Thermometer,
  Wind,
  type LucideIcon,
} from 'lucide-react'
import { useAuth } from '../../hooks/useAuth'
import { useOptions } from '../../hooks/useOptions'
import { useResourceList } from '../../hooks/useResourceList'
import { plotsApi } from '../plots/api'
import { weatherObservationsApi, getPlotWeather, type WeatherObservationPayload } from './api'
import { AgronomicAdviceSection } from './AgronomicAdviceSection'
import { DataTable, type Column } from '../../components/DataTable'
import { Pagination } from '../../components/Pagination'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { FormField } from '../../components/FormField'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { toApiError, type NormalizedError } from '../../services/http'
import type { PlotWeather, WeatherObservation } from '../../types/api'

const emptyForm: WeatherObservationPayload = { plot_id: 0, observed_date: '', source: 'manuel' }

interface AgriculturalHint {
  icon: LucideIcon
  tone: 'warning' | 'success'
  text: string
}

/** Indicateur agricole prudent (§20) — jamais un conseil agronomique absolu, seulement un
 * rappel de seuils simples appliqués à des données réellement mesurées/prévues. */
function agriculturalHint(weather: PlotWeather): AgriculturalHint | null {
  const forecast = weather.forecast
  if (!forecast) return null
  const tomorrow = forecast.daily[1]
  if (tomorrow?.precipitation_sum != null && tomorrow.precipitation_sum >= 20) {
    return { icon: AlertTriangle, tone: 'warning', text: `Précipitations importantes prévues demain (${tomorrow.precipitation_sum} mm).` }
  }
  if (forecast.current.precipitation === 0 && forecast.current.temperature != null && forecast.current.temperature >= 18 && forecast.current.temperature <= 30) {
    return { icon: CheckCircle2, tone: 'success', text: 'Conditions actuelles potentiellement favorables aux travaux au champ.' }
  }
  return null
}

export function WeatherPage() {
  const { user } = useAuth()
  const canManage = user?.role === 'admin' || user?.role === 'cooperative_manager'

  const { options: plots } = useOptions(() => plotsApi.list({ per_page: 200 }), [])

  // Météo par parcelle (§19-20) : sélection d'une parcelle -> conditions réelles + prévisions.
  const [selectedPlotId, setSelectedPlotId] = useState<number | ''>('')
  const [weather, setWeather] = useState<PlotWeather | null>(null)
  const [weatherLoading, setWeatherLoading] = useState(false)
  const [weatherError, setWeatherError] = useState<string | null>(null)

  useEffect(() => {
    if (!selectedPlotId) {
      setWeather(null)
      return
    }
    setWeatherLoading(true)
    setWeatherError(null)
    getPlotWeather(selectedPlotId)
      .then(setWeather)
      .catch((err) => setWeatherError(toApiError(err).message))
      .finally(() => setWeatherLoading(false))
  }, [selectedPlotId])

  const list = useCallback((params: Record<string, unknown>) => weatherObservationsApi.list(params), [])
  const { data, meta, loading, error, params, setParam, refetch } = useResourceList<WeatherObservation>(list, { page: 1, per_page: 15 })

  const [form, setForm] = useState<WeatherObservationPayload>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<NormalizedError | null>(null)

  const [toDelete, setToDelete] = useState<WeatherObservation | null>(null)
  const [deleting, setDeleting] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    try {
      await weatherObservationsApi.create(form)
      setForm(emptyForm)
      refetch()
    } catch (err) {
      setFormError(toApiError(err))
    } finally {
      setSaving(false)
    }
  }

  async function confirmDelete() {
    if (!toDelete) return
    setDeleting(true)
    try {
      await weatherObservationsApi.remove(toDelete.id)
      setToDelete(null)
      refetch()
    } finally {
      setDeleting(false)
    }
  }

  const columns: Column<WeatherObservation>[] = [
    { key: 'observed_date', header: 'Date', render: (o) => o.observed_date },
    { key: 'plot', header: 'Parcelle', render: (o) => o.plot?.name ?? o.plot_id },
    { key: 'temperature', header: 'Température (°C)', render: (o) => o.temperature ?? '—', align: 'right' },
    { key: 'precipitation', header: 'Précipitations (mm)', render: (o) => o.precipitation ?? '—', align: 'right' },
    { key: 'humidity', header: 'Humidité (%)', render: (o) => o.humidity ?? '—', align: 'right' },
    { key: 'source', header: 'Source', render: (o) => o.source ?? '—' },
    ...(canManage
      ? [
          {
            key: 'actions',
            header: '',
            align: 'right' as const,
            render: (o: WeatherObservation) => (
              <Button variant="ghost" onClick={() => setToDelete(o)}>
                Supprimer
              </Button>
            ),
          },
        ]
      : []),
  ]

  const fieldErrors = formError?.errors ?? {}
  const hint = weather ? agriculturalHint(weather) : null

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="text-2xl font-semibold text-ink">Météo agricole</h1>
        <p className="text-sm text-muted">
          Analysez les conditions météorologiques d'une parcelle et obtenez un conseil agronomique indicatif basé sur sa
          zone agroécologique — conditions réelles (Open-Meteo, sans clé), complétées par les observations saisies
          manuellement sur le terrain.
        </p>
      </div>

      <AgronomicAdviceSection plots={plots} />

      <div className="rounded-lg border border-line bg-surface p-5 shadow-sm">
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <label htmlFor="plot-weather-select" className="text-sm font-medium text-ink">
            Parcelle
          </label>
          <select
            id="plot-weather-select"
            value={selectedPlotId}
            onChange={(e) => setSelectedPlotId(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">Sélectionner une parcelle…</option>
            {plots.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </div>

        {!selectedPlotId && <p className="text-sm text-muted">Sélectionnez une parcelle pour afficher sa météo.</p>}
        {weatherLoading && <p className="text-sm text-muted">Chargement de la météo…</p>}
        {weatherError && <Alert>{weatherError}</Alert>}

        {weather && !weatherLoading && (
          <div className="flex flex-col gap-4">
            {weather.forecast ? (
              <>
                <div className="flex flex-wrap gap-6">
                  <BigStat icon={Thermometer} value={`${weather.forecast.current.temperature ?? '—'} °C`} label="Température actuelle" />
                  <BigStat icon={Droplets} value={`${weather.forecast.current.humidity ?? '—'} %`} label="Humidité" />
                  <BigStat icon={CloudRain} value={`${weather.forecast.current.precipitation ?? '—'} mm`} label="Précipitations" />
                  <BigStat icon={Wind} value={`${weather.forecast.current.wind_speed ?? '—'} km/h`} label="Vent" />
                </div>

                <div className="overflow-x-auto">
                  <div className="flex gap-3">
                    {weather.forecast.daily.map((day) => (
                      <div key={day.date} className="flex min-w-[110px] flex-col items-center gap-1 rounded-md border border-line p-3 text-center">
                        <span className="text-xs font-medium text-muted">{new Date(day.date).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric' })}</span>
                        <span className="text-sm text-ink">
                          {day.temperature_min ?? '—'}° / {day.temperature_max ?? '—'}°
                        </span>
                        <span className="flex items-center justify-center gap-1 text-xs text-primary-dark">
                          <CloudRain size={12} aria-hidden /> {day.precipitation_sum ?? '—'} mm
                        </span>
                      </div>
                    ))}
                  </div>
                </div>

                {hint && (
                  <p className={`flex items-center gap-1.5 text-sm font-medium ${hint.tone === 'warning' ? 'text-warning' : 'text-primary-dark'}`}>
                    <hint.icon size={16} aria-hidden />
                    {hint.text}
                  </p>
                )}
                <p className="text-xs text-muted">
                  Indicateur informatif, pas un conseil agronomique — source : Open-Meteo, {new Date(weather.forecast.fetched_at).toLocaleString('fr-FR')}.
                </p>
              </>
            ) : (
              <Alert tone="warning">
                Service météo externe indisponible pour le moment ({weather.provider_error}). Les observations manuelles
                ci-dessous restent consultables.
              </Alert>
            )}

            {weather.recent_manual_observations.length > 0 && (
              <div>
                <h3 className="mb-2 text-sm font-semibold text-ink">Observations manuelles récentes de cette parcelle</h3>
                <ul className="flex flex-col gap-1 text-sm text-muted">
                  {weather.recent_manual_observations.map((o) => (
                    <li key={o.id}>
                      {new Date(o.observed_date).toLocaleDateString('fr-FR')} — {o.temperature != null ? `${o.temperature} °C` : '—'},{' '}
                      {o.precipitation != null ? `${o.precipitation} mm` : '—'}, {o.humidity != null ? `${o.humidity} %` : '—'}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        )}
      </div>

      {canManage && (
        <form className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5 shadow-sm" onSubmit={handleSubmit}>
          <h2 className="text-base font-semibold text-ink">Nouvelle observation manuelle</h2>
          {formError && !Object.keys(fieldErrors).length && <Alert>{formError.message}</Alert>}

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <FormField label="Parcelle" htmlFor="plot_id" required errors={fieldErrors.plot_id}>
              <select id="plot_id" value={form.plot_id || ''} onChange={(e) => setForm({ ...form, plot_id: Number(e.target.value) })} required>
                <option value="" disabled>
                  Sélectionner…
                </option>
                {plots.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField label="Date" htmlFor="observed_date" required errors={fieldErrors.observed_date}>
              <input id="observed_date" type="date" max={new Date().toISOString().slice(0, 10)} value={form.observed_date} onChange={(e) => setForm({ ...form, observed_date: e.target.value })} required />
            </FormField>
            <FormField label="Température (°C)" htmlFor="temperature" hint="Au moins une des 3 mesures est requise." errors={fieldErrors.temperature}>
              <input id="temperature" type="number" step="any" value={form.temperature ?? ''} onChange={(e) => setForm({ ...form, temperature: e.target.value ? Number(e.target.value) : null })} />
            </FormField>
            <FormField label="Précipitations (mm)" htmlFor="precipitation" errors={fieldErrors.precipitation}>
              <input id="precipitation" type="number" min={0} step="any" value={form.precipitation ?? ''} onChange={(e) => setForm({ ...form, precipitation: e.target.value ? Number(e.target.value) : null })} />
            </FormField>
            <FormField label="Humidité (%)" htmlFor="humidity" errors={fieldErrors.humidity}>
              <input id="humidity" type="number" min={0} max={100} step="any" value={form.humidity ?? ''} onChange={(e) => setForm({ ...form, humidity: e.target.value ? Number(e.target.value) : null })} />
            </FormField>
            <FormField label="Source" htmlFor="source" hint="Ex. relevé terrain, station locale…" errors={fieldErrors.source}>
              <input id="source" value={form.source ?? ''} onChange={(e) => setForm({ ...form, source: e.target.value })} />
            </FormField>
          </div>

          <div>
            <Button type="submit" loading={saving}>
              Enregistrer l'observation
            </Button>
          </div>
        </form>
      )}

      <div className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5 shadow-sm">
        <select value={String(params.plot_id ?? '')} onChange={(e) => setParam('plot_id', e.target.value || undefined)}>
          <option value="">Toutes les parcelles</option>
          {plots.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>

        {error && <Alert>{error.message}</Alert>}
        <DataTable columns={columns} rows={data} rowKey={(o) => o.id} loading={loading} emptyMessage="Aucune observation météo enregistrée." />
        <Pagination meta={meta} onPageChange={(page) => setParam('page', page)} />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer l'observation"
          message={`Confirmer la suppression de l'observation du ${toDelete.observed_date} ?`}
          busy={deleting}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
    </div>
  )
}

function BigStat({ icon: Icon, value, label }: { icon: LucideIcon; value: string; label: string }) {
  return (
    <div className="flex items-center gap-2">
      <span className="flex h-9 w-9 items-center justify-center rounded-md bg-primary-light text-primary" aria-hidden>
        <Icon size={18} />
      </span>
      <div className="flex flex-col">
        <span className="text-lg font-semibold text-ink">{value}</span>
        <span className="text-xs text-muted">{label}</span>
      </div>
    </div>
  )
}

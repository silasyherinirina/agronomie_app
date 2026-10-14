import { useState, type FormEvent } from 'react'
import {
  AlertTriangle,
  CloudRain,
  Droplets,
  History,
  Info,
  Sparkles,
  Sun,
  Thermometer,
  TrendingUp,
  Wind,
  type LucideIcon,
} from 'lucide-react'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { getAgronomicAdvice } from './api'
import { toApiError } from '../../services/http'
import type { CropRecommendation, WeatherAgronomicAdvice, WeatherAnalysisType, WeatherRisk } from '../../types/api'

interface PlotOption {
  id: number
  name: string
}

const ANALYSIS_TYPE_META: Record<WeatherAnalysisType, { label: string; icon: LucideIcon; className: string }> = {
  historical: { label: 'Analyse historique', icon: History, className: 'bg-primary-light text-primary-dark' },
  current: { label: 'Conditions actuelles', icon: Sun, className: 'bg-success-bg text-primary-dark' },
  forecast: { label: 'Prévision météorologique', icon: TrendingUp, className: 'bg-warning-bg text-warning' },
}

const LEVEL_META: Record<CropRecommendation['level'], { label: string; className: string }> = {
  recommended: { label: 'Recommandée', className: 'bg-success-bg text-primary-dark' },
  moderate: { label: 'Moyennement adaptée', className: 'bg-warning-bg text-warning' },
  unfavorable: { label: 'Défavorable', className: 'bg-danger-bg text-danger' },
}

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

function ConditionTile({ icon: Icon, label, value }: { icon: LucideIcon; label: string; value: string }) {
  return (
    <div className="flex items-center gap-3 rounded-xl bg-canvas p-4">
      <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-surface text-primary shadow-sm" aria-hidden>
        <Icon size={18} />
      </span>
      <div className="flex min-w-0 flex-col">
        <span className="truncate text-lg font-semibold text-ink">{value}</span>
        <span className="text-xs text-muted">{label}</span>
      </div>
    </div>
  )
}

function CropRecommendationCard({ recommendation }: { recommendation: CropRecommendation }) {
  const meta = LEVEL_META[recommendation.level]
  return (
    <div className="flex flex-col gap-2 rounded-xl border border-line bg-surface p-4">
      <div className="flex items-center justify-between gap-2">
        <span className="font-medium text-ink">{recommendation.crop}</span>
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${meta.className}`}>{meta.label}</span>
      </div>
      <div className="flex items-center gap-2">
        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-canvas">
          <div className="h-full rounded-full bg-primary" style={{ width: `${recommendation.score}%` }} />
        </div>
        <span className="text-sm font-semibold text-ink">{recommendation.score}/100</span>
      </div>
      <ul className="flex flex-col gap-0.5 text-xs text-muted">
        {recommendation.reasons.map((reason, i) => (
          <li key={i}>{reason}</li>
        ))}
      </ul>
    </div>
  )
}

function RiskItem({ risk }: { risk: WeatherRisk }) {
  return (
    <li className="flex items-start gap-2 rounded-xl bg-warning-bg p-3 text-sm text-warning">
      <AlertTriangle size={16} className="mt-0.5 flex-shrink-0" aria-hidden />
      {risk.message}
    </li>
  )
}

/**
 * Module — Conseil agronomique prédictif basé sur la météo. Interroge
 * GET /plots/:id/agronomic-advice (WeatherAgronomicAdviceService côté backend) : parcelle
 * -> région -> zone agroécologique -> météo historique/actuelle/prévisionnelle -> cultures
 * recommandées. Ne remplace pas la section météo simple existante ci-dessous dans WeatherPage
 * (conservée telle quelle) — ce bloc est un module d'analyse distinct et plus riche.
 */
export function AgronomicAdviceSection({ plots }: { plots: PlotOption[] }) {
  const [plotId, setPlotId] = useState('')
  const [date, setDate] = useState(todayIso())
  const [advice, setAdvice] = useState<WeatherAgronomicAdvice | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleAnalyze(e: FormEvent) {
    e.preventDefault()
    if (!plotId || !date) return
    setLoading(true)
    setError(null)
    setAdvice(null)
    try {
      const result = await getAgronomicAdvice(Number(plotId), date)
      setAdvice(result)
    } catch (err) {
      setError(toApiError(err).message)
    } finally {
      setLoading(false)
    }
  }

  const typeMeta = advice?.analysis_type ? ANALYSIS_TYPE_META[advice.analysis_type] : null
  const day = advice?.weather

  return (
    <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-5 shadow-sm">
      <div>
        <h2 className="flex items-center gap-2 text-base font-semibold text-ink">
          <Sparkles size={18} className="text-primary" aria-hidden />
          Conseil agronomique prédictif
        </h2>
        <p className="mt-0.5 text-xs text-muted">
          Sélectionnez une parcelle et une date : le système détermine automatiquement sa région, sa zone
          agroécologique, et analyse la météo correspondante (historique, actuelle ou prévisionnelle selon la date).
        </p>
      </div>

      <form className="flex flex-wrap items-end gap-3" onSubmit={handleAnalyze}>
        <div className="flex flex-col gap-1">
          <label htmlFor="advice-plot" className="text-sm font-medium text-ink">
            Parcelle
          </label>
          <select
            id="advice-plot"
            value={plotId}
            onChange={(e) => setPlotId(e.target.value)}
            required
            className="rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
          >
            <option value="" disabled>
              Sélectionner une parcelle…
            </option>
            {plots.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </div>
        <div className="flex flex-col gap-1">
          <label htmlFor="advice-date" className="text-sm font-medium text-ink">
            Date d'analyse
          </label>
          <input
            id="advice-date"
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            required
            className="rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
          />
        </div>
        <Button type="submit" loading={loading} disabled={!plotId || !date}>
          Analyser
        </Button>
      </form>

      {error && <Alert>{error}</Alert>}

      {advice && (
        <div className="flex flex-col gap-4 border-t border-line pt-4">
          {/* CONTEXTE */}
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div>
              <p className="text-xs text-muted">Parcelle</p>
              <p className="text-sm font-medium text-ink">{advice.plot.name}</p>
            </div>
            <div>
              <p className="text-xs text-muted">Région</p>
              <p className="text-sm font-medium text-ink">{advice.region ?? '—'}</p>
            </div>
            <div>
              <p className="text-xs text-muted">Zone agroécologique</p>
              <p className="text-sm font-medium text-ink">{advice.agroecological_zone?.name ?? '—'}</p>
            </div>
            <div>
              <p className="text-xs text-muted">Culture actuelle</p>
              <p className="text-sm font-medium text-ink">
                {advice.active_crop_cycle ? advice.active_crop_cycle.crop : 'Aucun cycle en cours'}
              </p>
            </div>
          </div>

          {typeMeta && (
            <span className={`inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ${typeMeta.className}`}>
              <typeMeta.icon size={14} aria-hidden />
              {typeMeta.label} — {new Date(advice.date).toLocaleDateString('fr-FR')}
            </span>
          )}

          {advice.unavailable_reason ? (
            <Alert tone="warning">{advice.unavailable_reason}</Alert>
          ) : (
            <>
              {/* CONDITIONS MÉTÉOROLOGIQUES */}
              {day && (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                  <ConditionTile
                    icon={Thermometer}
                    label="Température"
                    value={day.temperature_min !== null && day.temperature_max !== null ? `${day.temperature_min}–${day.temperature_max} °C` : '—'}
                  />
                  <ConditionTile icon={Droplets} label="Humidité" value={day.humidity !== null ? `${day.humidity} %` : '—'} />
                  <ConditionTile icon={CloudRain} label="Précipitations" value={day.precipitation !== null ? `${day.precipitation} mm` : '—'} />
                  <ConditionTile icon={Wind} label="Vent" value={day.wind_speed !== null ? `${day.wind_speed} km/h` : '—'} />
                </div>
              )}

              {advice.manual_observation_note && (
                <p className="flex items-start gap-1.5 text-xs text-muted">
                  <Info size={14} className="mt-0.5 flex-shrink-0" aria-hidden />
                  {advice.manual_observation_note}
                </p>
              )}

              {/* CONSEIL AGRONOMIQUE */}
              <div className="rounded-xl bg-primary-light p-4">
                <h3 className="mb-1 flex items-center gap-1.5 text-sm font-semibold text-primary-dark">
                  <Sparkles size={16} aria-hidden /> Conseil agronomique
                </h3>
                <p className="text-sm text-ink">{advice.summary}</p>
                {advice.advice && <p className="mt-2 text-sm text-ink">{advice.advice}</p>}
              </div>

              {/* CULTURES RECOMMANDÉES */}
              {advice.recommendations.length > 0 && (
                <div>
                  <h3 className="mb-2 text-sm font-semibold text-ink">Cultures recommandées</h3>
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {advice.recommendations.map((r) => (
                      <CropRecommendationCard key={r.crop_id} recommendation={r} />
                    ))}
                  </div>
                </div>
              )}

              {/* RISQUES */}
              {advice.risks.length > 0 && (
                <div>
                  <h3 className="mb-2 flex items-center gap-1.5 text-sm font-semibold text-ink">
                    <AlertTriangle size={16} className="text-warning" aria-hidden /> Points de vigilance
                  </h3>
                  <ul className="flex flex-col gap-2">
                    {advice.risks.map((risk, i) => (
                      <RiskItem key={i} risk={risk} />
                    ))}
                  </ul>
                </div>
              )}

              {/* SOURCE */}
              <p className="text-xs text-muted">
                Source : {advice.source === 'open-meteo-archive' ? 'Open-Meteo (archive historique)' : 'Open-Meteo'}
                {advice.fetched_at && <> — données récupérées le {new Date(advice.fetched_at).toLocaleString('fr-FR')}</>}
              </p>
            </>
          )}

          <p className="text-xs italic text-muted">{advice.disclaimer}</p>
        </div>
      )}
    </div>
  )
}

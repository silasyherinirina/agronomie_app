import { http } from '../../services/http'
import { createResourceApi } from '../../services/resource'
import type { ApiResource, PlotWeather, WeatherAgronomicAdvice, WeatherObservation } from '../../types/api'

export interface WeatherObservationPayload {
  plot_id: number
  observed_date: string
  temperature?: number | null
  precipitation?: number | null
  humidity?: number | null
  source?: string | null
}

export const weatherObservationsApi = createResourceApi<WeatherObservation, WeatherObservationPayload, Partial<WeatherObservationPayload>>(
  '/weather-observations',
)

/** Météo réelle (Open-Meteo, sans clé) + observations manuelles récentes pour une parcelle. */
export function getPlotWeather(plotId: number): Promise<PlotWeather> {
  return http.get<ApiResource<PlotWeather>>(`/plots/${plotId}/weather`).then((r) => r.data.data)
}

/**
 * Module — Conseil agronomique prédictif basé sur la météo. `date` au format YYYY-MM-DD ;
 * détermine seule si l'analyse sera historique, actuelle ou prévisionnelle (voir
 * WeatherAgronomicAdviceService côté backend).
 */
export function getAgronomicAdvice(plotId: number, date: string): Promise<WeatherAgronomicAdvice> {
  return http
    .get<ApiResource<WeatherAgronomicAdvice>>(`/plots/${plotId}/agronomic-advice`, { params: { date } })
    .then((r) => r.data.data)
}

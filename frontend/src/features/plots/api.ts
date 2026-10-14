import { http } from '../../services/http'
import { createResourceApi } from '../../services/resource'
import type { AgriculturalCampaign, GeoPolygon, Plot } from '../../types/api'

export interface PlotPayload {
  cooperative_id?: number
  farmer_id?: number | null
  name: string
  geometry?: GeoPolygon
  status?: 'active' | 'inactive'
}

export const plotsApi = createResourceApi<Plot, PlotPayload, Partial<PlotPayload>>('/plots')

/**
 * Campagnes agricoles réellement associées à une parcelle (via ses cycles culturaux) —
 * relation renvoyée par le backend (PlotController::campaigns()), jamais reconstruite/devinée
 * côté frontend. Alimente le filtre "Campagne" du Dashboard une fois une parcelle sélectionnée.
 */
export function getPlotCampaigns(plotId: number): Promise<AgriculturalCampaign[]> {
  return http.get<{ data: AgriculturalCampaign[] }>(`/plots/${plotId}/campaigns`).then((r) => r.data.data)
}

import { http } from '../../services/http'
import { createResourceApi } from '../../services/resource'
import type { Sale, SaleStatistics } from '../../types/api'

export interface SalePayload {
  // Absent pour un cooperative_manager (le backend force sa propre coopérative) — requis
  // uniquement pour un admin (voir SaleService::resolveCooperativeId).
  cooperative_id?: number
  farmer_id?: number | null
  plot_id?: number | null
  crop_id?: number | null
  variety_id?: number | null
  crop_cycle_id?: number | null
  campaign_id?: number | null
  buyer_name?: string | null
  buyer_contact?: string | null
  sale_date: string
  quantity: number
  unit: string
  unit_price: number
  currency?: string
  observations?: string | null
}

export const salesApi = createResourceApi<Sale, SalePayload, Partial<SalePayload>>('/sales')

/** GET /api/sales/statistics — mêmes filtres que salesApi.list() (§27 : cohérence garantie car
 * calculés côté serveur par le même SaleService, jamais recalculés indépendamment ici). */
export function getSaleStatistics(params: Record<string, unknown> = {}): Promise<SaleStatistics> {
  return http.get<{ data: SaleStatistics }>('/sales/statistics', { params }).then((r) => r.data.data)
}

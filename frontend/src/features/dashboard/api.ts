import { http } from '../../services/http'
import type { ApiResource, DashboardSummary } from '../../types/api'

export function getDashboardSummary(params: {
  plot_id?: number
  cooperative_id?: number
  campaign_id?: number
}): Promise<DashboardSummary> {
  return http.get<ApiResource<DashboardSummary>>('/dashboard', { params }).then((r) => r.data.data)
}

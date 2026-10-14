import { createResourceApi } from '../../services/resource'
import type { Cooperative, GeoPoint } from '../../types/api'

export interface CooperativePayload {
  name: string
  region: string
  district?: string | null
  commune?: string | null
  geometry?: GeoPoint | null
  manager_user_id?: number | null
  status?: 'active' | 'inactive'
}

export const cooperativesApi = createResourceApi<Cooperative, CooperativePayload, Partial<CooperativePayload>>(
  '/cooperatives',
)

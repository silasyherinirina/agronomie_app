import { createResourceApi } from '../../services/resource'
import type { Farmer } from '../../types/api'

export interface FarmerPayload {
  cooperative_id?: number
  user_id?: number | null
  first_name: string
  last_name: string
  join_date?: string | null
  status?: 'active' | 'inactive'
}

export const farmersApi = createResourceApi<Farmer, FarmerPayload, Partial<FarmerPayload>>('/farmers')

import { createResourceApi } from '../../services/resource'
import type { Harvest } from '../../types/api'

export interface HarvestPayload {
  crop_cycle_id: number
  harvest_date: string
  quantity: number
  unit: string
  yield_source?: 'calculated' | 'declared'
  yield_value?: number | null
  quality?: string | null
  observations?: string | null
}

export const harvestsApi = createResourceApi<Harvest, HarvestPayload, Partial<HarvestPayload>>('/harvests')

import { createResourceApi } from '../../services/resource'
import type { CropCycle } from '../../types/api'

export interface CropCyclePayload {
  plot_id: number
  crop_id: number
  variety_id?: number | null
  campaign_id: number
  planting_date?: string | null
  expected_harvest_date?: string | null
  status?: 'in_progress' | 'harvested' | 'failed'
}

export const cropCyclesApi = createResourceApi<CropCycle, CropCyclePayload, Partial<CropCyclePayload>>('/crop-cycles')

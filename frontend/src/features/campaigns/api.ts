import { createResourceApi } from '../../services/resource'
import type { AgriculturalCampaign } from '../../types/api'

export interface CampaignPayload {
  name: string
  year: number
  season?: string | null
  start_date: string
  end_date?: string | null
  status?: 'active' | 'closed'
}

export const campaignsApi = createResourceApi<AgriculturalCampaign, CampaignPayload, Partial<CampaignPayload>>(
  '/agricultural-campaigns',
)

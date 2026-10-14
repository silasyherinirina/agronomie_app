import { createResourceApi } from '../../services/resource'
import type { Input, InputType, InputUsage } from '../../types/api'

export interface InputPayload {
  name: string
  type: InputType
  unit: string
}

export interface InputUsagePayload {
  input_id: number
  plot_id: number
  campaign_id: number
  farmer_id?: number | null
  quantity: number
  cost?: number | null
  date: string
  observations?: string | null
}

export const inputsApi = createResourceApi<Input, InputPayload, Partial<InputPayload>>('/inputs')
export const inputUsagesApi = createResourceApi<InputUsage, InputUsagePayload, Partial<InputUsagePayload>>('/input-usages')

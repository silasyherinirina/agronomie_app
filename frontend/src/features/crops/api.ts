import { http } from '../../services/http'
import { createResourceApi } from '../../services/resource'
import type { Crop, CropModelStatus, CropVariety } from '../../types/api'

export interface CropPayload {
  name: string
  code?: string | null
  category?: string | null
  cycle_duration_days?: number | null
}

export interface CropVarietyPayload {
  crop_id: number
  name: string
}

export const cropsApi = createResourceApi<Crop, CropPayload, Partial<CropPayload>>('/crops')
export const cropVarietiesApi = createResourceApi<CropVariety, CropVarietyPayload, Partial<CropVarietyPayload>>('/crop-varieties')

/**
 * Statut du modèle IA par culture (voir CropModelStatusController) — jamais déduit côté
 * frontend : "culture supportée dans la base" ≠ "modèle IA disponible" (§13/§16).
 */
export function getCropModelStatus(): Promise<CropModelStatus[]> {
  return http.get<{ data: CropModelStatus[] }>('/crops/model-status').then((r) => r.data.data)
}

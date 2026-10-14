import { http } from '../../services/http'
import { createResourceApi } from '../../services/resource'
import type { ApiResource, PredictionFeatures, ProductionPrediction, SuggestedPredictionInputs } from '../../types/api'

export interface PredictionPayload {
  plot_id: number
  // Obligatoire : plus de repli implicite sur le riz (architecture multi-cultures — voir
  // PredictionService::resolveCropKey() côté backend). Une prédiction sans culture n'est plus
  // une requête valide.
  crop_id: number
  campaign_id?: number | null
  features: PredictionFeatures
}

export const predictionsApi = createResourceApi<ProductionPrediction, PredictionPayload>('/predictions')

export function getSuggestedInputs(plotId: number, campaignId?: number): Promise<SuggestedPredictionInputs> {
  return http
    .get<ApiResource<SuggestedPredictionInputs>>(`/plots/${plotId}/prediction-inputs`, {
      params: { campaign_id: campaignId },
    })
    .then((r) => r.data.data)
}

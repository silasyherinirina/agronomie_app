/**
 * Types miroir des réponses JSON du backend Laravel (app/Http/Resources/*.php).
 * Toute évolution d'une Resource côté backend doit être répercutée ici.
 */

export type Role = 'admin' | 'cooperative_manager' | 'farmer' | 'analyst'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  farmer_id: number | null
  managed_cooperative_id: number | null
  email_verified_at: string | null
  created_at: string
}

/** GeoJSON minimal — seuls Point et Polygon sont utilisés (cf. Phase 0 stratégie PostGIS). */
export type GeoPoint = { type: 'Point'; coordinates: [number, number] }
export type GeoPolygon = { type: 'Polygon'; coordinates: number[][][] }

export interface Cooperative {
  id: number
  name: string
  region: string
  district: string | null
  commune: string | null
  geometry: GeoPoint | null
  manager_user_id: number | null
  manager?: User | null
  status: 'active' | 'inactive'
  farmers_count?: number
  plots_count?: number
  created_at: string
  updated_at: string
}

export interface Farmer {
  id: number
  cooperative_id: number
  cooperative?: Cooperative
  user_id: number | null
  first_name: string
  last_name: string
  full_name: string
  join_date: string | null
  status: 'active' | 'inactive'
  plots_count?: number
  created_at: string
  updated_at: string
}

export interface Plot {
  id: number
  cooperative_id: number
  cooperative?: Cooperative
  farmer_id: number | null
  farmer?: Farmer | null
  name: string
  geometry: GeoPolygon | null
  area_ha: number | null
  status: 'active' | 'inactive'
  crop_cycles_count?: number
  created_at: string
  updated_at: string
}

export interface CropVariety {
  id: number
  crop_id: number
  crop?: Crop
  name: string
  created_at: string
  updated_at: string
}

export interface Crop {
  id: number
  name: string
  // Identifiant stable pour le service IA (ex. "pomme_de_terre") — voir CropModelStatus pour
  // savoir si cette culture dispose réellement d'un modèle entraîné ("supportée" ≠ "disponible").
  code: string | null
  category: string | null
  cycle_duration_days: number | null
  is_active: boolean
  varieties?: CropVariety[]
  created_at: string
  updated_at: string
}

/**
 * Miroir de GET /api/crops/model-status (CropModelStatusController) — statut du modèle IA pour
 * CHAQUE culture active, jamais deviné côté frontend (cahier des charges §13/§16). `available`
 * ne veut dire qu'une chose : un modèle actif existe réellement pour cette culture.
 */
export type CropModelAvailability = 'ready' | 'inactive' | 'training_required' | 'unavailable'

export interface CropModelStatus {
  id: number
  name: string
  category: string | null
  cycle_duration_days: number | null
  crop_key: string | null
  available: boolean
  status: CropModelAvailability
  algorithm: string | null
  version: string | null
  is_experimental: boolean | null
}

export interface AgriculturalCampaign {
  id: number
  name: string
  year: number
  season: string | null
  start_date: string
  end_date: string | null
  status: 'active' | 'closed'
  created_at: string
  updated_at: string
}

export interface CropCycle {
  id: number
  plot_id: number
  plot?: Plot
  crop_id: number
  crop?: Crop
  variety_id: number | null
  variety?: CropVariety | null
  campaign_id: number
  campaign?: AgriculturalCampaign
  planting_date: string | null
  expected_harvest_date: string | null
  status: 'in_progress' | 'harvested' | 'failed'
  harvests_count?: number
  created_at: string
  updated_at: string
}

export interface Harvest {
  id: number
  crop_cycle_id: number
  crop_cycle?: CropCycle
  harvest_date: string
  quantity: number
  unit: string
  yield_value: number | null
  yield_source: 'calculated' | 'declared'
  quality: string | null
  observations: string | null
  created_at: string
  updated_at: string
}

export type InputType = 'semence' | 'engrais' | 'phytosanitaire' | 'autre'

export interface Input {
  id: number
  name: string
  type: InputType
  unit: string
  created_at: string
  updated_at: string
}

export interface InputUsage {
  id: number
  input_id: number
  input?: Input
  plot_id: number
  plot?: Plot
  campaign_id: number
  campaign?: AgriculturalCampaign
  farmer_id: number | null
  farmer?: Farmer | null
  quantity: number
  cost: number | null
  date: string
  observations: string | null
  created_at: string
  updated_at: string
}

/**
 * Module L — Météo. Saisie manuelle (Phase 11 initiale) + fournisseur externe réel Open-Meteo
 * sans clé, combinés (Phase H) — voir backend WeatherObservationController / WeatherForecastController.
 */
export interface WeatherObservation {
  id: number
  plot_id: number
  plot?: Plot
  observed_date: string
  temperature: number | null
  precipitation: number | null
  humidity: number | null
  source: string | null
  created_at: string
  updated_at: string
}

export interface WeatherDailyForecast {
  date: string
  precipitation_sum: number | null
  temperature_max: number | null
  temperature_min: number | null
}

export interface WeatherCurrentConditions {
  temperature: number | null
  humidity: number | null
  precipitation: number | null
  wind_speed: number | null
  observed_at: string | null
}

export interface WeatherForecast {
  current: WeatherCurrentConditions
  daily: WeatherDailyForecast[]
  source: string
  fetched_at: string
}

export interface PlotWeather {
  plot_id: number
  centroid: { lat: number; lng: number }
  /** null si le fournisseur externe est indisponible — voir provider_error, jamais de valeur inventée. */
  forecast: WeatherForecast | null
  provider_error: string | null
  recent_manual_observations: WeatherObservation[]
}

/**
 * Module — Conseil agronomique prédictif basé sur la météo (GET /plots/:id/agronomic-advice).
 * Miroir de WeatherAgronomicAdviceService::adviceFor() (backend) — voir ce fichier pour la
 * logique exacte. `analysis_type` distingue strictement passé/présent/futur (jamais confondus) ;
 * les champs météo/recommandations/risks restent `null`/`[]` tant qu'aucune donnée réelle n'est
 * disponible (voir `unavailable_reason`), jamais une valeur inventée.
 */
export type WeatherAnalysisType = 'historical' | 'current' | 'forecast'
export type CropRecommendationLevel = 'recommended' | 'moderate' | 'unfavorable'
export type WeatherRiskLevel = 'low' | 'moderate' | 'high'

export interface AgroEcologicalZone {
  key: string
  name: string
  climate: string
}

export interface ActiveCropCycleSummary {
  crop_cycle_id: number
  crop_id: number
  crop: string | null
  variety: string | null
  campaign_id: number
  campaign: string | null
  planting_date: string | null
}

export interface WeatherAdviceDay {
  date: string
  temperature_min: number | null
  temperature_max: number | null
  precipitation: number | null
  humidity: number | null
  wind_speed: number | null
  /** Non nul uniquement pour analysis_type === 'current' (lecture instantanée Open-Meteo). */
  observed_at: string | null
}

export interface WeatherAdviceSummary {
  temperature_min: number | null
  temperature_max: number | null
  humidity_avg: number | null
  precipitation_cumulative: number | null
  precipitation_avg_per_day: number | null
  rainfall_category: 'low' | 'moderate' | 'high' | null
  wind_speed_avg: number | null
  wind_speed_max: number | null
  precipitation_trend: 'en hausse' | 'en baisse' | 'stable' | null
  days_analyzed: number
}

export interface CropRecommendation {
  crop_id: number
  crop: string
  /** Score de compatibilité indicatif (0-100), PAS une probabilité statistique — voir `reasons`. */
  score: number
  level: CropRecommendationLevel
  reasons: string[]
}

export interface WeatherRisk {
  type: string
  level: WeatherRiskLevel
  message: string
}

export interface WeatherAgronomicAdvice {
  plot: { id: number; name: string; area_ha: number | null }
  region: string | null
  agroecological_zone: AgroEcologicalZone | null
  active_crop_cycle: ActiveCropCycleSummary | null
  date: string
  disclaimer: string
  analysis_type: WeatherAnalysisType | null
  window: { start: string | null; end: string | null } | null
  weather: WeatherAdviceDay | null
  weather_summary: WeatherAdviceSummary | null
  /** Observation manuelle proche de la date, signalée mais JAMAIS fusionnée avec Open-Meteo. */
  manual_observation_note: string | null
  recommendations: CropRecommendation[]
  risks: WeatherRisk[]
  summary: string
  advice: string | null
  /** Message honnête si aucune donnée n'est disponible (région inconnue, météo indisponible…). */
  unavailable_reason: string | null
  source: string | null
  fetched_at: string | null
}

/** Enveloppe standard d'une liste paginée (Laravel API Resource ::collection()->paginate()). */
export interface Paginated<T> {
  data: T[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

/** Enveloppe d'une ressource unique (JsonResource sans ::collection()). */
export interface ApiResource<T> {
  data: T
}

/** Corps d'une erreur 422 de validation Laravel. */
export interface ApiValidationError {
  message: string
  errors: Record<string, string[]>
}

/**
 * Miroir de DashboardService::summary() (backend). Les quantités de récolte/intrant sont
 * toujours groupées par unité (unit) — jamais fusionnées entre unités hétérogènes (kg, sac...).
 * Seuls area_ha (hectares) et cost/total_cost (Ariary) sont des grandeurs homogènes sommables.
 */
export interface DashboardKpis {
  cooperatives_count: number | null
  farmers_count: number | null
  plots_count: number
  total_area_ha: number
  campaigns_count: number
}

export interface UnitTotal {
  unit: string
  total_quantity: number
  harvests_count: number
}

export interface UnitAverage {
  unit: string
  average_yield: number
  sample_size: number
}

export interface CropProduction {
  crop_id: number
  crop_name: string
  unit: string
  total_quantity: number
}

export interface CropYield {
  crop_id: number
  crop_name: string
  unit: string
  average_yield: number
}

export interface CampaignProduction {
  campaign_id: number
  campaign_name: string
  year: number
  unit: string
  total_quantity: number
}

export interface InputConsumptionStat {
  input_id: number
  input_name: string
  type: InputType
  unit: string
  total_quantity: number
  total_cost: number
}

export interface GeographyStat {
  region: string
  plots_count: number
  total_area_ha: number
}

/**
 * Miroir de ai-service/app/schemas/prediction.py (Phase 9) et de ProductionPredictionResource
 * (Phase 10). Le modèle est entraîné sur des données SYNTHÉTIQUES — voir
 * docs/machine-learning/phase9-pipeline.md — toute prédiction reste expérimentale
 * (`is_experimental: true`).
 */
export type VarietyClass = 'traditionnelle' | 'amelioree'

export interface PredictionFeatures {
  area_ha: number
  fertilizer_kg_per_ha: number
  rainfall_mm: number
  planting_month: number
  variety_class: VarietyClass
}

export interface SuggestedPredictionInputs {
  plot_id: number
  crop_id: number | null
  area_ha: number | null
  fertilizer_kg_per_ha: number | null
  planting_month: number | null
  rainfall_mm: number | null
  variety_class: VarietyClass | null
  sources: Record<string, string | null>
}

export interface PredictionModelInfo {
  id: string
  name: string
  algorithm: string
  version: string
  trained_at: string | null
  metrics: {
    mae: number
    rmse: number
    r2: number
    dataset_size?: number
    test_size?: number
    comparison?: { algorithm: string; label: string; metrics: { mae: number; rmse: number; r2: number } }[]
  }
}

export interface ProductionPrediction {
  id: number
  plot_id: number
  plot?: Plot
  crop_id: number | null
  crop?: Crop
  campaign_id: number | null
  campaign?: AgriculturalCampaign
  predicted_yield: number
  unit: string
  input_features: PredictionFeatures
  // Importance réelle des variables (coefficients standardisés ou réduction d'impureté selon
  // l'algorithme) — jamais de niveau de confiance inventé (cahier des charges §10).
  feature_importances: Record<string, number> | null
  is_experimental: boolean
  model?: PredictionModelInfo
  predicted_at: string
  created_at: string
}

/**
 * Module M — Ventes agricoles. Miroir de SaleResource (backend). Toutes les FK autres que
 * `cooperative_id` sont nullables — voir la migration `create_sales_table` pour la justification
 * (une vente agrégée n'est pas toujours rattachée à un cycle cultural précis).
 */
export interface Sale {
  id: number
  cooperative_id: number
  cooperative?: Cooperative
  farmer_id: number | null
  farmer?: Farmer | null
  plot_id: number | null
  plot?: Plot | null
  crop_id: number | null
  crop?: Crop | null
  variety_id: number | null
  variety?: CropVariety | null
  crop_cycle_id: number | null
  campaign_id: number | null
  campaign?: AgriculturalCampaign | null
  buyer_name: string | null
  buyer_contact: string | null
  sale_date: string
  quantity: number
  unit: string
  unit_price: number
  // Toujours recalculé par le backend (quantity × unit_price) — jamais fait confiance à une
  // valeur locale non confirmée par la réponse API.
  total_amount: number
  currency: string
  observations: string | null
  created_at: string
  updated_at: string
}

export interface SaleUnitStat {
  unit: string
  total_quantity: number
  sales_count: number
}

export interface SaleCropAmount {
  crop_id: number
  crop_name: string
  total_amount: number
}

/** Miroir de SaleService::statistics() — mêmes filtres/périmètre que GET /api/sales (§27). */
export interface SaleStatistics {
  sales_count: number
  total_amount: number
  average_unit_price: number
  quantity_by_unit: SaleUnitStat[]
  amount_by_crop: SaleCropAmount[]
}

export type RecentActivityType = 'farmer_created' | 'plot_created' | 'harvest_recorded' | 'input_usage_recorded'

export interface RecentActivityEntry {
  type: RecentActivityType
  description: string
  date: string
  entity_id: number
}

export interface DashboardAlert {
  type: 'plot_without_active_cycle'
  message: string
  entity_id: number
}

/** Module M — Ventes agricoles, série pour le graphique "Évolution des ventes" (dashboard). */
export interface SaleCampaignAmount {
  campaign_id: number
  campaign_name: string
  year: number
  total_amount: number
}

/** Ligne de la table "Dernières ventes" (dashboard) — sous-ensemble léger de Sale. */
export interface RecentSaleEntry {
  id: number
  sale_date: string
  crop_name: string | null
  buyer_name: string | null
  total_amount: number
  currency: string
}

/**
 * Centre de notifications. Miroir de NotificationResource (backend) — voir
 * BuildsNotificationPayload pour la structure exacte du payload construit par chaque
 * `App\Notifications\*`. Nommé `AppNotification` (et non `Notification`) pour ne pas entrer
 * en conflit avec le type DOM global `Notification`.
 */
export type NotificationSeverity = 'info' | 'success' | 'warning' | 'error'

export interface AppNotification {
  id: string
  type: string | null
  title: string | null
  message: string | null
  severity: NotificationSeverity
  icon: string | null
  /** Chemin React Router relatif (ex. "/harvests") — jamais une URL absolue. */
  route: string | null
  entity_type: string | null
  entity_id: number | string | null
  metadata: Record<string, unknown>
  is_read: boolean
  read_at: string | null
  created_at: string
}

export interface UnreadCountResponse {
  count: number
}

export interface DashboardSummary {
  filters: {
    cooperative_id: number | null
    farmer_id: number | null
    campaign_id: number | null
    plot_id: number | null
  }
  kpis: DashboardKpis
  harvest_totals_by_unit: UnitTotal[]
  average_yield_by_unit: UnitAverage[]
  production_by_crop: CropProduction[]
  yield_by_crop: CropYield[]
  production_by_campaign: CampaignProduction[]
  input_consumption: InputConsumptionStat[]
  geography: GeographyStat[]
  recent_activity: RecentActivityEntry[]
  alerts: DashboardAlert[]
  // Module M — Ventes agricoles (§27 : mêmes chiffres que GET /api/sales/statistics pour les
  // mêmes filtres, calculés par le même SaleService côté backend).
  sales: SaleStatistics
  sales_by_campaign: SaleCampaignAmount[]
  recent_sales: RecentSaleEntry[]
}

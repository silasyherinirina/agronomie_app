import axios from 'axios'
import { http, toApiError, type NormalizedError } from '../../services/http'

export type ReportType = 'production' | 'harvests' | 'sales'
export type ReportFormat = 'csv' | 'xlsx' | 'pdf'

/**
 * Restructuration "cycle cultural = relation racine" — le cycle est désormais l'unique filtre
 * relationnel envoyé par cette page (§1/§4/§20) : plus de `cooperative_id`/`farmer_id`/
 * `plot_id`/`crop_id`/`variety_id`/`campaign_id` indépendants ici, ces dimensions sont dérivées
 * du cycle sélectionné et affichées en lecture seule (voir ReportFilters.tsx). La période reste
 * un filtre orthogonal (temporel, pas relationnel), applicable dans les deux modes.
 * Le backend (`ReportDataBuilder`) continue d'accepter ces champs indépendamment en Mode global
 * pour un usage API direct — ce type ne reflète que ce que CETTE page envoie réellement.
 */
export interface ReportFilters {
  crop_cycle_id?: number
  start_date?: string
  end_date?: string
}

const EXTENSION: Record<ReportFormat, string> = { csv: 'csv', xlsx: 'xlsx', pdf: 'pdf' }

function filenameFromHeader(header: unknown, fallback: string): string {
  if (typeof header !== 'string') return fallback
  const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(header)
  return match ? decodeURIComponent(match[1]) : fallback
}

/**
 * GET /api/reports/{type}?format=... — téléchargement direct depuis React (§20/§34). Le backend
 * applique déjà tous les filtres (§19 : jamais un filtrage React après coup sur une liste
 * complète) ; ce client se contente de déclencher l'enregistrement du fichier renvoyé.
 */
export async function downloadReport(type: ReportType, format: ReportFormat, filters: ReportFilters): Promise<void> {
  try {
    const response = await http.get(`/reports/${type}`, {
      params: { format, ...filters },
      responseType: 'blob',
    })

    const fallbackName = `${type}-${new Date().toISOString().slice(0, 10)}.${EXTENSION[format]}`
    const filename = filenameFromHeader(response.headers['content-disposition'], fallbackName)

    const url = URL.createObjectURL(response.data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (err) {
    throw await normalizeBlobError(err)
  }
}

/**
 * Une erreur sur une requête `responseType: 'blob'` revient elle-même sous forme de Blob (le
 * plus souvent du JSON de validation Laravel) — il faut la relire en texte avant de pouvoir en
 * extraire un message exploitable par `toApiError`, sinon l'utilisateur verrait un message
 * technique brut (§32 : "ne jamais afficher une erreur technique brute").
 */
async function normalizeBlobError(err: unknown): Promise<NormalizedError> {
  if (axios.isAxiosError(err) && err.response?.data instanceof Blob) {
    try {
      const text = await err.response.data.text()
      err.response.data = JSON.parse(text)
    } catch {
      // Corps non-JSON (ex. erreur serveur brute) : toApiError() produira un message générique.
    }
  }

  return toApiError(err)
}

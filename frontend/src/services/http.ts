import axios, { AxiosError } from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL as string

/**
 * Client Axios centralisé pour toutes les ressources sous /api/*.
 *
 * withCredentials + withXSRFToken : nécessaires pour l'authentification SPA Sanctum
 * (cookie httpOnly de session + cookie XSRF-TOKEN lu par Axios et renvoyé dans l'en-tête
 * X-XSRF-TOKEN). `withXSRFToken` est indispensable ici car frontend (localhost:5173) et
 * backend (localhost:8000) sont deux origines différentes : depuis Axios 1.6, l'envoi
 * automatique de l'en-tête XSRF est restreint au same-origin sauf activation explicite.
 */
export const http = axios.create({
  baseURL: `${API_BASE_URL}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

/** Pose le cookie XSRF-TOKEN. À appeler avant tout login (flux Sanctum SPA). */
export function fetchCsrfCookie(): Promise<unknown> {
  return axios.get(`${API_BASE_URL}/sanctum/csrf-cookie`, { withCredentials: true })
}

export interface NormalizedError {
  status: number | null
  message: string
  errors?: Record<string, string[]>
  // Enveloppe structurée du service IA relayée par Laravel (voir AiServiceException /
  // ProductionPredictionController::store()) — ex. "TRAINING_REQUIRED", "UNKNOWN_CROP",
  // "MODEL_INACTIVE" (cahier des charges §9). Absent pour toute autre erreur (validation,
  // permissions...), jamais un code générique inventé.
  code?: string
  cropKey?: string
}

interface AiErrorBody {
  message?: string
  errors?: Record<string, string[]>
  success?: false
  error?: { code?: string; message?: string; crop_key?: string }
}

/** Convertit une erreur Axios/inconnue en forme exploitable par les formulaires et l'UI. */
export function toApiError(error: unknown): NormalizedError {
  if (axios.isAxiosError(error)) {
    const err = error as AxiosError<AiErrorBody>
    const status = err.response?.status ?? null
    const body = err.response?.data

    if (status === 422 && body?.errors) {
      return { status, message: body.message ?? 'Erreur de validation.', errors: body.errors }
    }

    // Enveloppe structurée relayée depuis le service IA (§9) — prioritaire sur `message` : elle
    // porte un `code` exploitable par l'UI (ex. afficher "Entraîner le modèle" sur
    // TRAINING_REQUIRED) plutôt qu'un texte libre à analyser au jugé.
    if (body?.error?.message) {
      return { status, message: body.error.message, code: body.error.code, cropKey: body.error.crop_key }
    }

    if (body?.message) {
      return { status, message: body.message }
    }

    if (status === null) {
      return { status: null, message: "Impossible de joindre le serveur. Vérifiez votre connexion." }
    }

    return { status, message: `Erreur inattendue (${status}).` }
  }

  return { status: null, message: 'Erreur inattendue.' }
}

type UnauthorizedHandler = () => void
let unauthorizedHandler: UnauthorizedHandler | null = null

/** Enregistré une seule fois par AuthProvider pour réagir à une session expirée (401). */
export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
  unauthorizedHandler = handler
}

http.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    if (axios.isAxiosError(error) && error.response?.status === 401 && !error.config?.url?.endsWith('/login')) {
      unauthorizedHandler?.()
    }
    return Promise.reject(error)
  },
)

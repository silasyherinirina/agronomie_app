import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import {
  getNotifications,
  getUnreadCount,
  markAllAsRead as markAllAsReadRequest,
  markAsRead as markAsReadRequest,
} from '../services/notificationService'
import { useAuth } from './useAuth'
import type { AppNotification } from '../types/api'

/** Nombre de notifications récentes chargées pour le dropdown de la cloche (§9 : jamais la
 * liste complète juste pour un aperçu). La page /notifications utilise sa propre pagination. */
const RECENT_LIMIT = 8

/** Pas de websockets/queue en place (§21) : polling léger du seul compteur, pas de la liste. */
const POLL_INTERVAL_MS = 45_000

interface NotificationContextValue {
  notifications: AppNotification[]
  unreadCount: number
  loading: boolean
  error: string | null
  /** Recharge la liste récente + le compteur (ex. à l'ouverture du dropdown). */
  refresh: () => void
  markAsRead: (id: string) => Promise<void>
  markAllAsRead: () => Promise<void>
}

const NotificationContext = createContext<NotificationContextValue | null>(null)

export function NotificationProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const [notifications, setNotifications] = useState<AppNotification[]>([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const refresh = useCallback(() => {
    if (!user) return
    setLoading(true)
    setError(null)
    Promise.all([getNotifications({ per_page: RECENT_LIMIT }), getUnreadCount()])
      .then(([page, count]) => {
        setNotifications(page.data)
        setUnreadCount(count)
      })
      .catch(() => setError('Impossible de charger les notifications.'))
      .finally(() => setLoading(false))
  }, [user])

  // Chargement initial dès qu'un utilisateur est authentifié ; état remis à zéro à la déconnexion
  // (pas de notification résiduelle d'un compte précédent sur un poste partagé).
  useEffect(() => {
    if (user) {
      refresh()
    } else {
      setNotifications([])
      setUnreadCount(0)
      setError(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.id])

  // Polling léger : uniquement le compteur, uniquement si connecté, nettoyé au démontage/à la
  // déconnexion (pas de fuite mémoire ni d'appel réseau pour un visiteur anonyme).
  useEffect(() => {
    if (!user) return

    const interval = setInterval(() => {
      getUnreadCount()
        .then(setUnreadCount)
        .catch(() => {
          // Un échec ponctuel de polling ne doit ni faire disparaître le badge courant ni
          // planter l'application — nouvelle tentative au prochain tick.
        })
    }, POLL_INTERVAL_MS)

    return () => clearInterval(interval)
  }, [user])

  const markAsRead = useCallback(
    async (id: string) => {
      const target = notifications.find((n) => n.id === id)
      if (!target || target.is_read) return

      // Optimiste : badge et liste réagissent avant la confirmation serveur (§10).
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: true, read_at: new Date().toISOString() } : n)),
      )
      setUnreadCount((prev) => Math.max(0, prev - 1))

      try {
        await markAsReadRequest(id)
      } catch {
        // Rollback si l'API refuse la mise à jour (notification déjà supprimée, session expirée...).
        setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, is_read: false, read_at: null } : n)))
        setUnreadCount((prev) => prev + 1)
      }
    },
    [notifications],
  )

  const markAllAsRead = useCallback(async () => {
    const previousNotifications = notifications
    const previousCount = unreadCount

    setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true, read_at: n.read_at ?? new Date().toISOString() })))
    setUnreadCount(0)

    try {
      await markAllAsReadRequest()
    } catch {
      setNotifications(previousNotifications)
      setUnreadCount(previousCount)
    }
  }, [notifications, unreadCount])

  const value = useMemo(
    () => ({ notifications, unreadCount, loading, error, refresh, markAsRead, markAllAsRead }),
    [notifications, unreadCount, loading, error, refresh, markAsRead, markAllAsRead],
  )

  return <NotificationContext.Provider value={value}>{children}</NotificationContext.Provider>
}

export function useNotifications(): NotificationContextValue {
  const ctx = useContext(NotificationContext)
  if (!ctx) {
    throw new Error("useNotifications doit être utilisé à l'intérieur de <NotificationProvider>.")
  }
  return ctx
}

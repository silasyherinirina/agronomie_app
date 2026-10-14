import { X } from 'lucide-react'
import { Button } from '../../components/Button'
import { notificationIcon } from './notificationIcon'
import type { AppNotification } from '../../types/api'

interface NotificationDetailModalProps {
  notification: AppNotification
  onClose: () => void
  onNavigate: (route: string) => void
}

const TYPE_LABELS: Record<string, string> = {
  harvest: 'Récolte',
  sale: 'Vente',
  prediction: 'Prévision IA',
  campaign: 'Campagne agricole',
  weather: 'Météo',
  system: 'Système',
}

function formatFullDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Détail d'une notification (§17). Ouvert toujours après le marquage "lu" côté appelant
 * (NotificationsPage/AppLayout) — ce composant reste purement présentation, sans appel API.
 */
export function NotificationDetailModal({ notification, onClose, onNavigate }: NotificationDetailModalProps) {
  const Icon = notificationIcon(notification.type)
  const typeLabel = notification.type ? (TYPE_LABELS[notification.type] ?? notification.type) : null

  return (
    <div role="dialog" aria-modal="true" className="fixed inset-0 z-[100] flex items-center justify-center bg-black/45 p-4">
      <div className="w-full max-w-md rounded-lg bg-surface p-6 shadow-md">
        <div className="mb-3 flex items-start justify-between gap-3">
          <div className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-primary-light text-primary-dark">
              <Icon size={18} aria-hidden />
            </span>
            {typeLabel && <span className="text-xs font-semibold uppercase tracking-wide text-muted">{typeLabel}</span>}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Fermer"
            className="rounded-md p-1 text-muted hover:bg-canvas hover:text-ink"
          >
            <X size={18} aria-hidden />
          </button>
        </div>

        <h3 className="text-base font-semibold text-ink">{notification.title}</h3>
        {notification.message && <p className="mt-2 text-sm text-muted">{notification.message}</p>}
        <p className="mt-4 text-xs text-muted">{formatFullDate(notification.created_at)}</p>

        <div className="form-actions">
          <Button variant="secondary" onClick={onClose}>
            Fermer
          </Button>
          {notification.route && <Button onClick={() => onNavigate(notification.route as string)}>Consulter</Button>}
        </div>
      </div>
    </div>
  )
}

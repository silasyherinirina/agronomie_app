import { useCallback, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Bell } from 'lucide-react'
import { PageHeader } from '../../components/PageHeader'
import { Pagination } from '../../components/Pagination'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { useResourceList } from '../../hooks/useResourceList'
import { useNotifications } from '../../hooks/useNotifications'
import { getNotifications, markAsRead } from '../../services/notificationService'
import { formatRelativeTime } from '../../utils/relativeTime'
import { notificationIcon } from './notificationIcon'
import { NotificationDetailModal } from './NotificationDetailModal'
import type { AppNotification } from '../../types/api'

type StatusFilter = 'all' | 'unread'

/**
 * Page "Toutes les notifications" (§16/§17) : historique paginé, filtrable, avec détail par
 * clic — alimentée par les mêmes endpoints réels que le dropdown de la topbar
 * (voir AppLayout > NotificationsMenu), jamais de données simulées.
 */
export function NotificationsPage() {
  const navigate = useNavigate()
  const { markAllAsRead, refresh: refreshBell } = useNotifications()
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all')
  const [selected, setSelected] = useState<AppNotification | null>(null)
  const [markingAll, setMarkingAll] = useState(false)

  const list = useCallback(
    (params: Record<string, unknown>) =>
      getNotifications({
        page: params.page as number | undefined,
        per_page: params.per_page as number | undefined,
        status: statusFilter === 'unread' ? 'unread' : undefined,
      }),
    [statusFilter],
  )
  const { data, meta, loading, error, setParam, refetch } = useResourceList<AppNotification>(list, {
    page: 1,
    per_page: 15,
  })

  function selectFilter(next: StatusFilter) {
    setStatusFilter(next)
    setParam('page', 1)
  }

  async function handleMarkAllAsRead() {
    setMarkingAll(true)
    try {
      await markAllAsRead()
      refetch()
    } finally {
      setMarkingAll(false)
    }
  }

  async function handleSelect(notification: AppNotification) {
    setSelected(notification)
    if (!notification.is_read) {
      await markAsRead(notification.id)
      refetch()
      refreshBell()
    }
  }

  function handleNavigate(route: string) {
    setSelected(null)
    navigate(route)
  }

  const hasUnread = data.some((n) => !n.is_read)

  return (
    <div>
      <PageHeader
        title="Notifications"
        description="Consultez les événements et informations importants concernant votre activité agricole."
        action={
          <Button variant="secondary" onClick={() => void handleMarkAllAsRead()} loading={markingAll} disabled={!hasUnread}>
            Tout marquer comme lu
          </Button>
        }
      />

      <div className="mb-4 inline-flex rounded-lg border border-line bg-surface p-1">
        {(
          [
            { key: 'all', label: 'Toutes' },
            { key: 'unread', label: 'Non lues' },
          ] as const
        ).map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => selectFilter(tab.key)}
            className={[
              'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
              statusFilter === tab.key ? 'bg-primary-light text-primary-dark' : 'text-muted hover:text-ink',
            ].join(' ')}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {error ? (
        <Alert tone="danger">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <span>{error.message}</span>
            <Button variant="secondary" onClick={refetch}>
              Réessayer
            </Button>
          </div>
        </Alert>
      ) : loading ? (
        <div className="flex justify-center py-12">
          <Spinner />
        </div>
      ) : data.length === 0 ? (
        <div className="rounded-lg border border-line bg-surface py-16 text-center">
          <Bell size={28} className="mx-auto mb-3 text-muted" aria-hidden />
          <p className="font-medium text-ink">Aucune notification</p>
          <p className="mt-1 text-sm text-muted">Vous êtes à jour.</p>
        </div>
      ) : (
        <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
          {data.map((notification) => {
            const Icon = notificationIcon(notification.type)
            return (
              <li key={notification.id}>
                <button
                  type="button"
                  onClick={() => void handleSelect(notification)}
                  className={[
                    'flex w-full items-start gap-3 px-4 py-4 text-left transition-colors hover:bg-canvas',
                    !notification.is_read ? 'bg-primary-light' : '',
                  ].join(' ')}
                >
                  <span
                    aria-hidden
                    className={[
                      'mt-1.5 h-2 w-2 flex-shrink-0 rounded-full',
                      !notification.is_read ? 'bg-primary' : 'border border-line',
                    ].join(' ')}
                  />
                  <span className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-canvas text-primary-dark">
                    <Icon size={16} aria-hidden />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className={`block text-sm text-ink ${!notification.is_read ? 'font-semibold' : 'font-medium'}`}>
                      {notification.title}
                    </span>
                    {notification.message && <span className="mt-0.5 block text-sm text-muted">{notification.message}</span>}
                    <span className="mt-1 block text-xs text-muted">{formatRelativeTime(notification.created_at)}</span>
                  </span>
                </button>
              </li>
            )
          })}
        </ul>
      )}

      <Pagination meta={meta} onPageChange={(page) => setParam('page', page)} />

      {selected && (
        <NotificationDetailModal notification={selected} onClose={() => setSelected(null)} onNavigate={handleNavigate} />
      )}
    </div>
  )
}

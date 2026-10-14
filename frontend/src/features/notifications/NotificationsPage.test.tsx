import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { NotificationsPage } from './NotificationsPage'
import * as useNotificationsModule from '../../hooks/useNotifications'
import type { AppNotification, Paginated } from '../../types/api'

const notificationServiceMocks = vi.hoisted(() => ({
  getNotifications: vi.fn(),
  markAsRead: vi.fn(),
}))

vi.mock('../../services/notificationService', () => notificationServiceMocks)
vi.mock('../../hooks/useNotifications')

function emptyPage(data: AppNotification[] = []): Paginated<AppNotification> {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: data.length ? 1 : null, last_page: 1, per_page: 15, to: data.length || null, total: data.length },
  }
}

function makeNotification(overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id: 'n1',
    type: 'harvest',
    title: 'Récolte enregistrée',
    message: 'La récolte de riz de la parcelle « Parcelle test » a été enregistrée.',
    severity: 'info',
    icon: 'wheat',
    route: '/harvests',
    entity_type: 'harvest',
    entity_id: 1,
    metadata: {},
    is_read: false,
    read_at: null,
    created_at: new Date().toISOString(),
    ...overrides,
  }
}

const notificationsHookDefaults = {
  notifications: [],
  unreadCount: 0,
  loading: false,
  error: null,
  refresh: vi.fn(),
  markAsRead: vi.fn(),
  markAllAsRead: vi.fn(),
}

function renderPage() {
  return render(
    <MemoryRouter>
      <NotificationsPage />
    </MemoryRouter>,
  )
}

describe('NotificationsPage', () => {
  it('shows a loading state before the list resolves', () => {
    notificationServiceMocks.getNotifications.mockReturnValue(new Promise(() => {}))
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue(notificationsHookDefaults)

    renderPage()

    expect(screen.getByRole('status')).toBeInTheDocument()
  })

  it('renders the list and visually distinguishes an unread notification', async () => {
    notificationServiceMocks.getNotifications.mockResolvedValue(
      emptyPage([makeNotification(), makeNotification({ id: 'n2', title: 'Vente enregistrée', is_read: true, read_at: new Date().toISOString() })]),
    )
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue(notificationsHookDefaults)

    renderPage()

    await waitFor(() => expect(screen.getByText('Récolte enregistrée')).toBeInTheDocument())
    expect(screen.getByText('Vente enregistrée')).toBeInTheDocument()
  })

  it('shows an empty state when there are no notifications', async () => {
    notificationServiceMocks.getNotifications.mockResolvedValue(emptyPage())
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue(notificationsHookDefaults)

    renderPage()

    await waitFor(() => expect(screen.getByText('Aucune notification')).toBeInTheDocument())
  })

  it('shows an error state with a retry action', async () => {
    notificationServiceMocks.getNotifications.mockRejectedValue({ isAxiosError: true, response: { status: 500, data: {} } })
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue(notificationsHookDefaults)

    renderPage()

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument())
    expect(screen.getByText('Réessayer')).toBeInTheDocument()
  })

  it('marks a notification as read and opens its detail on click', async () => {
    const user = userEvent.setup()
    notificationServiceMocks.getNotifications.mockResolvedValue(emptyPage([makeNotification()]))
    notificationServiceMocks.markAsRead.mockResolvedValue(makeNotification({ is_read: true }))
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue(notificationsHookDefaults)

    renderPage()

    await waitFor(() => expect(screen.getByText('Récolte enregistrée')).toBeInTheDocument())
    await user.click(screen.getByText('Récolte enregistrée'))

    expect(notificationServiceMocks.markAsRead).toHaveBeenCalledWith('n1')
    // Le détail (modal) affiche l'action "Consulter" pour une notification avec une route.
    expect(await screen.findByText('Consulter')).toBeInTheDocument()
  })

  it('calls markAllAsRead when the header action is clicked', async () => {
    const user = userEvent.setup()
    const markAllAsRead = vi.fn().mockResolvedValue(undefined)
    notificationServiceMocks.getNotifications.mockResolvedValue(emptyPage([makeNotification()]))
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue({ ...notificationsHookDefaults, markAllAsRead })

    renderPage()

    await waitFor(() => expect(screen.getByText('Récolte enregistrée')).toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: 'Tout marquer comme lu' }))

    expect(markAllAsRead).toHaveBeenCalled()
  })

  it('switches to the "unread" filter and re-fetches with the matching status', async () => {
    const user = userEvent.setup()
    notificationServiceMocks.getNotifications.mockResolvedValue(emptyPage([makeNotification()]))
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue(notificationsHookDefaults)

    renderPage()

    await waitFor(() => expect(screen.getByText('Récolte enregistrée')).toBeInTheDocument())
    await user.click(screen.getByRole('button', { name: 'Non lues' }))

    await waitFor(() =>
      expect(notificationServiceMocks.getNotifications).toHaveBeenLastCalledWith(
        expect.objectContaining({ status: 'unread' }),
      ),
    )
  })
})

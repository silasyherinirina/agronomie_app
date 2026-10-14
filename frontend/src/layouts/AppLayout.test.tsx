import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { AppLayout } from './AppLayout'
import * as useAuthModule from '../hooks/useAuth'
import * as useNotificationsModule from '../hooks/useNotifications'
import type { AppNotification, User } from '../types/api'

vi.mock('../hooks/useAuth')
// Hors périmètre de ce test (navigation par rôle + bannière e-mail) : la cloche est vérifiée
// séparément (voir un futur test dédié aux notifications). Mockée ici pour ne déclencher aucun
// appel réseau réel et garder ce test isolé/rapide.
vi.mock('../hooks/useNotifications')

const authDefaults = {
  loading: false,
  sessionExpired: false,
  clearSessionExpired: vi.fn(),
  login: vi.fn(),
  register: vi.fn(),
  logout: vi.fn(),
  setUser: vi.fn(),
}

const notificationsDefaults = {
  notifications: [],
  unreadCount: 0,
  loading: false,
  error: null,
  refresh: vi.fn(),
  markAsRead: vi.fn(),
  markAllAsRead: vi.fn(),
}

beforeEach(() => {
  vi.mocked(useNotificationsModule.useNotifications).mockReturnValue(notificationsDefaults)
})

function mockUser(overrides: Partial<User>): User {
  return {
    id: 1,
    name: 'Test',
    email: 't@example.com',
    role: 'admin',
    farmer_id: null,
    managed_cooperative_id: null,
    email_verified_at: '2026-08-31T00:00:00.000000Z',
    created_at: '',
    ...overrides,
  }
}

function renderLayout() {
  return render(
    <MemoryRouter initialEntries={['/']}>
      <Routes>
        <Route path="/" element={<AppLayout />}>
          <Route index element={<div>Contenu</div>} />
        </Route>
        {/* Cible de navigation pour les tests de la cloche (clic sur une notification). */}
        <Route path="/harvests" element={<div>Page Récoltes</div>} />
      </Routes>
    </MemoryRouter>,
  )
}

// Phase D, §8 — la navigation doit s'adapter au rôle. Le contrôle réel (Policies) reste côté
// Laravel : ce test vérifie seulement le confort d'affichage du menu, pas une frontière de
// sécurité (voir le commentaire dans AppLayout.tsx).
describe('AppLayout navigation', () => {
  it('shows management-oriented links to an admin', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({ role: 'admin' }) })
    renderLayout()

    expect(screen.getByRole('link', { name: 'Coopératives' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Agriculteurs' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Intrants' })).toBeInTheDocument()
  })

  it('hides management-oriented links from a farmer', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({
      ...authDefaults,
      user: mockUser({ role: 'farmer', farmer_id: 1 }),
    })
    renderLayout()

    expect(screen.queryByRole('link', { name: 'Coopératives' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Agriculteurs' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Intrants' })).not.toBeInTheDocument()
    // Toujours visibles pour un agriculteur : ses propres données.
    expect(screen.getByRole('link', { name: 'Parcelles' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Récoltes' })).toBeInTheDocument()
  })

  // La bannière "e-mail non vérifié" a été retirée du layout (audit du 12/09) : le centre de
  // notifications est désormais le seul canal d'information au-dessus du contenu de page, quel
  // que soit l'état de vérification du compte. Le backend (email_verified_at, renvoi de
  // l'e-mail) reste inchangé — seul cet affichage a disparu.
  it('never shows the old unverified-email banner, verified or not', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({
      ...authDefaults,
      user: mockUser({ email_verified_at: null }),
    })
    renderLayout()

    expect(screen.queryByText(/n'est pas encore vérifiée/i)).not.toBeInTheDocument()
    expect(screen.queryByText("Renvoyer l'e-mail")).not.toBeInTheDocument()
  })
})

function mockNotification(overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id: 'n1',
    type: 'harvest',
    title: 'Récolte enregistrée',
    message: 'La récolte de riz a été enregistrée.',
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

describe('NotificationsMenu (cloche)', () => {
  it('hides the unread badge when there is nothing unread', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    renderLayout()

    expect(screen.getByRole('button', { name: 'Notifications' })).toBeInTheDocument()
    expect(screen.queryByText('0')).not.toBeInTheDocument()
  })

  it('shows the unread count, capped at "9+"', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue({ ...notificationsDefaults, unreadCount: 12 })
    renderLayout()

    expect(screen.getByText('9+')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Notifications, 12 non lues' })).toBeInTheDocument()
  })

  it('opens the dropdown and lists recent notifications', async () => {
    const user = userEvent.setup()
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue({
      ...notificationsDefaults,
      notifications: [mockNotification()],
      unreadCount: 1,
    })
    renderLayout()

    await user.click(screen.getByRole('button', { name: 'Notifications, 1 non lues' }))

    expect(screen.getByText('Récolte enregistrée')).toBeInTheDocument()
  })

  it('marks an unread notification as read and navigates to its route on click', async () => {
    const user = userEvent.setup()
    const markAsRead = vi.fn().mockResolvedValue(undefined)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue({
      ...notificationsDefaults,
      notifications: [mockNotification()],
      unreadCount: 1,
      markAsRead,
    })
    renderLayout()

    await user.click(screen.getByRole('button', { name: 'Notifications, 1 non lues' }))
    await user.click(screen.getByText('Récolte enregistrée'))

    expect(markAsRead).toHaveBeenCalledWith('n1')
    expect(await screen.findByText('Page Récoltes')).toBeInTheDocument()
  })

  it('navigates without calling markAsRead again for an already-read notification', async () => {
    const user = userEvent.setup()
    const markAsRead = vi.fn()
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue({
      ...notificationsDefaults,
      notifications: [mockNotification({ is_read: true, read_at: new Date().toISOString() })],
      markAsRead,
    })
    renderLayout()

    await user.click(screen.getByRole('button', { name: 'Notifications' }))
    await user.click(screen.getByText('Récolte enregistrée'))

    expect(markAsRead).not.toHaveBeenCalled()
    expect(await screen.findByText('Page Récoltes')).toBeInTheDocument()
  })

  it('calls markAllAsRead when "Tout marquer comme lu" is clicked', async () => {
    const user = userEvent.setup()
    const markAllAsRead = vi.fn().mockResolvedValue(undefined)
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue({
      ...notificationsDefaults,
      notifications: [mockNotification()],
      unreadCount: 1,
      markAllAsRead,
    })
    renderLayout()

    await user.click(screen.getByRole('button', { name: 'Notifications, 1 non lues' }))
    await user.click(screen.getByText('Tout marquer comme lu'))

    expect(markAllAsRead).toHaveBeenCalled()
  })

  it('shows an empty state when there is nothing to display', async () => {
    const user = userEvent.setup()
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    renderLayout()

    await user.click(screen.getByRole('button', { name: 'Notifications' }))

    expect(screen.getByText('Aucune notification')).toBeInTheDocument()
  })

  it('shows an error state with a retry action', async () => {
    const user = userEvent.setup()
    const refresh = vi.fn()
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue({
      ...notificationsDefaults,
      error: 'Impossible de charger les notifications.',
      refresh,
    })
    renderLayout()

    await user.click(screen.getByRole('button', { name: 'Notifications' }))

    expect(screen.getByText('Impossible de charger les notifications.')).toBeInTheDocument()
    await user.click(screen.getByText('Réessayer'))
    expect(refresh).toHaveBeenCalled()
  })

  it('closes the dropdown when Escape is pressed', async () => {
    const user = userEvent.setup()
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: mockUser({}) })
    vi.mocked(useNotificationsModule.useNotifications).mockReturnValue({
      ...notificationsDefaults,
      notifications: [mockNotification()],
      unreadCount: 1,
    })
    renderLayout()

    await user.click(screen.getByRole('button', { name: 'Notifications, 1 non lues' }))
    expect(screen.getByRole('menu', { name: 'Notifications' })).toBeInTheDocument()

    await user.keyboard('{Escape}')
    expect(screen.queryByRole('menu', { name: 'Notifications' })).not.toBeInTheDocument()
  })
})

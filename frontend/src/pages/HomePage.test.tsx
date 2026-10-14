import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { HomePage } from './HomePage'
import { AuthProvider } from '../hooks/useAuth'
import * as authService from '../services/auth'

vi.mock('../services/auth')

function renderHome() {
  return render(
    <MemoryRouter initialEntries={['/']}>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/dashboard" element={<div>Tableau de bord</div>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

// Régression directe de la demande d'audit : "/" ne doit plus rediriger automatiquement vers
// /login et doit afficher une page publique.
describe('HomePage', () => {
  beforeEach(() => {
    vi.mocked(authService.fetchCurrentUser).mockRejectedValue(new Error('not authenticated'))
  })

  it('shows the public landing page to an unauthenticated visitor without redirecting to /login', async () => {
    renderHome()

    await waitFor(() => expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument())
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent(/pilotez votre agriculture/i)
    expect(screen.getAllByRole('link', { name: /connexion/i }).length).toBeGreaterThan(0)
    expect(screen.getAllByRole('link', { name: /créer un compte/i }).length).toBeGreaterThan(0)
  })

  it('redirects an already authenticated visitor to /dashboard', async () => {
    vi.mocked(authService.fetchCurrentUser).mockResolvedValue({
      id: 1,
      name: 'Admin',
      email: 'admin@example.com',
      role: 'admin',
      farmer_id: null,
      managed_cooperative_id: null,
      email_verified_at: '2026-08-31T00:00:00.000000Z',
      created_at: '',
    })

    renderHome()

    await waitFor(() => expect(screen.getByText('Tableau de bord')).toBeInTheDocument())
  })
})

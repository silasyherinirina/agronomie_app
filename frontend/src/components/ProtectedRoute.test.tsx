import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { ProtectedRoute } from './ProtectedRoute'
import * as useAuthModule from '../hooks/useAuth'
import type { User } from '../types/api'

vi.mock('../hooks/useAuth')

function renderProtected(allow?: User['role'][]) {
  return render(
    <MemoryRouter initialEntries={['/secret']}>
      <Routes>
        <Route path="/login" element={<div>Page de connexion</div>} />
        <Route path="/dashboard" element={<div>Accueil</div>} />
        <Route
          path="/secret"
          element={
            <ProtectedRoute allow={allow}>
              <div>Contenu protégé</div>
            </ProtectedRoute>
          }
        />
      </Routes>
    </MemoryRouter>,
  )
}

// Champs communs du contrat AuthContextValue, complétés dans chaque mock par les cas testés.
const authDefaults = {
  sessionExpired: false,
  clearSessionExpired: vi.fn(),
  login: vi.fn(),
  register: vi.fn(),
  logout: vi.fn(),
  setUser: vi.fn(),
}

describe('ProtectedRoute', () => {
  it('shows a loading state while the session check is in progress', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: null, loading: true })
    renderProtected();
    expect(screen.getByText(/vérification de la session/i)).toBeInTheDocument()
  })

  it('redirects to /login when there is no authenticated user', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({ ...authDefaults, user: null, loading: false })
    renderProtected();
    expect(screen.getByText('Page de connexion')).toBeInTheDocument()
  })

  it('renders the protected content once authenticated', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({
      ...authDefaults,
      user: {
        id: 1,
        name: 'A',
        email: 'a@a.com',
        role: 'admin',
        farmer_id: null,
        managed_cooperative_id: null,
        email_verified_at: '',
        created_at: '',
      },
      loading: false,
    })
    renderProtected()
    expect(screen.getByText('Contenu protégé')).toBeInTheDocument()
  })

  it('redirects to the dashboard when the role is not in the allowed list', () => {
    vi.mocked(useAuthModule.useAuth).mockReturnValue({
      ...authDefaults,
      user: {
        id: 1,
        name: 'F',
        email: 'f@a.com',
        role: 'farmer',
        farmer_id: 1,
        managed_cooperative_id: null,
        email_verified_at: '',
        created_at: '',
      },
      loading: false,
    })
    renderProtected(['admin'])
    expect(screen.getByText('Accueil')).toBeInTheDocument()
  })
})

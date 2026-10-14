import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RegisterPage } from './RegisterPage'
import { AuthProvider } from '../hooks/useAuth'
import * as authService from '../services/auth'
import type { User } from '../types/api'

vi.mock('../services/auth')

const FAKE_USER: User = {
  id: 2,
  name: 'Nouvel Utilisateur',
  email: 'nouveau@example.com',
  role: 'farmer',
  farmer_id: null,
  managed_cooperative_id: null,
  email_verified_at: null,
  created_at: '2026-08-31T00:00:00.000000Z',
}

function renderRegisterPage() {
  return render(
    <MemoryRouter initialEntries={['/register']}>
      <AuthProvider>
        <Routes>
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/dashboard" element={<div>Tableau de bord</div>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('RegisterPage', () => {
  beforeEach(() => {
    vi.mocked(authService.fetchCurrentUser).mockRejectedValue(new Error('not authenticated'))
  })

  it('registers and redirects to the dashboard, without ever asking for a role', async () => {
    vi.mocked(authService.register).mockResolvedValue(FAKE_USER)
    const user = userEvent.setup()

    renderRegisterPage()

    await waitFor(() => expect(screen.getByLabelText(/nom complet/i)).toBeInTheDocument())
    expect(screen.queryByLabelText(/rôle/i)).not.toBeInTheDocument()

    await user.type(screen.getByLabelText(/nom complet/i), 'Nouvel Utilisateur')
    await user.type(screen.getByLabelText(/adresse e-mail/i), 'nouveau@example.com')
    await user.type(screen.getByLabelText(/^mot de passe/i), 'motdepasse123')
    await user.type(screen.getByLabelText(/confirmer le mot de passe/i), 'motdepasse123')
    await user.click(screen.getByRole('button', { name: /créer mon compte/i }))

    await waitFor(() => expect(screen.getByText('Tableau de bord')).toBeInTheDocument())
    expect(authService.register).toHaveBeenCalledWith({
      name: 'Nouvel Utilisateur',
      email: 'nouveau@example.com',
      password: 'motdepasse123',
      password_confirmation: 'motdepasse123',
    })
  })

  it('shows field-level validation errors from a 422 response', async () => {
    vi.mocked(authService.register).mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { message: 'Erreur de validation.', errors: { email: ['Cette adresse est déjà utilisée.'] } },
      },
    })
    const user = userEvent.setup()

    renderRegisterPage()

    await waitFor(() => expect(screen.getByLabelText(/nom complet/i)).toBeInTheDocument())
    await user.type(screen.getByLabelText(/nom complet/i), 'X')
    await user.type(screen.getByLabelText(/adresse e-mail/i), 'existe@example.com')
    await user.type(screen.getByLabelText(/^mot de passe/i), 'motdepasse123')
    await user.type(screen.getByLabelText(/confirmer le mot de passe/i), 'motdepasse123')
    await user.click(screen.getByRole('button', { name: /créer mon compte/i }))

    expect(await screen.findByText('Cette adresse est déjà utilisée.')).toBeInTheDocument()
    expect(screen.queryByText('Tableau de bord')).not.toBeInTheDocument()
  })
})

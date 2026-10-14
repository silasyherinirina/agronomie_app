import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LoginPage } from './LoginPage'
import { AuthProvider } from '../hooks/useAuth'
import * as authService from '../services/auth'
import type { User } from '../types/api'

vi.mock('../services/auth')

const FAKE_USER: User = {
  id: 1,
  name: 'Admin Test',
  email: 'admin@example.com',
  role: 'admin',
  farmer_id: null,
  managed_cooperative_id: null,
  email_verified_at: '2026-08-29T00:00:00.000000Z',
  created_at: '2026-08-29T00:00:00.000000Z',
}

function renderLoginPage() {
  return render(
    <MemoryRouter initialEntries={['/login']}>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/dashboard" element={<div>Tableau de bord</div>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>,
  )
}

describe('LoginPage', () => {
  beforeEach(() => {
    vi.mocked(authService.fetchCurrentUser).mockRejectedValue(new Error('not authenticated'))
  })

  it('logs in with valid credentials and redirects to the dashboard', async () => {
    vi.mocked(authService.login).mockResolvedValue(FAKE_USER)
    const user = userEvent.setup()

    renderLoginPage()

    await waitFor(() => expect(screen.getByLabelText(/e-mail/i)).toBeInTheDocument())

    await user.type(screen.getByLabelText(/e-mail/i), 'admin@example.com')
    await user.type(screen.getByLabelText(/mot de passe/i), 'password')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    await waitFor(() => expect(screen.getByText('Tableau de bord')).toBeInTheDocument())
    expect(authService.login).toHaveBeenCalledWith('admin@example.com', 'password')
  })

  it('shows the server error message and stays on the form when login fails', async () => {
    vi.mocked(authService.login).mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { message: 'Identifiants incorrects.' } },
    })
    const user = userEvent.setup()

    renderLoginPage()

    await waitFor(() => expect(screen.getByLabelText(/e-mail/i)).toBeInTheDocument())

    await user.type(screen.getByLabelText(/e-mail/i), 'admin@example.com')
    await user.type(screen.getByLabelText(/mot de passe/i), 'wrong-password')
    await user.click(screen.getByRole('button', { name: /se connecter/i }))

    expect(await screen.findByText('Identifiants incorrects.')).toBeInTheDocument()
    expect(screen.queryByText('Tableau de bord')).not.toBeInTheDocument()
  })
})

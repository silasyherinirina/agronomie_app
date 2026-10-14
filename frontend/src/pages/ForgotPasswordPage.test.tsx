import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { ForgotPasswordPage } from './ForgotPasswordPage'
import * as authService from '../services/auth'

vi.mock('../services/auth')

describe('ForgotPasswordPage', () => {
  it('shows the generic confirmation message returned by the API', async () => {
    vi.mocked(authService.forgotPassword).mockResolvedValue(
      "Si un compte existe avec cette adresse, un lien de réinitialisation vient d'être envoyé.",
    )
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <ForgotPasswordPage />
      </MemoryRouter>,
    )

    await user.type(screen.getByLabelText(/adresse e-mail/i), 'quelquun@example.com')
    await user.click(screen.getByRole('button', { name: /envoyer le lien/i }))

    await waitFor(() =>
      expect(
        screen.getByText("Si un compte existe avec cette adresse, un lien de réinitialisation vient d'être envoyé."),
      ).toBeInTheDocument(),
    )
    expect(authService.forgotPassword).toHaveBeenCalledWith('quelquun@example.com')
  })

  it('shows an error message when the request fails', async () => {
    vi.mocked(authService.forgotPassword).mockRejectedValue({
      isAxiosError: true,
      response: { status: 429, data: { message: 'Trop de tentatives, réessayez plus tard.' } },
    })
    const user = userEvent.setup()

    render(
      <MemoryRouter>
        <ForgotPasswordPage />
      </MemoryRouter>,
    )

    await user.type(screen.getByLabelText(/adresse e-mail/i), 'quelquun@example.com')
    await user.click(screen.getByRole('button', { name: /envoyer le lien/i }))

    expect(await screen.findByText('Trop de tentatives, réessayez plus tard.')).toBeInTheDocument()
  })
})

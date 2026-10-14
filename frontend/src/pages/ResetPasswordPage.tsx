import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { AuthLayout } from '../layouts/AuthLayout'
import { Button } from '../components/Button'
import { Alert } from '../components/Alert'
import { FormField } from '../components/FormField'
import { resetPassword } from '../services/auth'
import { toApiError, type NormalizedError } from '../services/http'

/** Page ouverte depuis le lien reçu par e-mail : ?token=...&email=... (Phase D). */
export function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<NormalizedError | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (!token || !email) {
    return (
      <AuthLayout>
        <Alert>Ce lien de réinitialisation est invalide ou incomplet.</Alert>
        <p className="mt-4 text-center text-sm">
          <Link to="/forgot-password" className="font-medium text-primary hover:underline">
            Demander un nouveau lien
          </Link>
        </p>
      </AuthLayout>
    )
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      await resetPassword({ token, email, password, password_confirmation: passwordConfirmation })
      navigate('/login', { replace: true, state: { notice: 'Mot de passe réinitialisé, vous pouvez vous connecter.' } })
    } catch (err) {
      setError(toApiError(err))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthLayout>
      <Link to="/login" className="mb-5 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition-colors hover:text-ink">
        <ArrowLeft size={16} aria-hidden />
        Retour à la connexion
      </Link>

      <h1 className="text-xl font-semibold text-ink">Réinitialiser le mot de passe</h1>
      <p className="mt-1 mb-6 text-sm text-muted">Nouveau mot de passe pour {email}</p>

      <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
        {error && <Alert>{error.message}</Alert>}
        <FormField
          label="Nouveau mot de passe"
          htmlFor="password"
          required
          hint="8 caractères minimum, au moins une lettre et un chiffre."
          errors={error?.errors?.password}
        >
          <input
            id="password"
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </FormField>
        <FormField label="Confirmer le mot de passe" htmlFor="password_confirmation" required>
          <input
            id="password_confirmation"
            type="password"
            autoComplete="new-password"
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
            required
          />
        </FormField>
        <Button type="submit" loading={submitting} className="w-full">
          Réinitialiser le mot de passe
        </Button>
      </form>
    </AuthLayout>
  )
}

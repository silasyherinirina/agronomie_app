import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { AuthLayout } from '../layouts/AuthLayout'
import { Button } from '../components/Button'
import { Alert } from '../components/Alert'
import { FormField } from '../components/FormField'
import { forgotPassword } from '../services/auth'
import { toApiError } from '../services/http'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      const responseMessage = await forgotPassword(email)
      setMessage(responseMessage)
    } catch (err) {
      setError(toApiError(err).message)
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

      <h1 className="text-xl font-semibold text-ink">Mot de passe oublié</h1>
      <p className="mt-1 mb-6 text-sm text-muted">
        Saisissez votre adresse e-mail : si un compte lui correspond, un lien de réinitialisation
        vous sera envoyé.
      </p>

      <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
        {message && <Alert tone="success">{message}</Alert>}
        {error && <Alert>{error}</Alert>}
        <FormField label="Adresse e-mail" htmlFor="email" required>
          <input
            id="email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </FormField>
        <Button type="submit" loading={submitting} className="w-full">
          Envoyer le lien de réinitialisation
        </Button>
        <p className="text-center text-sm">
          <Link to="/" className="font-medium text-primary hover:underline">
            Retour à l'accueil
          </Link>
        </p>
      </form>
    </AuthLayout>
  )
}

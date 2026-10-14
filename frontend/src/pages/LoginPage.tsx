import { useEffect, useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { AuthLayout } from '../layouts/AuthLayout'
import { Button } from '../components/Button'
import { Alert } from '../components/Alert'
import { FormField } from '../components/FormField'
import { useAuth } from '../hooks/useAuth'
import { toApiError } from '../services/http'

interface LocationState {
  from?: string
  notice?: string
  expired?: boolean
}

export function LoginPage() {
  const { user, login, clearSessionExpired } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const state = (location.state as LocationState | null) ?? null
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (state?.expired) clearSessionExpired()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  if (user) {
    return <Navigate to={state?.from ?? '/dashboard'} replace />
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      await login(email, password)
      navigate('/dashboard', { replace: true })
    } catch (err) {
      setError(toApiError(err).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthLayout>
      <Link to="/" className="mb-5 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition-colors hover:text-ink">
        <ArrowLeft size={16} aria-hidden />
        Retour à l'accueil
      </Link>

      <h1 className="text-xl font-semibold text-ink">Bienvenue sur Agriculture Mada</h1>
      <p className="mt-1 mb-6 text-sm text-muted">Connectez-vous à votre espace de gestion agricole.</p>

      <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
        {state?.expired && <Alert tone="warning">Votre session a expiré. Veuillez vous reconnecter.</Alert>}
        {state?.notice && <Alert tone="success">{state.notice}</Alert>}
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
        <FormField label="Mot de passe" htmlFor="password" required>
          <input
            id="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </FormField>
        <Button type="submit" loading={submitting} className="w-full">
          Se connecter
        </Button>
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
          <Link to="/forgot-password" className="font-medium text-primary hover:underline">
            Mot de passe oublié ?
          </Link>
          <Link to="/register" className="font-medium text-primary hover:underline">
            Créer un compte
          </Link>
        </div>
      </form>
    </AuthLayout>
  )
}

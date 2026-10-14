import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { AuthLayout } from '../layouts/AuthLayout'
import { Button } from '../components/Button'
import { Alert } from '../components/Alert'
import { FormField } from '../components/FormField'
import { useAuth } from '../hooks/useAuth'
import { toApiError, type NormalizedError } from '../services/http'

/**
 * Inscription publique (Phase D). Toujours créée avec le rôle 'farmer' côté serveur : aucun
 * choix de rôle n'est proposé ici (cahier des charges §17 — jamais de rôle décidé côté client).
 */
export function RegisterPage() {
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<NormalizedError | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (user) {
    return <Navigate to="/dashboard" replace />
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      await register({ name, email, password, password_confirmation: passwordConfirmation })
      navigate('/dashboard', { replace: true })
    } catch (err) {
      setError(toApiError(err))
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

      <h1 className="text-xl font-semibold text-ink">Créer un compte</h1>
      <p className="mt-1 mb-6 text-sm text-muted">Rejoignez votre espace de gestion agricole.</p>

      <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
        {error && <Alert>{error.message}</Alert>}
        <FormField label="Nom complet" htmlFor="name" required errors={error?.errors?.name}>
          <input id="name" type="text" autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} required />
        </FormField>
        <FormField label="Adresse e-mail" htmlFor="email" required errors={error?.errors?.email}>
          <input
            id="email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </FormField>
        <FormField
          label="Mot de passe"
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
          Créer mon compte
        </Button>
        <p className="text-center text-sm text-muted">
          Déjà un compte ?{' '}
          <Link to="/login" className="font-medium text-primary hover:underline">
            Se connecter
          </Link>
        </p>
      </form>
    </AuthLayout>
  )
}

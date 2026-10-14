import { useState, type FormEvent } from 'react'
import { useAuth } from '../hooks/useAuth'
import { Button } from '../components/Button'
import { Alert } from '../components/Alert'
import { Badge } from '../components/Badge'
import { FormField } from '../components/FormField'
import { PageHeader } from '../components/PageHeader'
import { updatePassword, updateProfile } from '../services/auth'
import { toApiError, type NormalizedError } from '../services/http'

const ROLE_LABELS: Record<string, string> = {
  admin: 'Administrateur',
  cooperative_manager: 'Responsable de coopérative',
  farmer: 'Agriculteur',
  analyst: 'Analyste',
}

/** Phase D, §9 — profil du compte connecté : infos personnelles + changement de mot de passe. */
export function ProfilePage() {
  const { user } = useAuth()
  if (!user) return null

  return (
    <div className="flex flex-col gap-5">
      <PageHeader title="Paramètres du compte" description="Informations personnelles et sécurité." />

      <div className="flex flex-col gap-3 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <strong>{user.name}</strong>
            <p className="m-0 text-sm text-muted">{user.email}</p>
          </div>
          <div className="flex flex-col items-end gap-1">
            <Badge label={ROLE_LABELS[user.role] ?? user.role} tone="neutral" />
            <Badge
              label={user.email_verified_at ? 'E-mail vérifié' : 'E-mail non vérifié'}
              tone={user.email_verified_at ? 'success' : 'warning'}
            />
          </div>
        </div>
        <p className="m-0 text-xs text-muted">
          Compte créé le {new Date(user.created_at).toLocaleDateString('fr-FR')}
        </p>
      </div>

      <ProfileInfoForm />
      <PasswordForm />
    </div>
  )
}

function ProfileInfoForm() {
  const { user, setUser } = useAuth()
  const [name, setName] = useState(user?.name ?? '')
  const [email, setEmail] = useState(user?.email ?? '')
  const [error, setError] = useState<NormalizedError | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)
    setSuccess(null)
    try {
      const updated = await updateProfile({ name, email })
      setUser(updated)
      setSuccess(
        updated.email !== user?.email
          ? 'Profil mis à jour. Un nouvel e-mail de vérification a été envoyé à votre nouvelle adresse.'
          : 'Profil mis à jour.',
      )
    } catch (err) {
      setError(toApiError(err))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm" onSubmit={handleSubmit}>
      <h3 className="text-base font-semibold text-ink">Informations personnelles</h3>
      {success && <Alert tone="success">{success}</Alert>}
      {error && <Alert>{error.message}</Alert>}
      <div className="form-grid">
        <FormField label="Nom complet" htmlFor="profile-name" required errors={error?.errors?.name}>
          <input id="profile-name" value={name} onChange={(e) => setName(e.target.value)} required />
        </FormField>
        <FormField label="Adresse e-mail" htmlFor="profile-email" required errors={error?.errors?.email}>
          <input
            id="profile-email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </FormField>
      </div>
      <div className="form-actions">
        <Button type="submit" loading={submitting}>
          Enregistrer
        </Button>
      </div>
    </form>
  )
}

function PasswordForm() {
  const [currentPassword, setCurrentPassword] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<NormalizedError | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)
    setSuccess(null)
    try {
      const message = await updatePassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      })
      setSuccess(message)
      setCurrentPassword('')
      setPassword('')
      setPasswordConfirmation('')
    } catch (err) {
      setError(toApiError(err))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <form className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm" onSubmit={handleSubmit}>
      <h3 className="text-base font-semibold text-ink">Changer le mot de passe</h3>
      {success && <Alert tone="success">{success}</Alert>}
      {error && <Alert>{error.message}</Alert>}
      <div className="form-grid">
        <FormField label="Mot de passe actuel" htmlFor="current-password" required errors={error?.errors?.current_password}>
          <input
            id="current-password"
            type="password"
            autoComplete="current-password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            required
          />
        </FormField>
        <FormField
          label="Nouveau mot de passe"
          htmlFor="new-password"
          required
          hint="8 caractères minimum, au moins une lettre et un chiffre."
          errors={error?.errors?.password}
        >
          <input
            id="new-password"
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </FormField>
        <FormField label="Confirmer le nouveau mot de passe" htmlFor="new-password-confirmation" required>
          <input
            id="new-password-confirmation"
            type="password"
            autoComplete="new-password"
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
            required
          />
        </FormField>
      </div>
      <div className="form-actions">
        <Button type="submit" variant="secondary" loading={submitting}>
          Changer le mot de passe
        </Button>
      </div>
    </form>
  )
}

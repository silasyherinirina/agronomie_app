import { Link } from 'react-router-dom'
import { CheckCircle2 } from 'lucide-react'
import { AuthLayout } from '../layouts/AuthLayout'
import { Button } from '../components/Button'

/** Page d'atterrissage après le lien de vérification (redirection backend, Phase D). */
export function EmailVerifiedPage() {
  return (
    <AuthLayout>
      <div className="flex flex-col items-center gap-3 py-2 text-center">
        <span className="flex h-12 w-12 items-center justify-center rounded-full bg-success-bg text-primary">
          <CheckCircle2 size={26} aria-hidden />
        </span>
        <h1 className="text-lg font-semibold text-ink">Adresse e-mail vérifiée</h1>
        <p className="text-sm text-muted">Votre adresse e-mail a bien été vérifiée. Vous pouvez accéder à votre espace.</p>
        <Link to="/dashboard" className="mt-2 w-full">
          <Button variant="primary" className="w-full">
            Retour à l'application
          </Button>
        </Link>
      </div>
    </AuthLayout>
  )
}

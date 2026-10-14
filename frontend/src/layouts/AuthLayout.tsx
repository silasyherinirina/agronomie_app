import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Sprout } from 'lucide-react'

/** Habillage commun des pages d'authentification publiques (login, inscription, mot de passe
 * oublié...) — cohérent visuellement avec la page d'accueil (audit de professionnalisation). */
export function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-full items-center justify-center bg-gradient-to-br from-primary-dark to-primary p-4">
      <div className="w-full max-w-sm rounded-xl border border-white/10 bg-surface p-6 shadow-md">
        <Link to="/" className="mb-1 flex items-center gap-2 text-ink no-underline">
          <span className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-white">
            <Sprout size={18} aria-hidden />
          </span>
          <span className="text-lg font-bold">Agriculture Mada</span>
        </Link>
        <p className="mb-5 text-sm text-muted">Plateforme intelligente de gestion agricole</p>
        {children}
      </div>
    </div>
  )
}

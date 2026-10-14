import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth'
import { Spinner } from './Spinner'
import type { Role } from '../types/api'

interface ProtectedRouteProps {
  children: ReactNode
  /** Si fourni, restreint l'accès aux rôles listés (sinon redirige vers le tableau de bord). */
  allow?: Role[]
}

export function ProtectedRoute({ children, allow }: ProtectedRouteProps) {
  const { user, loading, sessionExpired } = useAuth()
  const location = useLocation()

  if (loading) {
    return <Spinner label="Vérification de la session…" />
  }

  if (!user) {
    // Ne modifie pas le contexte ici (setState pendant le rendu d'un autre composant) : c'est
    // LoginPage qui consomme puis efface le drapeau (effet, après montage — voir son useEffect).
    return <Navigate to="/login" state={{ from: location.pathname, expired: sessionExpired }} replace />
  }

  if (allow && !allow.includes(user.role)) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}

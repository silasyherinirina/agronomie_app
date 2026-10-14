import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import {
  fetchCurrentUser,
  login as loginRequest,
  logout as logoutRequest,
  register as registerRequest,
} from '../services/auth'
import { setUnauthorizedHandler } from '../services/http'
import type { User } from '../types/api'

interface AuthContextValue {
  user: User | null
  /** true pendant la vérification de session au démarrage de l'app. */
  loading: boolean
  /** true si un 401 a coupé une session jusque-là active (pas au premier chargement anonyme). */
  sessionExpired: boolean
  clearSessionExpired: () => void
  login: (email: string, password: string) => Promise<User>
  register: (input: { name: string; email: string; password: string; password_confirmation: string }) => Promise<User>
  logout: () => Promise<void>
  /** Met à jour l'utilisateur en contexte sans nouvel appel réseau (après modif. du profil). */
  setUser: (user: User) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)
  const [sessionExpired, setSessionExpired] = useState(false)
  // Miroir de `user` : l'écouteur 401 est enregistré une seule fois (effet à deps vides) et
  // doit pourtant savoir si une session ÉTAIT active au moment du 401, sans dépendre d'un
  // `user` figé dans sa closure. Synchronisé en effet (pas pendant le rendu — un ref ne doit
  // être écrit qu'en dehors du rendu : gestionnaire d'évènement ou effet).
  const userRef = useRef<User | null>(null)
  useEffect(() => {
    userRef.current = user
  }, [user])

  useEffect(() => {
    fetchCurrentUser()
      .then(setUser)
      .catch(() => setUser(null))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    // Un 401 alors qu'une session était active = expiration réelle (à signaler) ; un 401 sans
    // session préalable = simple visiteur non connecté (rien à signaler, cas normal).
    setUnauthorizedHandler(() => {
      if (userRef.current) {
        setSessionExpired(true)
      }
      setUser(null)
    })
    return () => setUnauthorizedHandler(null)
  }, [])

  const login = useCallback(async (email: string, password: string) => {
    const loggedInUser = await loginRequest(email, password)
    setUser(loggedInUser)
    setSessionExpired(false)
    return loggedInUser
  }, [])

  const register = useCallback(
    async (input: { name: string; email: string; password: string; password_confirmation: string }) => {
      const registeredUser = await registerRequest(input)
      setUser(registeredUser)
      return registeredUser
    },
    [],
  )

  const logout = useCallback(async () => {
    try {
      await logoutRequest()
    } finally {
      setUser(null)
    }
  }, [])

  const clearSessionExpired = useCallback(() => setSessionExpired(false), [])

  const value = useMemo(
    () => ({ user, loading, sessionExpired, clearSessionExpired, login, register, logout, setUser }),
    [user, loading, sessionExpired, clearSessionExpired, login, register, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth doit être utilisé à l\'intérieur de <AuthProvider>.')
  }
  return ctx
}

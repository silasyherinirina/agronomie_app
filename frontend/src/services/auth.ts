import { fetchCsrfCookie, http } from './http'
import type { ApiResource, User } from '../types/api'

export async function login(email: string, password: string): Promise<User> {
  // Flux Sanctum SPA (voir docs/uml/sequence-auth.mmd) : cookie CSRF avant toute mutation.
  await fetchCsrfCookie()
  const { data } = await http.post<ApiResource<User>>('/login', { email, password })
  return data.data
}

export async function logout(): Promise<void> {
  await http.post('/logout')
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await http.get<ApiResource<User>>('/user')
  return data.data
}

/**
 * Inscription publique (Phase D). Le compte créé reçoit toujours le rôle 'farmer' côté
 * serveur — aucune option de rôle n'est proposée ici intentionnellement.
 */
export async function register(input: {
  name: string
  email: string
  password: string
  password_confirmation: string
}): Promise<User> {
  await fetchCsrfCookie()
  const { data } = await http.post<ApiResource<User>>('/register', input)
  return data.data
}

export async function forgotPassword(email: string): Promise<string> {
  await fetchCsrfCookie()
  const { data } = await http.post<{ message: string }>('/forgot-password', { email })
  return data.message
}

export async function resetPassword(input: {
  token: string
  email: string
  password: string
  password_confirmation: string
}): Promise<string> {
  await fetchCsrfCookie()
  const { data } = await http.post<{ message: string }>('/reset-password', input)
  return data.message
}

export async function updateProfile(input: { name: string; email: string }): Promise<User> {
  const { data } = await http.put<ApiResource<User>>('/profile', input)
  return data.data
}

export async function updatePassword(input: {
  current_password: string
  password: string
  password_confirmation: string
}): Promise<string> {
  const { data } = await http.put<{ message: string }>('/profile/password', input)
  return data.message
}

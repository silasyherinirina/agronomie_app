import type { ReactNode } from 'react'

interface AlertProps {
  tone?: 'danger' | 'success' | 'warning'
  children: ReactNode
}

const TONE_CLASSES: Record<NonNullable<AlertProps['tone']>, string> = {
  danger: 'bg-danger-bg text-danger',
  success: 'bg-success-bg text-primary-dark',
  warning: 'bg-warning-bg text-warning',
}

/** Bannière d'erreur/succès générique — utilisée pour tous les messages issus de l'API. */
export function Alert({ tone = 'danger', children }: AlertProps) {
  return (
    <div data-tone={tone} role="alert" className={`rounded-md px-4 py-3 text-sm ${TONE_CLASSES[tone]}`}>
      {children}
    </div>
  )
}

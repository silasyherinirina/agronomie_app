type Tone = 'success' | 'danger' | 'warning' | 'neutral'

const TONE_BY_STATUS: Record<string, Tone> = {
  active: 'success',
  in_progress: 'success',
  inactive: 'neutral',
  closed: 'neutral',
  harvested: 'success',
  failed: 'danger',
}

const TONE_CLASSES: Record<Tone, string> = {
  success: 'bg-success-bg text-primary-dark',
  danger: 'bg-danger-bg text-danger',
  warning: 'bg-warning-bg text-warning',
  neutral: 'bg-canvas text-muted',
}

interface BadgeProps {
  label: string
  tone?: Tone
}

/** Badge de statut générique. Si `tone` est omis, déduit une couleur cohérente du libellé. */
export function Badge({ label, tone }: BadgeProps) {
  const resolvedTone = tone ?? TONE_BY_STATUS[label] ?? 'neutral'
  return (
    <span
      data-tone={resolvedTone}
      className={`inline-flex items-center rounded-sm px-2 py-0.5 text-xs font-medium ${TONE_CLASSES[resolvedTone]}`}
    >
      {label}
    </span>
  )
}

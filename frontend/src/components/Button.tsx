import type { ButtonHTMLAttributes } from 'react'

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  loading?: boolean
}

const BASE =
  'inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold ' +
  'transition-colors disabled:cursor-not-allowed disabled:opacity-60'

const VARIANT_CLASSES: Record<Variant, string> = {
  primary: 'bg-primary text-white hover:enabled:bg-primary-dark',
  secondary: 'border border-line bg-surface text-ink hover:enabled:bg-canvas',
  danger: 'bg-danger text-white hover:enabled:opacity-90',
  ghost: 'bg-transparent text-muted hover:enabled:bg-canvas hover:enabled:text-ink',
}

export function Button({ variant = 'primary', loading = false, disabled, children, className, ...rest }: ButtonProps) {
  return (
    <button
      data-variant={variant}
      className={[BASE, VARIANT_CLASSES[variant], className].filter(Boolean).join(' ')}
      disabled={disabled || loading}
      {...rest}
    >
      {loading ? 'Chargement…' : children}
    </button>
  )
}

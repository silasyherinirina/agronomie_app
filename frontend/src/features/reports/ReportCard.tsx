import type { LucideIcon } from 'lucide-react'

interface ReportCardProps {
  icon: LucideIcon
  label: string
  description: string
  selected: boolean
  onSelect: () => void
}

/** Carte de sélection du type de rapport (§35 — "Choisir un rapport"). */
export function ReportCard({ icon: Icon, label, description, selected, onSelect }: ReportCardProps) {
  return (
    <button
      type="button"
      onClick={onSelect}
      aria-pressed={selected}
      className={[
        'flex flex-col items-start gap-2 rounded-lg border p-4 text-left transition-colors',
        selected ? 'border-primary bg-primary-light' : 'border-line bg-surface hover:bg-canvas',
      ].join(' ')}
    >
      <span
        className={[
          'flex h-10 w-10 items-center justify-center rounded-xl',
          selected ? 'bg-primary text-white' : 'bg-canvas text-primary',
        ].join(' ')}
      >
        <Icon size={18} aria-hidden />
      </span>
      <span className="text-sm font-semibold text-ink">{label}</span>
      <span className="text-xs text-muted">{description}</span>
    </button>
  )
}

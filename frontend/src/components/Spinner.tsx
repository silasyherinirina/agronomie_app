export function Spinner({ label = 'Chargement…' }: { label?: string }) {
  return (
    <div className="flex items-center gap-3 py-2 text-sm text-muted" role="status" aria-live="polite">
      <div className="h-4 w-4 animate-spin rounded-full border-2 border-line border-t-primary" />
      <span>{label}</span>
    </div>
  )
}

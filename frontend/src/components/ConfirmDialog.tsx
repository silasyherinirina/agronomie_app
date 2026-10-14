import { Button } from './Button'

interface ConfirmDialogProps {
  title: string
  message: string
  confirmLabel?: string
  busy?: boolean
  onConfirm: () => void
  onCancel: () => void
}

/** Boîte de confirmation générique — utilisée avant toute suppression (§A "suppression contrôlée"). */
export function ConfirmDialog({ title, message, confirmLabel = 'Supprimer', busy, onConfirm, onCancel }: ConfirmDialogProps) {
  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-[100] flex items-center justify-center bg-black/45 p-4"
    >
      <div className="w-full max-w-105 rounded-lg bg-surface p-6 shadow-md">
        <h3>{title}</h3>
        <p>{message}</p>
        <div className="form-actions">
          <Button variant="secondary" onClick={onCancel} disabled={busy}>
            Annuler
          </Button>
          <Button variant="danger" onClick={onConfirm} loading={busy}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  )
}

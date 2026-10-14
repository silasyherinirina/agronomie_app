import type { Paginated } from '../types/api'
import { Button } from './Button'

interface PaginationProps {
  meta: Paginated<unknown>['meta'] | null
  onPageChange: (page: number) => void
}

export function Pagination({ meta, onPageChange }: PaginationProps) {
  if (!meta || meta.last_page <= 1) {
    return null
  }

  return (
    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
      <span className="text-sm text-muted">
        {meta.total === 0
          ? 'Aucun résultat'
          : `${meta.from ?? 0}–${meta.to ?? 0} sur ${meta.total}`}
      </span>
      <div className="row">
        <Button
          variant="secondary"
          disabled={meta.current_page <= 1}
          onClick={() => onPageChange(meta.current_page - 1)}
        >
          ← Précédent
        </Button>
        <span className="text-sm text-muted">
          Page {meta.current_page} / {meta.last_page}
        </span>
        <Button
          variant="secondary"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPageChange(meta.current_page + 1)}
        >
          Suivant →
        </Button>
      </div>
    </div>
  )
}

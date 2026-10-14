import { useEffect, useState } from 'react'
import type { Paginated } from '../types/api'

/**
 * Charge une liste "complète" (per_page élevé) d'une ressource de référence pour peupler un
 * <select> de formulaire (coopératives, cultures, campagnes...). Limite connue : au-delà de
 * quelques centaines de lignes, un select simple ne suffit plus (autocomplete à prévoir).
 */
export function useOptions<T>(fetcher: () => Promise<Paginated<T>>, deps: unknown[] = []): { options: T[]; loading: boolean } {
  const [options, setOptions] = useState<T[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    fetcher()
      .then((res) => {
        if (!cancelled) setOptions(res.data)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)

  return { options, loading }
}

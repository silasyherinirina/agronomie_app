import { useCallback, useEffect, useState } from 'react'
import { toApiError, type NormalizedError } from '../services/http'
import type { Paginated } from '../types/api'

export type ListParams = Record<string, string | number | undefined>

interface UseResourceListResult<T> {
  data: T[]
  meta: Paginated<T>['meta'] | null
  loading: boolean
  error: NormalizedError | null
  params: ListParams
  /** Met à jour un paramètre ; repasse à la page 1 sauf si c'est justement `page` qui change. */
  setParam: (key: string, value: string | number | undefined) => void
  refetch: () => void
}

/**
 * Hook générique liste + pagination + filtrage, partagé par toutes les pages de listing
 * (cahier des charges §8 — réutilisabilité, §11 — pagination/filtrage/recherche côté API).
 */
export function useResourceList<T>(
  list: (params: ListParams) => Promise<Paginated<T>>,
  initialParams: ListParams = { page: 1, per_page: 15 },
): UseResourceListResult<T> {
  const [params, setParams] = useState<ListParams>(initialParams)
  const [data, setData] = useState<T[]>([])
  const [meta, setMeta] = useState<Paginated<T>['meta'] | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<NormalizedError | null>(null)
  // Sérialisé pour une dépendance d'effet stable (tous les params sont des primitives).
  const paramsKey = JSON.stringify(params)

  const fetchData = useCallback(() => {
    setLoading(true)
    setError(null)
    list(params)
      .then((res) => {
        setData(res.data)
        setMeta(res.meta)
      })
      .catch((err: unknown) => setError(toApiError(err)))
      .finally(() => setLoading(false))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [list, paramsKey])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const setParam = useCallback((key: string, value: string | number | undefined) => {
    setParams((prev) => ({
      ...prev,
      [key]: value,
      page: key === 'page' ? value : 1,
    }))
  }, [])

  return { data, meta, loading, error, params, setParam, refetch: fetchData }
}

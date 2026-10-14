import { http } from './http'
import type { ApiResource, Paginated } from '../types/api'

/**
 * Fabrique un client CRUD générique pour une ressource REST (GET liste/détail, POST, PUT,
 * DELETE), afin d'éviter de dupliquer les mêmes 5 appels Axios pour chacune des 10 ressources
 * du backend (cahier des charges §8 — réutilisabilité).
 */
export function createResourceApi<T, CreatePayload = Partial<T>, UpdatePayload = Partial<T>>(basePath: string) {
  return {
    list: (params?: Record<string, unknown>) =>
      http.get<Paginated<T>>(basePath, { params }).then((r) => r.data),
    get: (id: number) => http.get<ApiResource<T>>(`${basePath}/${id}`).then((r) => r.data.data),
    create: (payload: CreatePayload) =>
      http.post<ApiResource<T>>(basePath, payload).then((r) => r.data.data),
    update: (id: number, payload: UpdatePayload) =>
      http.put<ApiResource<T>>(`${basePath}/${id}`, payload).then((r) => r.data.data),
    remove: (id: number) => http.delete<void>(`${basePath}/${id}`),
  }
}

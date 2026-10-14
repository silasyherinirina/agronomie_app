import { http } from './http'
import type { AppNotification, Paginated, UnreadCountResponse } from '../types/api'

/**
 * Centre de notifications (miroir de NotificationController — backend). Appels centralisés ici
 * plutôt que dispersés dans les composants, comme le reste des services `src/services/*`.
 */

export function getNotifications(params?: {
  page?: number
  per_page?: number
  status?: 'unread' | 'read'
}): Promise<Paginated<AppNotification>> {
  return http.get<Paginated<AppNotification>>('/notifications', { params }).then((r) => r.data)
}

export function getUnreadCount(): Promise<number> {
  return http.get<UnreadCountResponse>('/notifications/unread-count').then((r) => r.data.count)
}

export function getNotification(id: string): Promise<AppNotification> {
  return http.get<{ data: AppNotification }>(`/notifications/${id}`).then((r) => r.data.data)
}

export function markAsRead(id: string): Promise<AppNotification> {
  return http.patch<{ data: AppNotification }>(`/notifications/${id}/read`).then((r) => r.data.data)
}

export function markAsUnread(id: string): Promise<AppNotification> {
  return http.patch<{ data: AppNotification }>(`/notifications/${id}/unread`).then((r) => r.data.data)
}

export function markAllAsRead(): Promise<{ count: number; updated: number }> {
  return http.patch<{ count: number; updated: number }>('/notifications/read-all').then((r) => r.data)
}

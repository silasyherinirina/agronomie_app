import type { ComponentType } from 'react'
import { Banknote, Bell, BrainCircuit, CalendarRange, CloudSun, Info, Wheat } from 'lucide-react'

type IconComponent = ComponentType<{ size?: number; className?: string }>

/** Icône par catégorie de notification (mêmes icônes que la navigation, pour une cohérence
 * visuelle immédiate entre la cloche, la page /notifications et les modules concernés).
 * Partagé entre AppLayout (dropdown) et NotificationsPage/NotificationDetailModal. */
const ICON_BY_TYPE: Record<string, IconComponent> = {
  harvest: Wheat,
  sale: Banknote,
  prediction: BrainCircuit,
  campaign: CalendarRange,
  weather: CloudSun,
  system: Info,
}

/** Repli sur la cloche pour un type inconnu — jamais d'icône devinée au hasard. */
export function notificationIcon(type: string | null): IconComponent {
  return (type && ICON_BY_TYPE[type]) || Bell
}

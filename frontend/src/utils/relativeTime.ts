const UNITS: { unit: Intl.RelativeTimeFormatUnit; seconds: number }[] = [
  { unit: 'year', seconds: 31536000 },
  { unit: 'month', seconds: 2592000 },
  { unit: 'week', seconds: 604800 },
  { unit: 'day', seconds: 86400 },
  { unit: 'hour', seconds: 3600 },
  { unit: 'minute', seconds: 60 },
]

const formatter = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' })

/**
 * Horodatage relatif localisé (« il y a 10 minutes », « hier »...) à partir d'une date ISO.
 * Utilise l'API `Intl.RelativeTimeFormat` du navigateur plutôt qu'une nouvelle dépendance.
 */
export function formatRelativeTime(iso: string): string {
  const diffSeconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000)

  if (Math.abs(diffSeconds) < 60) {
    return "à l'instant"
  }

  for (const { unit, seconds } of UNITS) {
    if (Math.abs(diffSeconds) >= seconds) {
      return formatter.format(Math.round(diffSeconds / seconds), unit)
    }
  }

  return formatter.format(Math.round(diffSeconds / 60), 'minute')
}

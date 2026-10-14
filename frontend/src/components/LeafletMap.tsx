import { useEffect, useRef } from 'react'
import L from 'leaflet'
import './LeafletMap.css'

interface LeafletMapProps {
  center: [number, number]
  zoom: number
  /** Reçoit l'instance Leaflet une fois créée, pour y ajouter des couches de façon impérative
   * (pas de react-leaflet : la stack imposée mentionne "Leaflet", ajouté ici en vanilla pour ne
   * pas introduire de nouvelle dépendance sans validation — cf. §5 du cahier des charges). */
  onReady: (map: L.Map) => void
  className?: string
}

/** Fond de carte OpenStreetMap + conteneur Leaflet réutilisable par toutes les pages carto. */
export function LeafletMap({ center, zoom, onReady, className }: LeafletMapProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const onReadyRef = useRef(onReady)
  onReadyRef.current = onReady

  useEffect(() => {
    if (!containerRef.current) return

    const map = L.map(containerRef.current).setView(center, zoom)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19,
    }).addTo(map)

    onReadyRef.current(map)

    return () => {
      map.remove()
    }
    // Le centre/zoom initiaux ne pilotent que la création ; les mises à jour ultérieures
    // passent par les méthodes impératives de l'instance (fitBounds, setView...).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return <div ref={containerRef} className={['leaflet-map-container', className].filter(Boolean).join(' ')} />
}

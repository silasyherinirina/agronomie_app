import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import L from 'leaflet'
import { LeafletMap } from '../../components/LeafletMap'
import { plotsApi } from '../plots/api'
import { cooperativesApi } from '../cooperatives/api'
import { farmersApi } from '../farmers/api'
import { useOptions } from '../../hooks/useOptions'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { Button } from '../../components/Button'
import { PageHeader } from '../../components/PageHeader'
import { toApiError } from '../../services/http'
import type { Plot } from '../../types/api'

const DEFAULT_CENTER: [number, number] = [
  Number(import.meta.env.VITE_MAP_DEFAULT_LAT ?? -18.8792),
  Number(import.meta.env.VITE_MAP_DEFAULT_LNG ?? 47.5079),
]
const DEFAULT_ZOOM = Number(import.meta.env.VITE_MAP_DEFAULT_ZOOM ?? 6)

const STATUS_COLOR: Record<string, string> = {
  active: '#1f7a4d',
  inactive: '#8a8f8c',
}

/**
 * Module I — Cartographie (Phase 6). Affiche les parcelles réelles (géométrie PostGIS) et les
 * sièges de coopératives sur un fond OpenStreetMap. Le dessin/édition de géométrie sur la carte
 * est explicitement reporté à une phase ultérieure (cf. formulaire Parcelle, Phase 5) — ce
 * module est volontairement limité à l'affichage, au filtrage et à la consultation.
 */
export function MapPage() {
  const navigate = useNavigate()
  const [map, setMap] = useState<L.Map | null>(null)
  const plotsLayerRef = useRef<L.LayerGroup | null>(null)
  const coopsLayerRef = useRef<L.LayerGroup | null>(null)

  const { options: cooperatives } = useOptions(() => cooperativesApi.list({ per_page: 200 }), [])
  const { options: farmers } = useOptions(() => farmersApi.list({ per_page: 200 }), [])

  const [cooperativeId, setCooperativeId] = useState('')
  const [status, setStatus] = useState('')
  const [showCooperatives, setShowCooperatives] = useState(true)

  const [plots, setPlots] = useState<Plot[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const handleMapReady = useCallback((instance: L.Map) => {
    plotsLayerRef.current = L.layerGroup().addTo(instance)
    coopsLayerRef.current = L.layerGroup().addTo(instance)
    setMap(instance)
  }, [])

  // Chargement des parcelles selon les filtres actifs — toujours depuis l'API réelle.
  useEffect(() => {
    setLoading(true)
    setError(null)
    plotsApi
      .list({
        per_page: 500,
        cooperative_id: cooperativeId || undefined,
        status: status || undefined,
      })
      .then((res) => setPlots(res.data))
      .catch((err) => setError(toApiError(err).message))
      .finally(() => setLoading(false))
  }, [cooperativeId, status])

  // Rendu des parcelles (polygones) sur la carte.
  useEffect(() => {
    if (!map || !plotsLayerRef.current) return
    const layer = plotsLayerRef.current
    layer.clearLayers()

    const boundsPoints: L.LatLngExpression[] = []

    plots
      .filter((plot) => plot.geometry)
      .forEach((plot) => {
        const coordinates = plot.geometry!.coordinates[0]
        const latLngs: L.LatLngExpression[] = coordinates.map(([lng, lat]) => [lat, lng])
        boundsPoints.push(...latLngs)

        const color = STATUS_COLOR[plot.status] ?? '#3388ff'
        const polygon = L.polygon(latLngs, { color, weight: 2, fillOpacity: 0.3 })

        const cooperative = cooperatives.find((c) => c.id === plot.cooperative_id)
        const farmer = plot.farmer_id ? farmers.find((f) => f.id === plot.farmer_id) : null

        // Construction du popup via des nœuds texte (pas innerHTML) : le nom de la parcelle,
        // de la coopérative et de l'exploitant sont des données saisies par l'utilisateur —
        // les injecter dans du HTML brut ouvrirait une faille XSS stockée (Phase 13, sécurité).
        const popup = document.createElement('div')
        const title = document.createElement('strong')
        title.textContent = plot.name
        popup.appendChild(title)
        popup.appendChild(document.createElement('br'))
        popup.appendChild(document.createTextNode(`Coopérative : ${cooperative?.name ?? plot.cooperative_id}`))
        popup.appendChild(document.createElement('br'))
        popup.appendChild(document.createTextNode(`Exploitant : ${farmer?.full_name ?? '—'}`))
        popup.appendChild(document.createElement('br'))
        popup.appendChild(document.createTextNode(`Superficie : ${plot.area_ha != null ? plot.area_ha.toFixed(4) + ' ha' : '—'}`))
        popup.appendChild(document.createElement('br'))
        popup.appendChild(document.createTextNode(`Statut : ${plot.status}`))

        const link = document.createElement('a')
        link.href = `/plots/${plot.id}/edit`
        link.textContent = 'Voir la fiche →'
        link.onclick = (e) => {
          e.preventDefault()
          navigate(`/plots/${plot.id}/edit`)
        }
        popup.appendChild(document.createElement('br'))
        popup.appendChild(link)

        polygon.bindPopup(popup)
        polygon.addTo(layer)
      })

    if (boundsPoints.length > 0) {
      map.fitBounds(L.latLngBounds(boundsPoints), { padding: [30, 30], maxZoom: 15 })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map, plots, cooperatives, farmers])

  // Rendu des sièges de coopératives (points).
  useEffect(() => {
    if (!map || !coopsLayerRef.current) return
    const layer = coopsLayerRef.current
    layer.clearLayers()
    if (!showCooperatives) return

    cooperatives
      .filter((c) => c.geometry)
      .forEach((c) => {
        const [lng, lat] = c.geometry!.coordinates
        const marker = L.circleMarker([lat, lng], {
          radius: 7,
          color: '#155c39',
          fillColor: '#b8860b',
          fillOpacity: 0.9,
          weight: 2,
        })
        // Nœuds texte, pas innerHTML : le nom/la région de la coopérative sont des données
        // saisies par l'utilisateur (même règle que le popup de parcelle ci-dessus — audit
        // du 2026-08-29, faille XSS stockée résiduelle corrigée ici).
        const popup = document.createElement('div')
        const title = document.createElement('strong')
        title.textContent = c.name
        popup.appendChild(title)
        popup.appendChild(document.createElement('br'))
        popup.appendChild(document.createTextNode(c.region))

        marker.bindPopup(popup)
        marker.addTo(layer)
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [map, cooperatives, showCooperatives])

  function recenter() {
    map?.setView(DEFAULT_CENTER, DEFAULT_ZOOM)
  }

  return (
    <div className="flex h-full flex-col gap-5">
      <PageHeader title="Carte des parcelles" description="Géométries PostGIS (SRID 4326) affichées sur fond OpenStreetMap." />

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-3">
            <select value={cooperativeId} onChange={(e) => setCooperativeId(e.target.value)}>
              <option value="">Toutes les coopératives</option>
              {cooperatives.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">Tous les statuts</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <label className="flex items-center gap-2 text-sm text-muted">
              <input type="checkbox" checked={showCooperatives} onChange={(e) => setShowCooperatives(e.target.checked)} />
              Sièges de coopératives
            </label>
          </div>
          <Button variant="secondary" onClick={recenter}>
            Recentrer (Madagascar)
          </Button>
        </div>

        {error && <Alert>{error}</Alert>}
        {loading && <Spinner label="Chargement des parcelles…" />}

        <div className="flex flex-wrap items-center gap-4 text-sm text-muted">
          <span className="flex items-center gap-1.5">
            <i className="inline-block h-2.5 w-2.5 rounded-full" style={{ background: STATUS_COLOR.active }} /> Parcelle active
          </span>
          <span className="flex items-center gap-1.5">
            <i className="inline-block h-2.5 w-2.5 rounded-full" style={{ background: STATUS_COLOR.inactive }} /> Parcelle inactive
          </span>
          <span className="flex items-center gap-1.5">
            <i className="inline-block h-2.5 w-2.5 rounded-full" style={{ background: '#b8860b' }} /> Siège de coopérative
          </span>
          <span className="ml-auto font-semibold text-ink">{plots.length} parcelle(s) affichée(s)</span>
        </div>

        <LeafletMap center={DEFAULT_CENTER} zoom={DEFAULT_ZOOM} onReady={handleMapReady} className="h-[60vh]" />
      </div>
    </div>
  )
}

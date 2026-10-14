import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { plotsApi, type PlotPayload } from './api'
import { cooperativesApi } from '../cooperatives/api'
import { farmersApi } from '../farmers/api'
import { useAuth } from '../../hooks/useAuth'
import { useOptions } from '../../hooks/useOptions'
import { FormField } from '../../components/FormField'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { toApiError, type NormalizedError } from '../../services/http'

const emptyForm: PlotPayload = { name: '', status: 'active' }
const RING_PLACEHOLDER = '[[47.50,-18.88],[47.51,-18.88],[47.51,-18.87],[47.50,-18.87],[47.50,-18.88]]'

export function PlotFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  const { options: cooperatives } = useOptions(() => cooperativesApi.list({ per_page: 200 }), [])
  const { options: farmers } = useOptions(() => farmersApi.list({ per_page: 200 }), [])

  const [form, setForm] = useState<PlotPayload>(emptyForm)
  const [ringJson, setRingJson] = useState('')
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)
  const [geometryError, setGeometryError] = useState<string | null>(null)

  useEffect(() => {
    if (!id) return
    plotsApi
      .get(Number(id))
      .then((p) => {
        setForm({ cooperative_id: p.cooperative_id, farmer_id: p.farmer_id, name: p.name, status: p.status })
        if (p.geometry) setRingJson(JSON.stringify(p.geometry.coordinates[0]))
      })
      .catch((err) => setError(toApiError(err)))
      .finally(() => setLoading(false))
  }, [id])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setGeometryError(null)

    let geometry: PlotPayload['geometry']
    if (ringJson.trim()) {
      try {
        const ring = JSON.parse(ringJson) as number[][]
        geometry = { type: 'Polygon', coordinates: [ring] }
      } catch {
        setGeometryError('Le tracé doit être un tableau JSON de coordonnées [longitude, latitude] valide.')
        return
      }
    } else if (!isEdit) {
      setGeometryError('La géométrie est obligatoire à la création (cf. Phase 0 — stratégie PostGIS).')
      return
    }

    setSaving(true)
    try {
      const payload: PlotPayload = { ...form, farmer_id: form.farmer_id || null, geometry }
      const saved = isEdit ? await plotsApi.update(Number(id), payload) : await plotsApi.create(payload)
      navigate(`/plots?cooperative_id=${saved.cooperative_id}`)
    } catch (err) {
      setError(toApiError(err))
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <Spinner />
  const fieldErrors = error?.errors ?? {}

  return (
    <div className="stack">
      <h1>{isEdit ? 'Modifier la parcelle' : 'Nouvelle parcelle'}</h1>

      <form className="card stack" onSubmit={handleSubmit}>
        {error && !Object.keys(fieldErrors).length && <Alert>{error.message}</Alert>}

        <div className="form-grid">
          {isAdmin && (
            <FormField label="Coopérative" htmlFor="cooperative_id" required errors={fieldErrors.cooperative_id}>
              <select
                id="cooperative_id"
                value={form.cooperative_id ?? ''}
                onChange={(e) => setForm({ ...form, cooperative_id: Number(e.target.value) })}
                required
              >
                <option value="" disabled>
                  Sélectionner…
                </option>
                {cooperatives.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </FormField>
          )}
          <FormField label="Agriculteur exploitant" htmlFor="farmer_id" hint="Optionnel." errors={fieldErrors.farmer_id}>
            <select id="farmer_id" value={form.farmer_id ?? ''} onChange={(e) => setForm({ ...form, farmer_id: e.target.value ? Number(e.target.value) : null })}>
              <option value="">Aucun</option>
              {farmers.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.full_name}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Nom" htmlFor="name" required errors={fieldErrors.name}>
            <input id="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </FormField>
          <FormField label="Statut" htmlFor="status" errors={fieldErrors.status}>
            <select id="status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as 'active' | 'inactive' })}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </FormField>
        </div>

        <FormField
          label="Tracé de la parcelle (anneau du polygone)"
          htmlFor="geometry"
          required={!isEdit}
          hint="Tableau JSON [longitude, latitude], premier et dernier point identiques. Un outil de dessin sur carte sera disponible en Phase 6."
          errors={geometryError ? [geometryError] : fieldErrors.geometry}
        >
          <textarea id="geometry" value={ringJson} onChange={(e) => setRingJson(e.target.value)} placeholder={RING_PLACEHOLDER} rows={3} />
        </FormField>

        <div className="form-actions">
          <Button type="button" variant="secondary" onClick={() => navigate(-1)}>
            Annuler
          </Button>
          <Button type="submit" loading={saving}>
            Enregistrer
          </Button>
        </div>
      </form>
    </div>
  )
}

import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { cropCyclesApi, type CropCyclePayload } from './api'
import { plotsApi } from '../plots/api'
import { cropsApi, cropVarietiesApi } from '../crops/api'
import { campaignsApi } from '../campaigns/api'
import { useOptions } from '../../hooks/useOptions'
import { FormField } from '../../components/FormField'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { toApiError, type NormalizedError } from '../../services/http'

const emptyForm: CropCyclePayload = { plot_id: 0, crop_id: 0, campaign_id: 0, status: 'in_progress' }

export function CropCycleFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()

  const { options: plots } = useOptions(() => plotsApi.list({ per_page: 200 }), [])
  const { options: crops } = useOptions(() => cropsApi.list({ per_page: 200 }), [])
  const { options: varieties } = useOptions(() => cropVarietiesApi.list({ per_page: 500 }), [])
  const { options: campaigns } = useOptions(() => campaignsApi.list({ per_page: 200 }), [])

  const [form, setForm] = useState<CropCyclePayload>(emptyForm)
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  const availableVarieties = useMemo(() => varieties.filter((v) => v.crop_id === form.crop_id), [varieties, form.crop_id])

  useEffect(() => {
    if (!id) return
    cropCyclesApi
      .get(Number(id))
      .then((c) =>
        setForm({
          plot_id: c.plot_id,
          crop_id: c.crop_id,
          variety_id: c.variety_id,
          campaign_id: c.campaign_id,
          planting_date: c.planting_date ?? '',
          expected_harvest_date: c.expected_harvest_date ?? '',
          status: c.status,
        }),
      )
      .catch((err) => setError(toApiError(err)))
      .finally(() => setLoading(false))
  }, [id])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const payload = {
        ...form,
        planting_date: form.planting_date || null,
        expected_harvest_date: form.expected_harvest_date || null,
      }
      if (isEdit) {
        await cropCyclesApi.update(Number(id), payload)
      } else {
        await cropCyclesApi.create(payload)
      }
      navigate('/crop-cycles')
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
      <h1>{isEdit ? 'Modifier le cycle cultural' : 'Nouveau cycle cultural'}</h1>

      <form className="card stack" onSubmit={handleSubmit}>
        {error && !Object.keys(fieldErrors).length && <Alert>{error.message}</Alert>}

        <div className="form-grid">
          <FormField label="Parcelle" htmlFor="plot_id" required errors={fieldErrors.plot_id}>
            <select id="plot_id" value={form.plot_id || ''} onChange={(e) => setForm({ ...form, plot_id: Number(e.target.value) })} required disabled={isEdit}>
              <option value="" disabled>
                Sélectionner…
              </option>
              {plots.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Culture" htmlFor="crop_id" required errors={fieldErrors.crop_id}>
            <select
              id="crop_id"
              value={form.crop_id || ''}
              onChange={(e) => setForm({ ...form, crop_id: Number(e.target.value), variety_id: null })}
              required
              disabled={isEdit}
            >
              <option value="" disabled>
                Sélectionner…
              </option>
              {crops.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Variété" htmlFor="variety_id" hint="Optionnel." errors={fieldErrors.variety_id}>
            <select id="variety_id" value={form.variety_id ?? ''} onChange={(e) => setForm({ ...form, variety_id: e.target.value ? Number(e.target.value) : null })}>
              <option value="">Aucune</option>
              {availableVarieties.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.name}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Campagne" htmlFor="campaign_id" required errors={fieldErrors.campaign_id}>
            <select id="campaign_id" value={form.campaign_id || ''} onChange={(e) => setForm({ ...form, campaign_id: Number(e.target.value) })} required disabled={isEdit}>
              <option value="" disabled>
                Sélectionner…
              </option>
              {campaigns.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Date de plantation" htmlFor="planting_date" errors={fieldErrors.planting_date}>
            <input id="planting_date" type="date" value={form.planting_date ?? ''} onChange={(e) => setForm({ ...form, planting_date: e.target.value })} />
          </FormField>
          <FormField label="Récolte prévue le" htmlFor="expected_harvest_date" errors={fieldErrors.expected_harvest_date}>
            <input
              id="expected_harvest_date"
              type="date"
              value={form.expected_harvest_date ?? ''}
              onChange={(e) => setForm({ ...form, expected_harvest_date: e.target.value })}
            />
          </FormField>
          {isEdit && (
            <FormField label="Statut" htmlFor="status" errors={fieldErrors.status}>
              <select id="status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as CropCyclePayload['status'] })}>
                <option value="in_progress">En cours</option>
                <option value="harvested">Récolté</option>
                <option value="failed">Échoué</option>
              </select>
            </FormField>
          )}
        </div>

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

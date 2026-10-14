import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { harvestsApi, type HarvestPayload } from './api'
import { cropCyclesApi } from '../cropCycles/api'
import { useOptions } from '../../hooks/useOptions'
import { FormField } from '../../components/FormField'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { toApiError, type NormalizedError } from '../../services/http'

const emptyForm: HarvestPayload = { crop_cycle_id: 0, harvest_date: '', quantity: 0, unit: 'kg', yield_source: 'calculated' }

export function HarvestFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()

  const { options: cropCycles } = useOptions(() => cropCyclesApi.list({ per_page: 200 }), [])

  const [form, setForm] = useState<HarvestPayload>(emptyForm)
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  useEffect(() => {
    if (!id) return
    harvestsApi
      .get(Number(id))
      .then((h) =>
        setForm({
          crop_cycle_id: h.crop_cycle_id,
          harvest_date: h.harvest_date,
          quantity: h.quantity,
          unit: h.unit,
          yield_source: h.yield_source,
          yield_value: h.yield_value,
          quality: h.quality ?? '',
          observations: h.observations ?? '',
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
        quality: form.quality || null,
        observations: form.observations || null,
        yield_value: form.yield_source === 'declared' ? form.yield_value : undefined,
      }
      if (isEdit) {
        await harvestsApi.update(Number(id), payload)
      } else {
        await harvestsApi.create(payload)
      }
      navigate('/harvests')
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
      <h1>{isEdit ? 'Modifier la récolte' : 'Nouvelle récolte'}</h1>

      <form className="card stack" onSubmit={handleSubmit}>
        {error && !Object.keys(fieldErrors).length && <Alert>{error.message}</Alert>}

        <div className="form-grid">
          <FormField label="Cycle cultural" htmlFor="crop_cycle_id" required errors={fieldErrors.crop_cycle_id}>
            <select
              id="crop_cycle_id"
              value={form.crop_cycle_id || ''}
              onChange={(e) => setForm({ ...form, crop_cycle_id: Number(e.target.value) })}
              required
              disabled={isEdit}
            >
              <option value="" disabled>
                Sélectionner…
              </option>
              {cropCycles.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.plot?.name ?? c.plot_id} — {c.crop?.name ?? c.crop_id}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Date de récolte" htmlFor="harvest_date" required errors={fieldErrors.harvest_date}>
            <input id="harvest_date" type="date" value={form.harvest_date} onChange={(e) => setForm({ ...form, harvest_date: e.target.value })} required />
          </FormField>
          <FormField label="Quantité" htmlFor="quantity" required errors={fieldErrors.quantity}>
            <input id="quantity" type="number" min={0} step="any" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: Number(e.target.value) })} required />
          </FormField>
          <FormField label="Unité" htmlFor="unit" required errors={fieldErrors.unit}>
            <input id="unit" value={form.unit} onChange={(e) => setForm({ ...form, unit: e.target.value })} placeholder="kg, sac, tonne…" required />
          </FormField>
          <FormField
            label="Stratégie de rendement"
            htmlFor="yield_source"
            hint="Calculé : quantité / superficie de la parcelle. Déclaré : saisi manuellement."
            errors={fieldErrors.yield_source}
          >
            <select id="yield_source" value={form.yield_source} onChange={(e) => setForm({ ...form, yield_source: e.target.value as 'calculated' | 'declared' })}>
              <option value="calculated">Calculé automatiquement</option>
              <option value="declared">Déclaré manuellement</option>
            </select>
          </FormField>
          {form.yield_source === 'declared' && (
            <FormField label="Rendement déclaré" htmlFor="yield_value" required errors={fieldErrors.yield_value}>
              <input
                id="yield_value"
                type="number"
                min={0}
                step="any"
                value={form.yield_value ?? ''}
                onChange={(e) => setForm({ ...form, yield_value: e.target.value ? Number(e.target.value) : null })}
                required
              />
            </FormField>
          )}
          <FormField label="Qualité" htmlFor="quality" hint="Optionnel." errors={fieldErrors.quality}>
            <input id="quality" value={form.quality ?? ''} onChange={(e) => setForm({ ...form, quality: e.target.value })} />
          </FormField>
        </div>

        <FormField label="Observations" htmlFor="observations" errors={fieldErrors.observations}>
          <textarea id="observations" value={form.observations ?? ''} onChange={(e) => setForm({ ...form, observations: e.target.value })} rows={3} />
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

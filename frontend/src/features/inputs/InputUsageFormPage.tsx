import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { inputUsagesApi, inputsApi, type InputUsagePayload } from './api'
import { plotsApi } from '../plots/api'
import { campaignsApi } from '../campaigns/api'
import { farmersApi } from '../farmers/api'
import { useOptions } from '../../hooks/useOptions'
import { FormField } from '../../components/FormField'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { toApiError, type NormalizedError } from '../../services/http'

const emptyForm: InputUsagePayload = { input_id: 0, plot_id: 0, campaign_id: 0, quantity: 0, date: '' }

export function InputUsageFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()

  const { options: inputs } = useOptions(() => inputsApi.list({ per_page: 200 }), [])
  const { options: plots } = useOptions(() => plotsApi.list({ per_page: 200 }), [])
  const { options: campaigns } = useOptions(() => campaignsApi.list({ per_page: 200 }), [])
  const { options: farmers } = useOptions(() => farmersApi.list({ per_page: 200 }), [])

  const [form, setForm] = useState<InputUsagePayload>(emptyForm)
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  useEffect(() => {
    if (!id) return
    inputUsagesApi
      .get(Number(id))
      .then((u) =>
        setForm({
          input_id: u.input_id,
          plot_id: u.plot_id,
          campaign_id: u.campaign_id,
          farmer_id: u.farmer_id,
          quantity: u.quantity,
          cost: u.cost,
          date: u.date,
          observations: u.observations ?? '',
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
      const payload = { ...form, farmer_id: form.farmer_id || null, cost: form.cost || null, observations: form.observations || null }
      if (isEdit) {
        await inputUsagesApi.update(Number(id), payload)
      } else {
        await inputUsagesApi.create(payload)
      }
      navigate('/inputs/usages')
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
      <h1>{isEdit ? "Modifier l'utilisation" : 'Nouvelle utilisation d\'intrant'}</h1>

      <form className="card stack" onSubmit={handleSubmit}>
        {error && !Object.keys(fieldErrors).length && <Alert>{error.message}</Alert>}

        <div className="form-grid">
          <FormField label="Intrant" htmlFor="input_id" required errors={fieldErrors.input_id}>
            <select id="input_id" value={form.input_id || ''} onChange={(e) => setForm({ ...form, input_id: Number(e.target.value) })} required disabled={isEdit}>
              <option value="" disabled>
                Sélectionner…
              </option>
              {inputs.map((i) => (
                <option key={i.id} value={i.id}>
                  {i.name} ({i.unit})
                </option>
              ))}
            </select>
          </FormField>
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
          <FormField label="Agriculteur" htmlFor="farmer_id" hint="Optionnel." errors={fieldErrors.farmer_id}>
            <select id="farmer_id" value={form.farmer_id ?? ''} onChange={(e) => setForm({ ...form, farmer_id: e.target.value ? Number(e.target.value) : null })}>
              <option value="">Aucun</option>
              {farmers.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.full_name}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Quantité" htmlFor="quantity" required errors={fieldErrors.quantity}>
            <input id="quantity" type="number" min={0} step="any" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: Number(e.target.value) })} required />
          </FormField>
          <FormField label="Coût (Ar)" htmlFor="cost" hint="Optionnel." errors={fieldErrors.cost}>
            <input id="cost" type="number" min={0} step="any" value={form.cost ?? ''} onChange={(e) => setForm({ ...form, cost: e.target.value ? Number(e.target.value) : null })} />
          </FormField>
          <FormField label="Date" htmlFor="date" required errors={fieldErrors.date}>
            <input id="date" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} required />
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

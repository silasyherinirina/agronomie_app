import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { campaignsApi, type CampaignPayload } from './api'
import { FormField } from '../../components/FormField'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { toApiError, type NormalizedError } from '../../services/http'

const emptyForm: CampaignPayload = { name: '', year: new Date().getFullYear(), start_date: '', status: 'active' }

export function CampaignFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()

  const [form, setForm] = useState<CampaignPayload>(emptyForm)
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  useEffect(() => {
    if (!id) return
    campaignsApi
      .get(Number(id))
      .then((c) =>
        setForm({ name: c.name, year: c.year, season: c.season ?? '', start_date: c.start_date, end_date: c.end_date ?? '', status: c.status }),
      )
      .catch((err) => setError(toApiError(err)))
      .finally(() => setLoading(false))
  }, [id])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const payload = { ...form, season: form.season || null, end_date: form.end_date || null }
      if (isEdit) {
        await campaignsApi.update(Number(id), payload)
      } else {
        await campaignsApi.create(payload)
      }
      navigate('/campaigns')
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
      <h1>{isEdit ? 'Modifier la campagne' : 'Nouvelle campagne'}</h1>

      <form className="card stack" onSubmit={handleSubmit}>
        {error && !Object.keys(fieldErrors).length && <Alert>{error.message}</Alert>}

        <div className="form-grid">
          <FormField label="Nom" htmlFor="name" required errors={fieldErrors.name}>
            <input id="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </FormField>
          <FormField label="Année" htmlFor="year" required errors={fieldErrors.year}>
            <input id="year" type="number" value={form.year} onChange={(e) => setForm({ ...form, year: Number(e.target.value) })} required />
          </FormField>
          <FormField label="Saison" htmlFor="season" errors={fieldErrors.season}>
            <input id="season" value={form.season ?? ''} onChange={(e) => setForm({ ...form, season: e.target.value })} placeholder="saison des pluies…" />
          </FormField>
          <FormField label="Statut" htmlFor="status" errors={fieldErrors.status}>
            <select id="status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as 'active' | 'closed' })}>
              <option value="active">Active</option>
              <option value="closed">Clôturée</option>
            </select>
          </FormField>
          <FormField label="Date de début" htmlFor="start_date" required errors={fieldErrors.start_date}>
            <input id="start_date" type="date" value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} required />
          </FormField>
          <FormField label="Date de fin" htmlFor="end_date" errors={fieldErrors.end_date}>
            <input id="end_date" type="date" value={form.end_date ?? ''} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
          </FormField>
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

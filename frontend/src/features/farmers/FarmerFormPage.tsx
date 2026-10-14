import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { farmersApi, type FarmerPayload } from './api'
import { cooperativesApi } from '../cooperatives/api'
import { useAuth } from '../../hooks/useAuth'
import { useOptions } from '../../hooks/useOptions'
import { FormField } from '../../components/FormField'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { toApiError, type NormalizedError } from '../../services/http'

const emptyForm: FarmerPayload = { first_name: '', last_name: '', join_date: '', status: 'active' }

export function FarmerFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  const { options: cooperatives } = useOptions(() => cooperativesApi.list({ per_page: 200 }), [])

  const [form, setForm] = useState<FarmerPayload>(emptyForm)
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  useEffect(() => {
    if (!id) return
    farmersApi
      .get(Number(id))
      .then((f) =>
        setForm({
          cooperative_id: f.cooperative_id,
          first_name: f.first_name,
          last_name: f.last_name,
          join_date: f.join_date ?? '',
          status: f.status,
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
      const payload = { ...form, join_date: form.join_date || null }
      const saved = isEdit ? await farmersApi.update(Number(id), payload) : await farmersApi.create(payload)
      navigate(`/farmers?cooperative_id=${saved.cooperative_id}`)
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
      <h1>{isEdit ? "Modifier l'agriculteur" : 'Nouvel agriculteur'}</h1>

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
          <FormField label="Prénom" htmlFor="first_name" required errors={fieldErrors.first_name}>
            <input id="first_name" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} required />
          </FormField>
          <FormField label="Nom" htmlFor="last_name" required errors={fieldErrors.last_name}>
            <input id="last_name" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} required />
          </FormField>
          <FormField label="Date d'adhésion" htmlFor="join_date" errors={fieldErrors.join_date}>
            <input id="join_date" type="date" value={form.join_date ?? ''} onChange={(e) => setForm({ ...form, join_date: e.target.value })} />
          </FormField>
          <FormField label="Statut" htmlFor="status" errors={fieldErrors.status}>
            <select id="status" value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value as 'active' | 'inactive' })}>
              <option value="active">Actif</option>
              <option value="inactive">Inactif</option>
            </select>
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

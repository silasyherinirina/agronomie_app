import { useCallback, useState, type FormEvent } from 'react'
import { Trash2 } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth'
import { useResourceList } from '../../hooks/useResourceList'
import { useOptions } from '../../hooks/useOptions'
import { cropsApi, cropVarietiesApi, type CropPayload, type CropVarietyPayload } from './api'
import { DataTable, type Column } from '../../components/DataTable'
import { Pagination } from '../../components/Pagination'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { FormField } from '../../components/FormField'
import { PageHeader } from '../../components/PageHeader'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { toApiError, type NormalizedError } from '../../services/http'
import type { Crop, CropVariety } from '../../types/api'

const emptyCrop: CropPayload = { name: '', category: '', cycle_duration_days: undefined }

function CropForm({ onSaved }: { onSaved: () => void }) {
  const [form, setForm] = useState<CropPayload>(emptyCrop)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await cropsApi.create({ ...form, category: form.category || null })
      setForm(emptyCrop)
      onSaved()
    } catch (err) {
      setError(toApiError(err))
    } finally {
      setSaving(false)
    }
  }

  const fieldErrors = error?.errors ?? {}

  return (
    <form className="stack" onSubmit={handleSubmit}>
      {error && !Object.keys(fieldErrors).length && <Alert>{error.message}</Alert>}
      <div className="form-grid">
        <FormField label="Nom" htmlFor="crop_name" required errors={fieldErrors.name}>
          <input id="crop_name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </FormField>
        <FormField label="Catégorie" htmlFor="crop_category" errors={fieldErrors.category}>
          <input id="crop_category" value={form.category ?? ''} onChange={(e) => setForm({ ...form, category: e.target.value })} placeholder="céréale, tubercule…" />
        </FormField>
        <FormField label="Durée de cycle (jours)" htmlFor="crop_cycle" errors={fieldErrors.cycle_duration_days}>
          <input
            id="crop_cycle"
            type="number"
            min={1}
            value={form.cycle_duration_days ?? ''}
            onChange={(e) => setForm({ ...form, cycle_duration_days: e.target.value ? Number(e.target.value) : undefined })}
          />
        </FormField>
      </div>
      <div className="form-actions">
        <Button type="submit" loading={saving}>
          Ajouter la culture
        </Button>
      </div>
    </form>
  )
}

function VarietyForm({ crops, onSaved }: { crops: Crop[]; onSaved: () => void }) {
  const [form, setForm] = useState<CropVarietyPayload>({ crop_id: crops[0]?.id ?? 0, name: '' })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await cropVarietiesApi.create(form)
      setForm({ ...form, name: '' })
      onSaved()
    } catch (err) {
      setError(toApiError(err))
    } finally {
      setSaving(false)
    }
  }

  const fieldErrors = error?.errors ?? {}

  return (
    <form className="stack" onSubmit={handleSubmit}>
      {error && !Object.keys(fieldErrors).length && <Alert>{error.message}</Alert>}
      <div className="form-grid">
        <FormField label="Culture" htmlFor="variety_crop" required errors={fieldErrors.crop_id}>
          <select id="variety_crop" value={form.crop_id} onChange={(e) => setForm({ ...form, crop_id: Number(e.target.value) })} required>
            {crops.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </FormField>
        <FormField label="Nom de la variété" htmlFor="variety_name" required errors={fieldErrors.name}>
          <input id="variety_name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </FormField>
      </div>
      <div className="form-actions">
        <Button type="submit" loading={saving} disabled={!crops.length}>
          Ajouter la variété
        </Button>
      </div>
    </form>
  )
}

export function CropsPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  const cropsList = useCallback((params: Record<string, unknown>) => cropsApi.list(params), [])
  const crops = useResourceList<Crop>(cropsList, { page: 1, per_page: 50 })

  const varietiesList = useCallback((params: Record<string, unknown>) => cropVarietiesApi.list(params), [])
  const varieties = useResourceList<CropVariety>(varietiesList, { page: 1, per_page: 50 })

  const { options: allCrops } = useOptions(() => cropsApi.list({ per_page: 200 }), [crops.data.length])

  const [cropToDelete, setCropToDelete] = useState<Crop | null>(null)
  const [varietyToDelete, setVarietyToDelete] = useState<CropVariety | null>(null)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  async function deleteCrop() {
    if (!cropToDelete) return
    setBusy(true)
    setActionError(null)
    try {
      await cropsApi.remove(cropToDelete.id)
      setCropToDelete(null)
      crops.refetch()
    } catch (err) {
      setActionError(toApiError(err).message)
    } finally {
      setBusy(false)
    }
  }

  async function deleteVariety() {
    if (!varietyToDelete) return
    setBusy(true)
    setActionError(null)
    try {
      await cropVarietiesApi.remove(varietyToDelete.id)
      setVarietyToDelete(null)
      varieties.refetch()
    } catch (err) {
      setActionError(toApiError(err).message)
    } finally {
      setBusy(false)
    }
  }

  const cropColumns: Column<Crop>[] = [
    { key: 'name', header: 'Nom', render: (c) => c.name },
    { key: 'category', header: 'Catégorie', render: (c) => c.category ?? '—' },
    { key: 'cycle_duration_days', header: 'Cycle (jours)', render: (c) => c.cycle_duration_days ?? '—', align: 'right' },
    ...(isAdmin
      ? [
          {
            key: 'actions',
            header: '',
            align: 'right' as const,
            render: (c: Crop) => (
              <Button variant="ghost" onClick={() => setCropToDelete(c)}>
                <Trash2 size={14} aria-hidden />
                Supprimer
              </Button>
            ),
          },
        ]
      : []),
  ]

  const varietyColumns: Column<CropVariety>[] = [
    { key: 'name', header: 'Variété', render: (v) => v.name },
    { key: 'crop', header: 'Culture', render: (v) => v.crop?.name ?? allCrops.find((c) => c.id === v.crop_id)?.name ?? v.crop_id },
    ...(isAdmin
      ? [
          {
            key: 'actions',
            header: '',
            align: 'right' as const,
            render: (v: CropVariety) => (
              <Button variant="ghost" onClick={() => setVarietyToDelete(v)}>
                <Trash2 size={14} aria-hidden />
                Supprimer
              </Button>
            ),
          },
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader title="Cultures & variétés" description="Référentiel commun à toutes les coopératives (gestion réservée à l'administrateur)." />

      {actionError && <Alert>{actionError}</Alert>}

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 className="text-base font-semibold text-ink">Cultures</h2>
        {isAdmin && <CropForm onSaved={crops.refetch} />}
        <DataTable columns={cropColumns} rows={crops.data} rowKey={(c) => c.id} loading={crops.loading} emptyMessage="Aucune culture." />
        <Pagination meta={crops.meta} onPageChange={(page) => crops.setParam('page', page)} />
      </div>

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 className="text-base font-semibold text-ink">Variétés</h2>
        {isAdmin && <VarietyForm crops={allCrops} onSaved={varieties.refetch} />}
        <DataTable columns={varietyColumns} rows={varieties.data} rowKey={(v) => v.id} loading={varieties.loading} emptyMessage="Aucune variété." />
        <Pagination meta={varieties.meta} onPageChange={(page) => varieties.setParam('page', page)} />
      </div>

      {cropToDelete && (
        <ConfirmDialog
          title="Supprimer la culture"
          message={`Confirmer la suppression de « ${cropToDelete.name} » ? Impossible si des cycles culturaux y font encore référence.`}
          busy={busy}
          onConfirm={() => void deleteCrop()}
          onCancel={() => setCropToDelete(null)}
        />
      )}
      {varietyToDelete && (
        <ConfirmDialog
          title="Supprimer la variété"
          message={`Confirmer la suppression de « ${varietyToDelete.name} » ?`}
          busy={busy}
          onConfirm={() => void deleteVariety()}
          onCancel={() => setVarietyToDelete(null)}
        />
      )}
    </div>
  )
}

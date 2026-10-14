import { useCallback, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Trash2 } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth'
import { useResourceList } from '../../hooks/useResourceList'
import { inputsApi, type InputPayload } from './api'
import { DataTable, type Column } from '../../components/DataTable'
import { Pagination } from '../../components/Pagination'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { FormField } from '../../components/FormField'
import { PageHeader } from '../../components/PageHeader'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { toApiError, type NormalizedError } from '../../services/http'
import type { Input, InputType } from '../../types/api'

const INPUT_TYPES: { value: InputType; label: string }[] = [
  { value: 'semence', label: 'Semence' },
  { value: 'engrais', label: 'Engrais' },
  { value: 'phytosanitaire', label: 'Phytosanitaire' },
  { value: 'autre', label: 'Autre' },
]

const emptyForm: InputPayload = { name: '', type: 'engrais', unit: '' }

function InputForm({ onSaved }: { onSaved: () => void }) {
  const [form, setForm] = useState<InputPayload>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await inputsApi.create(form)
      setForm(emptyForm)
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
        <FormField label="Nom" htmlFor="input_name" required errors={fieldErrors.name}>
          <input id="input_name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </FormField>
        <FormField label="Type" htmlFor="input_type" required errors={fieldErrors.type}>
          <select id="input_type" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value as InputType })} required>
            {INPUT_TYPES.map((t) => (
              <option key={t.value} value={t.value}>
                {t.label}
              </option>
            ))}
          </select>
        </FormField>
        <FormField label="Unité" htmlFor="input_unit" required errors={fieldErrors.unit}>
          <input id="input_unit" value={form.unit} onChange={(e) => setForm({ ...form, unit: e.target.value })} placeholder="kg, litre, sac…" required />
        </FormField>
      </div>
      <div className="form-actions">
        <Button type="submit" loading={saving}>
          Ajouter l'intrant
        </Button>
      </div>
    </form>
  )
}

export function InputsPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  const list = useCallback((params: Record<string, unknown>) => inputsApi.list(params), [])
  const { data, meta, loading, error, params, setParam, refetch } = useResourceList(list, { page: 1, per_page: 50 })

  const [toDelete, setToDelete] = useState<Input | null>(null)
  const [busy, setBusy] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)

  async function confirmDelete() {
    if (!toDelete) return
    setBusy(true)
    setActionError(null)
    try {
      await inputsApi.remove(toDelete.id)
      setToDelete(null)
      refetch()
    } catch (err) {
      setActionError(toApiError(err).message)
    } finally {
      setBusy(false)
    }
  }

  const columns: Column<Input>[] = [
    { key: 'name', header: 'Nom', render: (i) => i.name },
    { key: 'type', header: 'Type', render: (i) => INPUT_TYPES.find((t) => t.value === i.type)?.label ?? i.type },
    { key: 'unit', header: 'Unité', render: (i) => i.unit },
    ...(isAdmin
      ? [
          {
            key: 'actions',
            header: '',
            align: 'right' as const,
            render: (i: Input) => (
              <Button variant="ghost" onClick={() => setToDelete(i)}>
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
      <PageHeader
        title="Intrants"
        description="Référentiel (semences, engrais, produits phytosanitaires…). Gestion réservée à l'administrateur."
        action={
          <Link to="/inputs/usages">
            <Button variant="secondary">
              Voir les utilisations
              <ArrowRight size={16} aria-hidden />
            </Button>
          </Link>
        }
      />

      {actionError && <Alert>{actionError}</Alert>}

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <select value={String(params.type ?? '')} onChange={(e) => setParam('type', e.target.value || undefined)}>
            <option value="">Tous les types</option>
            {INPUT_TYPES.map((t) => (
              <option key={t.value} value={t.value}>
                {t.label}
              </option>
            ))}
          </select>
        </div>

        {isAdmin && <InputForm onSaved={refetch} />}
        {error && <Alert>{error.message}</Alert>}
        <DataTable columns={columns} rows={data} rowKey={(i) => i.id} loading={loading} emptyMessage="Aucun intrant." />
        <Pagination meta={meta} onPageChange={(page) => setParam('page', page)} />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer l'intrant"
          message={`Confirmer la suppression de « ${toDelete.name} » ? Impossible si des utilisations y font encore référence.`}
          busy={busy}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
    </div>
  )
}

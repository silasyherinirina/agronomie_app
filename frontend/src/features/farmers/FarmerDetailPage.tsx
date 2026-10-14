import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { farmersApi } from './api'
import { plotsApi } from '../plots/api'
import { harvestsApi } from '../harvests/api'
import { useAuth } from '../../hooks/useAuth'
import { useResourceList } from '../../hooks/useResourceList'
import { Spinner } from '../../components/Spinner'
import { Alert } from '../../components/Alert'
import { Badge } from '../../components/Badge'
import { Button } from '../../components/Button'
import { DataTable, type Column } from '../../components/DataTable'
import { Pagination } from '../../components/Pagination'
import { Tabs } from '../../components/Tabs'
import { toApiError, type NormalizedError } from '../../services/http'
import type { Farmer, Harvest, Plot } from '../../types/api'

export function FarmerDetailPage() {
  const { id } = useParams()
  const { user } = useAuth()
  const [farmer, setFarmer] = useState<Farmer | null>(null)
  const [error, setError] = useState<NormalizedError | null>(null)

  useEffect(() => {
    if (!id) return
    farmersApi.get(Number(id)).then(setFarmer).catch((err) => setError(toApiError(err)))
  }, [id])

  const canEdit =
    user?.role === 'admin' || (user?.role === 'cooperative_manager' && user.managed_cooperative_id === farmer?.cooperative_id)

  if (error) return <Alert>{error.message}</Alert>
  if (!farmer) return <Spinner label="Chargement de la fiche agriculteur…" />

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-ink">{farmer.full_name}</h1>
          <p className="text-sm text-muted">
            <Link to={`/cooperatives/${farmer.cooperative_id}`} className="text-primary hover:underline">
              {farmer.cooperative?.name ?? `Coopérative #${farmer.cooperative_id}`}
            </Link>
            {farmer.join_date && ` · Membre depuis le ${new Date(farmer.join_date).toLocaleDateString('fr-FR')}`}
          </p>
        </div>
        {canEdit && (
          <Link to={`/farmers/${farmer.id}/edit`}>
            <Button variant="secondary">Modifier</Button>
          </Link>
        )}
      </div>

      <Tabs
        items={[
          {
            key: 'resume',
            label: 'Résumé',
            content: (
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <SummaryStat label="Statut" value={<Badge label={farmer.status} />} />
                <SummaryStat label="Parcelles" value={farmer.plots_count ?? '—'} />
                <SummaryStat label="Compte plateforme" value={farmer.user_id ? 'Oui' : 'Non rattaché'} />
              </div>
            ),
          },
          { key: 'plots', label: 'Parcelles', content: <FarmerPlotsTab farmerId={farmer.id} /> },
          { key: 'harvests', label: 'Historique de production', content: <FarmerHarvestsTab farmerId={farmer.id} /> },
        ]}
      />
    </div>
  )
}

function SummaryStat({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="rounded-lg border border-line bg-surface p-4">
      <div className="text-xs uppercase tracking-wide text-muted">{label}</div>
      <div className="mt-1 text-lg font-semibold text-ink">{value}</div>
    </div>
  )
}

function FarmerPlotsTab({ farmerId }: { farmerId: number }) {
  const list = useCallback((params: Record<string, unknown>) => plotsApi.list({ ...params, farmer_id: farmerId }), [farmerId])
  const { data, meta, loading, error, setParam } = useResourceList<Plot>(list)

  const columns: Column<Plot>[] = [
    { key: 'name', header: 'Nom', render: (p) => <Link to={`/plots/${p.id}/edit`} className="text-primary hover:underline">{p.name}</Link> },
    { key: 'area_ha', header: 'Superficie (ha)', align: 'right', render: (p) => p.area_ha?.toFixed(4) ?? '—' },
    { key: 'status', header: 'Statut', render: (p) => <Badge label={p.status} /> },
  ]

  return (
    <div className="flex flex-col gap-3">
      {error && <Alert>{error.message}</Alert>}
      <DataTable columns={columns} rows={data} rowKey={(p) => p.id} loading={loading} emptyMessage="Aucune parcelle rattachée à cet agriculteur." />
      <Pagination meta={meta} onPageChange={(page) => setParam('page', page)} />
    </div>
  )
}

function FarmerHarvestsTab({ farmerId }: { farmerId: number }) {
  const list = useCallback((params: Record<string, unknown>) => harvestsApi.list({ ...params, farmer_id: farmerId }), [farmerId])
  const { data, meta, loading, error, setParam } = useResourceList<Harvest>(list)

  const columns: Column<Harvest>[] = [
    { key: 'harvest_date', header: 'Date', render: (h) => new Date(h.harvest_date).toLocaleDateString('fr-FR') },
    { key: 'plot', header: 'Parcelle', render: (h) => h.crop_cycle?.plot?.name ?? '—' },
    { key: 'crop', header: 'Culture', render: (h) => h.crop_cycle?.crop?.name ?? '—' },
    { key: 'quantity', header: 'Quantité', align: 'right', render: (h) => `${h.quantity.toLocaleString('fr-FR')} ${h.unit}` },
    { key: 'yield_value', header: 'Rendement', align: 'right', render: (h) => (h.yield_value != null ? `${h.yield_value.toLocaleString('fr-FR')} ${h.unit}/ha` : '—') },
  ]

  return (
    <div className="flex flex-col gap-3">
      {error && <Alert>{error.message}</Alert>}
      <DataTable columns={columns} rows={data} rowKey={(h) => h.id} loading={loading} emptyMessage="Aucune récolte enregistrée pour cet agriculteur." />
      <Pagination meta={meta} onPageChange={(page) => setParam('page', page)} />
    </div>
  )
}

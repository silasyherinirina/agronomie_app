import { useCallback, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useAuth } from "../../hooks/useAuth";
import { useResourceList } from "../../hooks/useResourceList";
import { harvestsApi } from "./api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { Button } from "../../components/Button";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { toApiError } from "../../services/http";
import type { Harvest } from "../../types/api";

export function HarvestsListPage() {
  const { user } = useAuth();
  const canManage =
    user?.role === "admin" || user?.role === "cooperative_manager";
  // Suppression restreinte à l'administrateur (traçabilité, cf. HarvestPolicy backend).
  const canDelete = user?.role === "admin";
  const [searchParams] = useSearchParams();

  const cooperativeId = searchParams.get("cooperative_id") ?? undefined;

  const plotId = searchParams.get("plot_id") ?? undefined;

  const campaignId = searchParams.get("campaign_id") ?? undefined;

  const list = useCallback(
    (params: Record<string, unknown>) =>
      harvestsApi.list({
        ...params,
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),
    [cooperativeId, plotId, campaignId],
  );
  const { data, meta, loading, error, setParam, refetch } =
    useResourceList(list);

  const [toDelete, setToDelete] = useState<Harvest | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function confirmDelete() {
    if (!toDelete) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await harvestsApi.remove(toDelete.id);
      setToDelete(null);
      refetch();
    } catch (err) {
      setDeleteError(toApiError(err).message);
    } finally {
      setDeleting(false);
    }
  }

  const columns: Column<Harvest>[] = [
    {
      key: "crop_cycle",
      header: "Cycle cultural",
      render: (h) =>
        h.crop_cycle
          ? `${h.crop_cycle.plot?.name ?? h.crop_cycle.plot_id} — ${h.crop_cycle.crop?.name ?? ""}`
          : h.crop_cycle_id,
    },
    { key: "harvest_date", header: "Date", render: (h) => h.harvest_date },
    {
      key: "quantity",
      header: "Quantité",
      render: (h) => `${h.quantity} ${h.unit}`,
      align: "right",
    },
    {
      key: "yield_value",
      header: "Rendement",
      render: (h) =>
        h.yield_value != null
          ? `${h.yield_value.toFixed(2)} (${h.yield_source === "declared" ? "déclaré" : "calculé"})`
          : "—",
      align: "right",
    },
    { key: "quality", header: "Qualité", render: (h) => h.quality ?? "—" },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (h: Harvest) => (
              <div className="flex items-center justify-end gap-3">
                <Link
                  to={`/harvests/${h.id}/edit`}
                  className="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                  <Pencil size={14} aria-hidden />
                  Modifier
                </Link>
                {canDelete && (
                  <Button variant="ghost" onClick={() => setToDelete(h)}>
                    <Trash2 size={14} aria-hidden />
                    Supprimer
                  </Button>
                )}
              </div>
            ),
          },
        ]
      : []),
  ];

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="Récoltes"
        description="Le rendement est calculé automatiquement (quantité / superficie) ou déclaré manuellement."
        action={
          canManage && (
            <Link to="/harvests/new">
              <Button>
                <Plus size={16} aria-hidden />
                Nouvelle récolte
              </Button>
            </Link>
          )
        }
      />

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        {error && <Alert>{error.message}</Alert>}
        <DataTable
          columns={columns}
          rows={data}
          rowKey={(h) => h.id}
          loading={loading}
          emptyMessage="Aucune récolte enregistrée."
        />
        <Pagination
          meta={meta}
          onPageChange={(page) => setParam("page", page)}
        />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer la récolte"
          message="Confirmer la suppression de cette récolte ?"
          busy={deleting}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
      {deleteError && <Alert>{deleteError}</Alert>}
    </div>
  );
}

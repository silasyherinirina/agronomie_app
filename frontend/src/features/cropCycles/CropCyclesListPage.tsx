import { useCallback, useState } from "react";
import { Link } from "react-router-dom";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useAuth } from "../../hooks/useAuth";
import { useResourceList } from "../../hooks/useResourceList";
import { cropCyclesApi } from "./api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { Button } from "../../components/Button";
import { Badge } from "../../components/Badge";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { toApiError } from "../../services/http";
import type { CropCycle } from "../../types/api";

export function CropCyclesListPage() {
  const { user } = useAuth();
  const canManage =
    user?.role === "admin" || user?.role === "cooperative_manager";

  const list = useCallback(
    (params: Record<string, unknown>) => cropCyclesApi.list(params),
    [],
  );
  const { data, meta, loading, error, params, setParam, refetch } =
    useResourceList(list);

  const [toDelete, setToDelete] = useState<CropCycle | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function confirmDelete() {
    if (!toDelete) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await cropCyclesApi.remove(toDelete.id);
      setToDelete(null);
      refetch();
    } catch (err) {
      setDeleteError(toApiError(err).message);
    } finally {
      setDeleting(false);
    }
  }

  const columns: Column<CropCycle>[] = [
    {
      key: "plot",
      header: "Parcelle",
      render: (c) => (c.plot ? `${c.plot?.name ?? ""}` : c.plot_id),
    },
    {
      key: "crop",
      header: "Culture",
      render: (c) => (c.crop ? `${c.crop?.name ?? ""}` : c.crop_id),
    },
    {
      key: "variety",
      header: "Variété",
      render: (c) => c.variety?.name ?? "—",
    },
    {
      key: "campaign",
      header: "Campagne",
      render: (c) => (c.campaign ? `${c.campaign?.name ?? ""}` : c.campaign_id),
    },
    {
      key: "planting_date",
      header: "Plantation",
      render: (c) => c.planting_date ?? "—",
    },
    {
      key: "harvests_count",
      header: "Nb Récoltes",
      render: (c) => c.harvests_count ?? "—",
      align: "right",
    },
    {
      key: "status",
      header: "Statut",
      render: (c) => <Badge label={c.status} />,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (c: CropCycle) => (
              <div className="flex items-center justify-end gap-3">
                <Link
                  to={`/crop-cycles/${c.id}/edit`}
                  className="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                  <Pencil size={14} aria-hidden />
                  Modifier
                </Link>
                <Button variant="ghost" onClick={() => setToDelete(c)}>
                  <Trash2 size={14} aria-hidden />
                  Supprimer
                </Button>
              </div>
            ),
          },
        ]
      : []),
  ];

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="Cycles culturaux"
        description="Association parcelle × culture × campagne, base des récoltes."
        action={
          canManage && (
            <Link to="/crop-cycles/new">
              <Button>
                <Plus size={16} aria-hidden />
                Nouveau cycle
              </Button>
            </Link>
          )
        }
      />

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <select
            value={String(params.status ?? "")}
            onChange={(e) => setParam("status", e.target.value || undefined)}
          >
            <option value="">Tous les statuts</option>
            <option value="in_progress">En cours</option>
            <option value="harvested">Récolté</option>
            <option value="failed">Échoué</option>
          </select>
        </div>

        {error && <Alert>{error.message}</Alert>}

        <DataTable
          columns={columns}
          rows={data}
          rowKey={(c) => c.id}
          loading={loading}
          emptyMessage="Aucun cycle cultural trouvé."
        />
        <Pagination
          meta={meta}
          onPageChange={(page) => setParam("page", page)}
        />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer le cycle cultural"
          message="Confirmer la suppression de ce cycle ? Impossible si des récoltes y sont encore rattachées."
          busy={deleting}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
      {deleteError && <Alert>{deleteError}</Alert>}
    </div>
  );
}

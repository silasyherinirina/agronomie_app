import { useCallback, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useAuth } from "../../hooks/useAuth";
import { useResourceList } from "../../hooks/useResourceList";
import { farmersApi } from "./api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { SearchInput } from "../../components/SearchInput";
import { Button } from "../../components/Button";
import { Badge } from "../../components/Badge";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { toApiError } from "../../services/http";
import type { Farmer } from "../../types/api";

export function FarmersListPage() {
  const { user } = useAuth();
  const canManage =
    user?.role === "admin" || user?.role === "cooperative_manager";
  const [searchParams] = useSearchParams();
  const cooperativeId = searchParams.get("cooperative_id") ?? undefined;
  const plotId = searchParams.get("plot_id") ?? undefined;
  const campaignId = searchParams.get("campaign_id") ?? undefined;

  const list = useCallback(
    (params: Record<string, unknown>) =>
      farmersApi.list({
        ...params,
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),
    [cooperativeId, plotId, campaignId],
  );
  const { data, meta, loading, error, params, setParam, refetch } =
    useResourceList(list);

  const [toDelete, setToDelete] = useState<Farmer | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function confirmDelete() {
    if (!toDelete) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await farmersApi.remove(toDelete.id);
      setToDelete(null);
      refetch();
    } catch (err) {
      setDeleteError(toApiError(err).message);
    } finally {
      setDeleting(false);
    }
  }

  const columns: Column<Farmer>[] = [
    {
      key: "full_name",
      header: "Nom complet",
      render: (f) => (
        <Link to={`/farmers/${f.id}`} className="text-primary hover:underline">
          {f.full_name}
        </Link>
      ),
    },
    {
      key: "cooperative",
      header: "Coopérative",
      render: (c) =>
        c.cooperative ? `${c.cooperative?.name ?? ""}` : c.cooperative_id,
    },
    {
      key: "join_date",
      header: "Date d'adhésion",
      render: (f) => f.join_date ?? "—",
    },
    {
      key: "plots_count",
      header: "nb Parcelles",
      render: (f) => f.plots_count ?? "—",
      align: "right",
    },
    {
      key: "status",
      header: "Statut",
      render: (f) => <Badge label={f.status} />,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (f: Farmer) => (
              <div className="flex items-center justify-end gap-3">
                <Link
                  to={`/farmers/${f.id}/edit`}
                  className="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                  <Pencil size={14} aria-hidden />
                  Modifier
                </Link>
                <Button variant="ghost" onClick={() => setToDelete(f)}>
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
        title="Agriculteurs"
        description="Membres rattachés aux coopératives."
        action={
          canManage && (
            <Link to="/farmers/new">
              <Button>
                <Plus size={16} aria-hidden />
                Nouvel agriculteur
              </Button>
            </Link>
          )
        }
      />

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <SearchInput
            value={String(params.search ?? "")}
            onChange={(v) => setParam("search", v || undefined)}
            placeholder="Rechercher par nom…"
          />
        </div>

        {error && <Alert>{error.message}</Alert>}

        <DataTable
          columns={columns}
          rows={data}
          rowKey={(f) => f.id}
          loading={loading}
          emptyMessage="Aucun agriculteur trouvé."
        />
        <Pagination
          meta={meta}
          onPageChange={(page) => setParam("page", page)}
        />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer l'agriculteur"
          message={`Confirmer la suppression de « ${toDelete.full_name} » ?`}
          busy={deleting}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
      {deleteError && <Alert>{deleteError}</Alert>}
    </div>
  );
}

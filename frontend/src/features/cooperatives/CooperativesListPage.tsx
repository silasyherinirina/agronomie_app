import { useCallback, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useAuth } from "../../hooks/useAuth";
import { useResourceList } from "../../hooks/useResourceList";
import { cooperativesApi } from "./api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { SearchInput } from "../../components/SearchInput";
import { Button } from "../../components/Button";
import { Badge } from "../../components/Badge";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { toApiError } from "../../services/http";
import type { Cooperative } from "../../types/api";

export function CooperativesListPage() {
  const { user } = useAuth();
  const canCreate = user?.role === "admin";
  const canDelete = user?.role === "admin";
  const [searchParams] = useSearchParams();
  const cooperativeId = searchParams.get("cooperative_id") ?? undefined;
  const plotId = searchParams.get("plot_id") ?? undefined;
  const campaignId = searchParams.get("campaign_id") ?? undefined;

  const list = useCallback(
    (params: Record<string, unknown>) =>
      cooperativesApi.list({
        ...params,
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),
    [cooperativeId, plotId, campaignId],
  );
  const { data, meta, loading, error, params, setParam, refetch } =
    useResourceList(list);

  const [toDelete, setToDelete] = useState<Cooperative | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function confirmDelete() {
    if (!toDelete) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await cooperativesApi.remove(toDelete.id);
      setToDelete(null);
      refetch();
    } catch (err) {
      setDeleteError(toApiError(err).message);
    } finally {
      setDeleting(false);
    }
  }

  const columns: Column<Cooperative>[] = [
    {
      key: "name",
      header: "Nom",
      render: (c) => (
        <Link
          to={`/cooperatives/${c.id}`}
          className="font-medium text-primary hover:underline"
        >
          {c.name}
        </Link>
      ),
    },
    { key: "region", header: "Région", render: (c) => c.region },
    { key: "district", header: "District", render: (c) => c.district ?? "—" },
    {
      key: "farmers_count",
      header: "Agriculteurs",
      render: (c) => c.farmers_count ?? "—",
      align: "right",
    },
    {
      key: "plots_count",
      header: "Parcelles",
      render: (c) => c.plots_count ?? "—",
      align: "right",
    },
    {
      key: "status",
      header: "Statut",
      render: (c) => <Badge label={c.status} />,
    },
    {
      key: "actions",
      header: "",
      align: "right",
      render: (c) => (
        <div className="flex items-center justify-end gap-3">
          <Link
            to={`/cooperatives/${c.id}/edit`}
            className="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
          >
            <Pencil size={14} aria-hidden />
            Modifier
          </Link>
          {canDelete && (
            <Button variant="ghost" onClick={() => setToDelete(c)}>
              <Trash2 size={14} aria-hidden />
              Supprimer
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="Coopératives"
        description="Gestion des coopératives agricoles membres de la plateforme."
        action={
          canCreate && (
            <Link to="/cooperatives/new">
              <Button>
                <Plus size={16} aria-hidden />
                Nouvelle coopérative
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
          rowKey={(c) => c.id}
          loading={loading}
          emptyMessage="Aucune coopérative trouvée."
        />
        <Pagination
          meta={meta}
          onPageChange={(page) => setParam("page", page)}
        />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer la coopérative"
          message={`Confirmer la suppression de « ${toDelete.name} » ? Cette action est réversible (suppression contrôlée).`}
          busy={deleting}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
      {deleteError && <Alert>{deleteError}</Alert>}
    </div>
  );
}

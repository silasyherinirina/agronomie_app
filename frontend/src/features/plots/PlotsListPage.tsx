import { useCallback, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useAuth } from "../../hooks/useAuth";
import { useResourceList } from "../../hooks/useResourceList";
import { plotsApi } from "./api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { SearchInput } from "../../components/SearchInput";
import { Button } from "../../components/Button";
import { Badge } from "../../components/Badge";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { toApiError } from "../../services/http";
import type { Plot } from "../../types/api";

export function PlotsListPage() {
  const { user } = useAuth();
  const canManage =
    user?.role === "admin" || user?.role === "cooperative_manager";
  const [searchParams] = useSearchParams();
  const cooperativeId = searchParams.get("cooperative_id") ?? undefined;
  const plotId = searchParams.get("plot_id") ?? undefined;
  const campaignId = searchParams.get("campaign_id") ?? undefined;

  const list = useCallback(
    (params: Record<string, unknown>) =>
      plotsApi.list({
        ...params,
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),
    [cooperativeId, plotId, campaignId],
  );
  const { data, meta, loading, error, params, setParam, refetch } =
    useResourceList(list);

  const [toDelete, setToDelete] = useState<Plot | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function confirmDelete() {
    if (!toDelete) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await plotsApi.remove(toDelete.id);
      setToDelete(null);
      refetch();
    } catch (err) {
      setDeleteError(toApiError(err).message);
    } finally {
      setDeleting(false);
    }
  }

  const columns: Column<Plot>[] = [
    { key: "name", header: "Nom", render: (p) => p.name },
    {
      key: "cooperative",
      header: "Coopérative",
      render: (c) =>
        c.cooperative ? `${c.cooperative?.name ?? ""}` : c.cooperative_id,
    },
    {
      key: "farmer",
      header: "Agriculteur exploitant",
      render: (c) => (c.farmer ? `${c.farmer?.full_name ?? ""}` : c.farmer_id),
    },
    {
      key: "area_ha",
      header: "Superficie (ha)",
      render: (p) => (p.area_ha != null ? p.area_ha.toFixed(4) : "—"),
      align: "right",
    },
    {
      key: "crop_cycles_count",
      header: "Cycles culturaux",
      render: (p) => p.crop_cycles_count ?? "—",
      align: "right",
    },
    {
      key: "status",
      header: "Statut",
      render: (p) => <Badge label={p.status} />,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (p: Plot) => (
              <div className="flex items-center justify-end gap-3">
                <Link
                  to={`/plots/${p.id}/edit`}
                  className="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                  <Pencil size={14} aria-hidden />
                  Modifier
                </Link>
                <Button variant="ghost" onClick={() => setToDelete(p)}>
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
        title="Parcelles"
        description="Géométrie stockée en PostGIS (WGS 84 / SRID 4326) — superficie calculée côté serveur, jamais saisie manuellement."
        action={
          canManage && (
            <Link to="/plots/new">
              <Button>
                <Plus size={16} aria-hidden />
                Nouvelle parcelle
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
          rowKey={(p) => p.id}
          loading={loading}
          emptyMessage="Aucune parcelle trouvée."
        />
        <Pagination
          meta={meta}
          onPageChange={(page) => setParam("page", page)}
        />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer la parcelle"
          message={`Confirmer la suppression de « ${toDelete.name} » ?`}
          busy={deleting}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
      {deleteError && <Alert>{deleteError}</Alert>}
    </div>
  );
}

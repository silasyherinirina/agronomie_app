import { useCallback, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useAuth } from "../../hooks/useAuth";
import { useResourceList } from "../../hooks/useResourceList";
import { campaignsApi } from "./api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { Button } from "../../components/Button";
import { Badge } from "../../components/Badge";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { toApiError } from "../../services/http";
import type { AgriculturalCampaign } from "../../types/api";

export function CampaignsListPage() {
  const { user } = useAuth();
  const isAdmin = user?.role === "admin";
  const [searchParams] = useSearchParams();

  const cooperativeId = searchParams.get("cooperative_id") ?? undefined;

  const plotId = searchParams.get("plot_id") ?? undefined;

  const campaignId = searchParams.get("campaign_id") ?? undefined;

  const list = useCallback(
    (params: Record<string, unknown>) =>
      campaignsApi.list({
        ...params,
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),
    [cooperativeId, plotId, campaignId],
  );
  const { data, meta, loading, error, params, setParam, refetch } =
    useResourceList(list);

  const [toDelete, setToDelete] = useState<AgriculturalCampaign | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function confirmDelete() {
    if (!toDelete) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await campaignsApi.remove(toDelete.id);
      setToDelete(null);
      refetch();
    } catch (err) {
      setDeleteError(toApiError(err).message);
    } finally {
      setDeleting(false);
    }
  }

  const columns: Column<AgriculturalCampaign>[] = [
    { key: "name", header: "Nom", render: (c) => c.name },
    { key: "year", header: "Année", render: (c) => c.year, align: "right" },
    { key: "season", header: "Saison", render: (c) => c.season ?? "—" },
    { key: "start_date", header: "Début", render: (c) => c.start_date },
    { key: "end_date", header: "Fin", render: (c) => c.end_date ?? "—" },
    {
      key: "status",
      header: "Statut",
      render: (c) => <Badge label={c.status} />,
    },
    ...(isAdmin
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (c: AgriculturalCampaign) => (
              <div className="flex items-center justify-end gap-3">
                <Link
                  to={`/campaigns/${c.id}/edit`}
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
        title="Campagnes agricoles"
        description="Permettent d'analyser la production dans le temps (saison/année)."
        action={
          isAdmin && (
            <Link to="/campaigns/new">
              <Button>
                <Plus size={16} aria-hidden />
                Nouvelle campagne
              </Button>
            </Link>
          )
        }
      />

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <select
            value={String(params.year ?? "")}
            onChange={(e) => setParam("year", e.target.value || undefined)}
          >
            <option value="">Toutes les années</option>
            {Array.from(
              { length: 10 },
              (_, i) => new Date().getFullYear() - i,
            ).map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </select>
        </div>

        {error && <Alert>{error.message}</Alert>}

        <DataTable
          columns={columns}
          rows={data}
          rowKey={(c) => c.id}
          loading={loading}
          emptyMessage="Aucune campagne trouvée."
        />
        <Pagination
          meta={meta}
          onPageChange={(page) => setParam("page", page)}
        />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer la campagne"
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

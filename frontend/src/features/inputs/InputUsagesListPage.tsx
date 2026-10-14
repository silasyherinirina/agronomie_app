import { useCallback, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { useAuth } from "../../hooks/useAuth";
import { useResourceList } from "../../hooks/useResourceList";
import { inputUsagesApi } from "./api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { Button } from "../../components/Button";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { toApiError } from "../../services/http";
import type { InputUsage } from "../../types/api";

export function InputUsagesListPage() {
  const { user } = useAuth();
  const [searchParams] = useSearchParams();

  const cooperativeId = searchParams.get("cooperative_id") ?? undefined;

  const plotId = searchParams.get("plot_id") ?? undefined;

  const campaignId = searchParams.get("campaign_id") ?? undefined;
  const canManage =
    user?.role === "admin" || user?.role === "cooperative_manager";
  // Suppression restreinte à l'administrateur (traçabilité, cf. InputUsagePolicy backend).
  const canDelete = user?.role === "admin";

  const list = useCallback(
    (params: Record<string, unknown>) =>
      inputUsagesApi.list({
        ...params,
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),
    [cooperativeId, plotId, campaignId],
  );
  const { data, meta, loading, error, setParam, refetch } =
    useResourceList(list);

  const [toDelete, setToDelete] = useState<InputUsage | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function confirmDelete() {
    if (!toDelete) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await inputUsagesApi.remove(toDelete.id);
      setToDelete(null);
      refetch();
    } catch (err) {
      setDeleteError(toApiError(err).message);
    } finally {
      setDeleting(false);
    }
  }

  const columns: Column<InputUsage>[] = [
    {
      key: "input",
      header: "Intrant",
      render: (u) => (u.input ? `${u.input?.name ?? ""}` : u.input_id),
    },
    {
      key: "plot",
      header: "Parcelle",
      render: (u) => (u.plot ? `${u.plot?.name ?? ""}` : u.plot_id),
    },
    {
      key: "campaign",
      header: "Campagne",
      render: (u) => (u.campaign ? `${u.campaign?.name ?? ""}` : u.campaign_id),
    },
    {
      key: "quantity",
      header: "Quantité",
      render: (u) => u.quantity,
      align: "right",
    },
    {
      key: "cost",
      header: "Coût (Ar)",
      render: (u) => (u.cost != null ? u.cost.toLocaleString("fr-FR") : "—"),
      align: "right",
    },
    { key: "date", header: "Date", render: (u) => u.date },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (u: InputUsage) => (
              <div className="flex items-center justify-end gap-3">
                <Link
                  to={`/inputs/usages/${u.id}/edit`}
                  className="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                  <Pencil size={14} aria-hidden />
                  Modifier
                </Link>
                {canDelete && (
                  <Button variant="ghost" onClick={() => setToDelete(u)}>
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
        title="Utilisations d'intrants"
        description="Suivi de la consommation d'intrants par parcelle et campagne."
        action={
          canManage && (
            <Link to="/inputs/usages/new">
              <Button>
                <Plus size={16} aria-hidden />
                Nouvelle utilisation
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
          rowKey={(u) => u.id}
          loading={loading}
          emptyMessage="Aucune utilisation enregistrée."
        />
        <Pagination
          meta={meta}
          onPageChange={(page) => setParam("page", page)}
        />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer l'utilisation"
          message="Confirmer la suppression de cette utilisation d'intrant ?"
          busy={deleting}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
      {deleteError && <Alert>{deleteError}</Alert>}
    </div>
  );
}

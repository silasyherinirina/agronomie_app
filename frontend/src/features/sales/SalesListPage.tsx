import { useCallback, useEffect, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import {
  Banknote,
  Package,
  Pencil,
  Plus,
  Receipt,
  Trash2,
  TrendingUp,
} from "lucide-react";
import { useAuth } from "../../hooks/useAuth";
import { useOptions } from "../../hooks/useOptions";
import { useResourceList } from "../../hooks/useResourceList";
import { salesApi, getSaleStatistics } from "./api";
import { cooperativesApi } from "../cooperatives/api";
import { farmersApi } from "../farmers/api";
import { cropsApi } from "../crops/api";
import { campaignsApi } from "../campaigns/api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { Button } from "../../components/Button";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { ConfirmDialog } from "../../components/ConfirmDialog";
import { toApiError } from "../../services/http";
import type { Sale, SaleStatistics } from "../../types/api";

function formatAmount(value: number, currency: string): string {
  return `${value.toLocaleString("fr-FR")} ${currency}`;
}

function KpiCard({
  icon: Icon,
  label,
  value,
}: {
  icon: typeof Banknote;
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-center gap-3 rounded-lg border border-line bg-surface p-4 shadow-sm">
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary-light text-primary">
        <Icon size={18} aria-hidden />
      </div>
      <div className="min-w-0">
        <p className="truncate text-lg font-bold leading-tight text-ink">
          {value}
        </p>
        <p className="mt-0.5 truncate text-[11px] font-medium uppercase tracking-wide text-muted">
          {label}
        </p>
      </div>
    </div>
  );
}

export function SalesListPage() {
  const { user } = useAuth();
  const canManage =
    user?.role === "admin" || user?.role === "cooperative_manager";
  const [searchParams] = useSearchParams();

  const cooperativeId = searchParams.get("cooperative_id") ?? undefined;

  const plotId = searchParams.get("plot_id") ?? undefined;

  const campaignId = searchParams.get("campaign_id") ?? undefined;
  // §11 : les filtres coopérative/agriculteur n'ont de sens que pour les rôles voyant plusieurs
  // coopératives — cooperative_manager/farmer sont déjà scopés côté serveur sur les leurs.
  const canFilterByCooperative =
    user?.role === "admin" || user?.role === "analyst";
  const canFilterByFarmer = user?.role !== "farmer";

  const { options: cooperatives } = useOptions(
    () => cooperativesApi.list({ per_page: 200 }),
    [],
  );
  const { options: farmers } = useOptions(
    () => farmersApi.list({ per_page: 200 }),
    [],
  );
  const { options: crops } = useOptions(
    () => cropsApi.list({ per_page: 200 }),
    [],
  );
  const { options: campaigns } = useOptions(
    () => campaignsApi.list({ per_page: 200 }),
    [],
  );

  const list = useCallback(
    (params: Record<string, unknown>) =>
      salesApi.list({
        ...params,
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),
    [cooperativeId, plotId, campaignId],
  );
  const { data, meta, loading, error, params, setParam, refetch } =
    useResourceList<Sale>(list);

  const [stats, setStats] = useState<SaleStatistics | null>(null);
  useEffect(() => {
    getSaleStatistics({
      ...params,
      cooperative_id: cooperativeId,
      plot_id: plotId,
      campaign_id: campaignId,
    })
      .then(setStats)
      .catch(() => setStats(null));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(params), cooperativeId, plotId, campaignId]);

  const [toDelete, setToDelete] = useState<Sale | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  async function confirmDelete() {
    if (!toDelete) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      await salesApi.remove(toDelete.id);
      setToDelete(null);
      refetch();
    } catch (err) {
      setDeleteError(toApiError(err).message);
    } finally {
      setDeleting(false);
    }
  }

  const columns: Column<Sale>[] = [
    { key: "sale_date", header: "Date", render: (s) => s.sale_date },
    { key: "crop", header: "Produit", render: (s) => s.crop?.name ?? "—" },
    {
      key: "variety",
      header: "Variété",
      render: (s) => s.variety?.name ?? "—",
    },
    {
      key: "cooperative",
      header: "Coopérative",
      render: (s) => s.cooperative?.name ?? "—",
    },
    { key: "buyer", header: "Acheteur", render: (s) => s.buyer_name ?? "—" },
    {
      key: "quantity",
      header: "Quantité",
      render: (s) => `${s.quantity.toLocaleString("fr-FR")} ${s.unit}`,
      align: "right",
    },
    {
      key: "unit_price",
      header: "Prix unitaire",
      render: (s) => formatAmount(s.unit_price, s.currency),
      align: "right",
    },
    {
      key: "total_amount",
      header: "Montant total",
      render: (s) => formatAmount(s.total_amount, s.currency),
      align: "right",
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (s: Sale) => (
              <div className="flex items-center justify-end gap-3">
                <Link
                  to={`/sales/${s.id}/edit`}
                  className="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                  <Pencil size={14} aria-hidden />
                  Modifier
                </Link>
                <Button variant="ghost" onClick={() => setToDelete(s)}>
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
        title="Ventes agricoles"
        description="Suivez la commercialisation et les revenus issus de la production agricole."
        action={
          canManage && (
            <Link to="/sales/new">
              <Button>
                <Plus size={16} aria-hidden />
                Nouvelle vente
              </Button>
            </Link>
          )
        }
      />

      {stats && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <KpiCard
            icon={Banknote}
            label="Chiffre d'affaires"
            value={formatAmount(stats.total_amount, "MGA")}
          />
          <KpiCard
            icon={Package}
            label="Quantité vendue"
            value={
              stats.quantity_by_unit.length
                ? stats.quantity_by_unit
                    .map(
                      (q) =>
                        `${q.total_quantity.toLocaleString("fr-FR")} ${q.unit}`,
                    )
                    .join(" + ")
                : "0"
            }
          />
          <KpiCard
            icon={Receipt}
            label="Nombre de ventes"
            value={stats.sales_count.toLocaleString("fr-FR")}
          />
          <KpiCard
            icon={TrendingUp}
            label="Prix moyen"
            value={formatAmount(stats.average_unit_price, "MGA")}
          />
        </div>
      )}

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <div className="flex flex-wrap items-center gap-3">
          <input
            type="date"
            aria-label="Période — début"
            value={String(params.start_date ?? "")}
            onChange={(e) =>
              setParam("start_date", e.target.value || undefined)
            }
          />
          <input
            type="date"
            aria-label="Période — fin"
            value={String(params.end_date ?? "")}
            onChange={(e) => setParam("end_date", e.target.value || undefined)}
          />
          {canFilterByCooperative && (
            <select
              value={String(params.cooperative_id ?? "")}
              onChange={(e) =>
                setParam("cooperative_id", e.target.value || undefined)
              }
            >
              <option value="">Toutes les coopératives</option>
              {cooperatives.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          )}
          {canFilterByFarmer && (
            <select
              value={String(params.farmer_id ?? "")}
              onChange={(e) =>
                setParam("farmer_id", e.target.value || undefined)
              }
            >
              <option value="">Tous les agriculteurs</option>
              {farmers.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.full_name}
                </option>
              ))}
            </select>
          )}
          <select
            value={String(params.crop_id ?? "")}
            onChange={(e) => setParam("crop_id", e.target.value || undefined)}
          >
            <option value="">Toutes les cultures</option>
            {crops.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
          <select
            value={String(params.campaign_id ?? "")}
            onChange={(e) =>
              setParam("campaign_id", e.target.value || undefined)
            }
          >
            <option value="">Toutes les campagnes</option>
            {campaigns.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name} ({c.year})
              </option>
            ))}
          </select>
        </div>

        {error && <Alert>{error.message}</Alert>}

        <DataTable
          columns={columns}
          rows={data}
          rowKey={(s) => s.id}
          loading={loading}
          emptyMessage="Aucune vente enregistrée."
        />
        <Pagination
          meta={meta}
          onPageChange={(page) => setParam("page", page)}
        />
      </div>

      {toDelete && (
        <ConfirmDialog
          title="Supprimer la vente"
          message="Confirmer la suppression de cette vente ?"
          busy={deleting}
          onConfirm={() => void confirmDelete()}
          onCancel={() => setToDelete(null)}
        />
      )}
      {deleteError && <Alert>{deleteError}</Alert>}
    </div>
  );
}

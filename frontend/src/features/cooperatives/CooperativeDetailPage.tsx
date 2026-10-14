import { useCallback, useEffect, useState, type ReactNode } from "react";
import { Link, useParams } from "react-router-dom";
import L from "leaflet";
import { cooperativesApi } from "./api";
import { farmersApi } from "../farmers/api";
import { ArrowLeft } from "lucide-react";
import { plotsApi } from "../plots/api";
import { getDashboardSummary } from "../dashboard/api";
import { useAuth } from "../../hooks/useAuth";
import { useResourceList } from "../../hooks/useResourceList";
import { Spinner } from "../../components/Spinner";
import { Alert } from "../../components/Alert";
import { Badge } from "../../components/Badge";
import { Button } from "../../components/Button";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { Tabs } from "../../components/Tabs";
import { LeafletMap } from "../../components/LeafletMap";
import { toApiError, type NormalizedError } from "../../services/http";
import type {
  Cooperative,
  DashboardSummary,
  Farmer,
  Plot,
} from "../../types/api";

const DEFAULT_CENTER: [number, number] = [
  Number(import.meta.env.VITE_MAP_DEFAULT_LAT ?? -18.8792),
  Number(import.meta.env.VITE_MAP_DEFAULT_LNG ?? 47.5079),
];
const DEFAULT_ZOOM = Number(import.meta.env.VITE_MAP_DEFAULT_ZOOM ?? 6);

export function CooperativeDetailPage() {
  const { id } = useParams();
  const { user } = useAuth();
  const [coop, setCoop] = useState<Cooperative | null>(null);
  const [error, setError] = useState<NormalizedError | null>(null);

  useEffect(() => {
    if (!id) return;
    cooperativesApi
      .get(Number(id))
      .then(setCoop)
      .catch((err) => setError(toApiError(err)));
  }, [id]);

  const canEdit =
    user?.role === "admin" ||
    (user?.role === "cooperative_manager" &&
      user.managed_cooperative_id === coop?.id);

  if (error) return <Alert>{error.message}</Alert>;
  if (!coop) return <Spinner label="Chargement de la fiche coopérative…" />;

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold text-ink">{coop.name}</h1>
          <Link
            to="/cooperatives"
            className="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition-colors hover:text-ink"
          >
            <ArrowLeft size={16} aria-hidden />
            Retour
          </Link>
          <p className="text-sm text-muted">
            {coop.region}
            {coop.district ? ` · ${coop.district}` : ""}
            {coop.commune ? ` · ${coop.commune}` : ""}
          </p>
        </div>
        {canEdit && (
          <Link to={`/cooperatives/${coop.id}/edit`}>
            <Button variant="secondary">Modifier</Button>
          </Link>
        )}
      </div>

      <Tabs
        items={[
          {
            key: "resume",
            label: "Résumé",
            content: (
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <SummaryStat
                  label="Statut"
                  value={<Badge label={coop.status} />}
                />
                <SummaryStat
                  label="Agriculteurs membres"
                  value={coop.farmers_count ?? "—"}
                />
                <SummaryStat
                  label="Parcelles"
                  value={coop.plots_count ?? "—"}
                />
                <SummaryStat
                  label="Localisation (siège)"
                  value={
                    coop.geometry
                      ? `${coop.geometry.coordinates[1].toFixed(5)}, ${coop.geometry.coordinates[0].toFixed(5)}`
                      : "Non renseignée"
                  }
                />
              </div>
            ),
          },
          {
            key: "farmers",
            label: "Agriculteurs",
            content: <CoopFarmersTab cooperativeId={coop.id} />,
          },
          {
            key: "plots",
            label: "Parcelles",
            content: <CoopPlotsTab cooperativeId={coop.id} />,
          },
          {
            key: "stats",
            label: "Cultures, récoltes & statistiques",
            content: <CoopStatsTab cooperativeId={coop.id} />,
          },
          {
            key: "map",
            label: "Carte",
            content: <CoopMapTab cooperativeId={coop.id} />,
          },
        ]}
      />
    </div>
  );
}

function SummaryStat({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="rounded-lg border border-line bg-surface p-4">
      <div className="text-xs uppercase tracking-wide text-muted">{label}</div>
      <div className="mt-1 text-lg font-semibold text-ink">{value}</div>
    </div>
  );
}

function CoopFarmersTab({ cooperativeId }: { cooperativeId: number }) {
  const list = useCallback(
    (params: Record<string, unknown>) =>
      farmersApi.list({ ...params, cooperative_id: cooperativeId }),
    [cooperativeId],
  );
  const { data, meta, loading, error, setParam } =
    useResourceList<Farmer>(list);

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
      key: "plots_count",
      header: "Parcelles",
      align: "right",
      render: (f) => f.plots_count ?? "—",
    },
    {
      key: "status",
      header: "Statut",
      render: (f) => <Badge label={f.status} />,
    },
  ];

  return (
    <div className="flex flex-col gap-3">
      {error && <Alert>{error.message}</Alert>}
      <DataTable
        columns={columns}
        rows={data}
        rowKey={(f) => f.id}
        loading={loading}
        emptyMessage="Aucun agriculteur membre."
      />
      <Pagination meta={meta} onPageChange={(page) => setParam("page", page)} />
    </div>
  );
}

function CoopPlotsTab({ cooperativeId }: { cooperativeId: number }) {
  const list = useCallback(
    (params: Record<string, unknown>) =>
      plotsApi.list({ ...params, cooperative_id: cooperativeId }),
    [cooperativeId],
  );
  const { data, meta, loading, error, setParam } = useResourceList<Plot>(list);

  const columns: Column<Plot>[] = [
    {
      key: "name",
      header: "Nom",
      render: (p) => (
        <Link
          to={`/plots/${p.id}/edit`}
          className="text-primary hover:underline"
        >
          {p.name}
        </Link>
      ),
    },
    {
      key: "area_ha",
      header: "Superficie (ha)",
      align: "right",
      render: (p) => p.area_ha?.toFixed(4) ?? "—",
    },
    {
      key: "status",
      header: "Statut",
      render: (p) => <Badge label={p.status} />,
    },
  ];

  return (
    <div className="flex flex-col gap-3">
      {error && <Alert>{error.message}</Alert>}
      <DataTable
        columns={columns}
        rows={data}
        rowKey={(p) => p.id}
        loading={loading}
        emptyMessage="Aucune parcelle enregistrée."
      />
      <Pagination meta={meta} onPageChange={(page) => setParam("page", page)} />
    </div>
  );
}

const th =
  "px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-muted";
const td = "px-3 py-2 text-sm text-ink";

function CoopStatsTab({ cooperativeId }: { cooperativeId: number }) {
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getDashboardSummary({ cooperative_id: cooperativeId })
      .then(setSummary)
      .catch((err) => setError(toApiError(err).message));
  }, [cooperativeId]);

  if (error) return <Alert>{error}</Alert>;
  if (!summary) return <Spinner label="Chargement des statistiques…" />;

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="mb-2 text-sm font-semibold text-ink">
          Production par culture
        </h3>
        <div className="overflow-x-auto">
          <table className="w-full border-collapse">
            <thead>
              <tr className="border-b border-line">
                <th className={th}>Culture</th>
                <th className={th}>Unité</th>
                <th className={th}>Quantité totale</th>
              </tr>
            </thead>
            <tbody>
              {summary.production_by_crop.length === 0 && (
                <tr>
                  <td colSpan={3} className={`${td} text-muted`}>
                    Aucune récolte enregistrée.
                  </td>
                </tr>
              )}
              {summary.production_by_crop.map((row) => (
                <tr
                  key={`${row.crop_id}-${row.unit}`}
                  className="border-b border-line last:border-0"
                >
                  <td className={td}>{row.crop_name}</td>
                  <td className={td}>{row.unit}</td>
                  <td className={td}>
                    {row.total_quantity.toLocaleString("fr-FR")}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div>
        <h3 className="mb-2 text-sm font-semibold text-ink">
          Consommation d'intrants
        </h3>
        <div className="overflow-x-auto">
          <table className="w-full border-collapse">
            <thead>
              <tr className="border-b border-line">
                <th className={th}>Intrant</th>
                <th className={th}>Quantité</th>
                <th className={th}>Coût (Ar)</th>
              </tr>
            </thead>
            <tbody>
              {summary.input_consumption.length === 0 && (
                <tr>
                  <td colSpan={3} className={`${td} text-muted`}>
                    Aucun intrant enregistré.
                  </td>
                </tr>
              )}
              {summary.input_consumption.map((row) => (
                <tr
                  key={row.input_id}
                  className="border-b border-line last:border-0"
                >
                  <td className={td}>{row.input_name}</td>
                  <td className={td}>
                    {row.total_quantity.toLocaleString("fr-FR")} {row.unit}
                  </td>
                  <td className={td}>
                    {row.total_cost.toLocaleString("fr-FR")}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <Link
        to={`/dashboard?cooperative_id=${cooperativeId}`}
        className="text-sm font-medium text-primary hover:underline"
      >
        Voir le tableau de bord complet de cette coopérative →
      </Link>
    </div>
  );
}

function CoopMapTab({ cooperativeId }: { cooperativeId: number }) {
  const [plots, setPlots] = useState<Plot[]>([]);
  const [map, setMap] = useState<L.Map | null>(null);

  useEffect(() => {
    plotsApi
      .list({ per_page: 500, cooperative_id: cooperativeId })
      .then((res) => setPlots(res.data));
  }, [cooperativeId]);

  useEffect(() => {
    if (!map) return;
    const layer = L.layerGroup().addTo(map);
    const bounds: L.LatLngExpression[] = [];
    plots
      .filter((p) => p.geometry)
      .forEach((p) => {
        const latLngs: L.LatLngExpression[] = p.geometry!.coordinates[0].map(
          ([lng, lat]) => [lat, lng],
        );
        bounds.push(...latLngs);
        L.polygon(latLngs, { color: "#1f7a4d", weight: 2, fillOpacity: 0.3 })
          .bindPopup(p.name)
          .addTo(layer);
      });
    if (bounds.length > 0)
      map.fitBounds(L.latLngBounds(bounds), { padding: [30, 30], maxZoom: 15 });
    return () => {
      layer.remove();
    };
  }, [map, plots]);

  return (
    <LeafletMap
      center={DEFAULT_CENTER}
      zoom={DEFAULT_ZOOM}
      onReady={setMap}
      className="h-[420px]"
    />
  );
}

import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { Link, useSearchParams } from "react-router-dom";
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import {
  AlertTriangle,
  ArrowRight,
  Banknote,
  Boxes,
  BrainCircuit,
  Building2,
  CalendarRange,
  CheckCircle2,
  CloudRain,
  CloudSun,
  Droplets,
  Map as MapIcon,
  RefreshCw,
  Sprout,
  Square,
  Sun,
  TrendingUp,
  Users,
  Wheat,
  Wind,
  type LucideIcon,
} from "lucide-react";

import { useAuth } from "../../hooks/useAuth";
import { useOptions } from "../../hooks/useOptions";
import { getDashboardSummary } from "./api";
import { cooperativesApi } from "../cooperatives/api";
import { campaignsApi } from "../campaigns/api";
import { getPlotCampaigns, plotsApi } from "../plots/api";
import { predictionsApi } from "../predictions/api";
import { getPlotWeather } from "../weather/api";
import { LeafletMap } from "../../components/LeafletMap";
import { MapErrorBoundary } from "../../components/MapErrorBoundary";
import { Alert } from "../../components/Alert";
import { Button } from "../../components/Button";
import { toApiError } from "../../services/http";

import type {
  AgriculturalCampaign,
  DashboardSummary,
  PlotWeather,
  ProductionPrediction,
  RecentActivityType,
  UnitTotal,
} from "../../types/api";

import L from "leaflet";

const DEFAULT_CENTER: [number, number] = [
  Number(import.meta.env.VITE_MAP_DEFAULT_LAT ?? -18.8792),
  Number(import.meta.env.VITE_MAP_DEFAULT_LNG ?? 47.5079),
];

const DEFAULT_ZOOM = Number(import.meta.env.VITE_MAP_DEFAULT_ZOOM ?? 6);

/* -------------------------------------------------------------------------- */
/* Helpers                                                                    */
/* -------------------------------------------------------------------------- */

const ACTIVITY_ICONS: Record<RecentActivityType, LucideIcon> = {
  farmer_created: Users,
  plot_created: Square,
  harvest_recorded: Wheat,
  input_usage_recorded: Boxes,
};

function dominantUnit(totals: UnitTotal[]): string | null {
  if (!totals.length) return null;

  return [...totals].sort((a, b) => b.total_quantity - a.total_quantity)[0]
    .unit;
}

function buildDetailUrl(
  path: string,
  params: Record<string, string | undefined>,
): string {
  const searchParams = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value) {
      searchParams.set(key, value);
    }
  });

  const queryString = searchParams.toString();
  return queryString ? `${path}?${queryString}` : path;
}

/* -------------------------------------------------------------------------- */
/* UI primitives                                                              */
/* -------------------------------------------------------------------------- */

function StatItem({
  icon: Icon,
  value,
  label,
  to,
}: {
  icon: LucideIcon;
  value: string;
  label: string;
  to?: string;
}) {
  const content = (
    <>
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary-light text-primary">
        <Icon size={18} />
      </div>

      <div className="min-w-0">
        <p className="truncate text-lg font-bold leading-tight text-ink">
          {value}
        </p>
        <p className="mt-1 truncate text-[11px] font-medium uppercase tracking-wide text-muted">
          {label}
        </p>
      </div>
    </>
  );

  const sharedClassName =
    "flex min-w-[150px] flex-1 items-center gap-3 border-b border-line px-4 py-4 last:border-b-0 sm:border-b-0 sm:border-r sm:last:border-r-0 lg:px-5";

  if (to) {
    return (
      <Link
        to={to}
        className={`${sharedClassName} cursor-pointer transition-colors hover:bg-primary-light/40`}
      >
        {content}
      </Link>
    );
  }

  return <div className={sharedClassName}>{content}</div>;
}

function StatStrip({ children }: { children: ReactNode }) {
  return (
    <div className="grid overflow-hidden rounded-2xl border border-line bg-surface shadow-sm sm:grid-cols-2 lg:grid-cols-5">
      {children}
    </div>
  );
}

function KpiCard({
  icon: Icon,
  value,
  label,
  description,
  to,
}: {
  icon: LucideIcon;
  value: string;
  label: string;
  description?: string;
  to?: string;
}) {
  const content = (
    <>
      <div className="flex items-start justify-between gap-3">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-accent-light text-accent">
          <Icon size={20} />
        </div>

        <TrendingUp
          size={16}
          className="text-muted opacity-50 transition-opacity group-hover:opacity-100"
        />
      </div>

      <p className="mt-5 truncate text-xl font-bold text-ink">{value}</p>

      <p className="mt-1 text-xs font-medium uppercase tracking-wide text-muted">
        {label}
      </p>

      {description && (
        <p className="mt-2 text-xs leading-relaxed text-muted">{description}</p>
      )}
    </>
  );

  const className =
    "group block rounded-2xl border border-line bg-surface p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md cursor-pointer";

  if (to) {
    return (
      <Link to={to} className={className}>
        {content}
      </Link>
    );
  }

  return <div className={className}>{content}</div>;
}

function SectionCard({
  title,
  icon: Icon,
  hint,
  action,
  children,
  className = "",
}: {
  title: string;
  icon?: LucideIcon;
  hint?: string;
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <section
      className={`flex min-w-0 flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-sm ${className}`}
    >
      <div className="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
        <div className="min-w-0">
          <h2 className="flex items-center gap-2 text-sm font-bold text-ink">
            {Icon && (
              <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-primary-light text-primary">
                <Icon size={16} />
              </span>
            )}

            <span className="truncate">{title}</span>
          </h2>

          {hint && (
            <p className="mt-1 text-xs leading-relaxed text-muted">{hint}</p>
          )}
        </div>

        {action}
      </div>

      <div className="min-w-0 flex-1 p-5 sm:p-6">{children}</div>
    </section>
  );
}

function EmptyState({
  message,
  action,
}: {
  message: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex min-h-[120px] flex-col items-center justify-center gap-3 text-center">
      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-canvas text-muted">
        <Sprout size={18} />
      </div>

      <p className="max-w-md text-sm text-muted">{message}</p>

      {action}
    </div>
  );
}

function DashboardSkeleton() {
  return (
    <div
      className="flex flex-col gap-5"
      aria-busy="true"
      aria-label="Chargement du tableau de bord"
    >
      <div className="grid overflow-hidden rounded-2xl bg-surface shadow-sm sm:grid-cols-2 lg:grid-cols-5">
        {Array.from({ length: 5 }).map((_, index) => (
          <div
            key={index}
            className="flex items-center gap-3 border-b border-line px-5 py-5 last:border-b-0 lg:border-b-0 lg:border-r"
          >
            <div className="h-10 w-10 animate-pulse rounded-xl bg-canvas" />

            <div className="flex flex-1 flex-col gap-2">
              <div className="h-5 w-16 animate-pulse rounded bg-canvas" />
              <div className="h-3 w-24 animate-pulse rounded bg-canvas" />
            </div>
          </div>
        ))}
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        {Array.from({ length: 3 }).map((_, index) => (
          <div
            key={index}
            className="h-32 animate-pulse rounded-2xl bg-surface shadow-sm"
          />
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {Array.from({ length: 4 }).map((_, index) => (
          <div
            key={index}
            className="h-80 animate-pulse rounded-2xl bg-surface shadow-sm"
          />
        ))}
      </div>
    </div>
  );
}

const th =
  "px-3 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-muted";

const td = "px-3 py-3 text-sm text-ink";

/* Hauteur maximale partagée par les listes/tableaux scrollables du Dashboard (Activité
 * récente, Alertes, Dernières ventes, Récoltes par unité, Statistiques géographiques) : une
 * seule valeur harmonisée plutôt qu'une hauteur libre par section, pour un rendu visuellement
 * cohérent quel que soit le volume de données réel. */
const scrollSectionHeight = "max-h-[320px] md:max-h-[360px] lg:max-h-[380px]";

/* -------------------------------------------------------------------------- */
/* Dashboard                                                                  */
/* -------------------------------------------------------------------------- */

export function DashboardPage() {
  const { user } = useAuth();

  const isScopedRole =
    user?.role === "cooperative_manager" || user?.role === "farmer";

  const { options: cooperatives } = useOptions(
    () => cooperativesApi.list({ per_page: 200 }),
    [],
  );

  const { options: campaigns } = useOptions(
    () => campaignsApi.list({ per_page: 200 }),
    [],
  );

  // Filtre Parcelle (contexte de référence) : réutilise la même liste de parcelles que celle
  // déjà chargée pour la carte plus bas — aucune requête supplémentaire ici. `cooperative_id`/
  // `cooperative` sont déjà présents sur chaque parcelle (voir PlotResource), donc la
  // coopérative associée se déduit directement de cette liste, sans nouvel appel réseau.
  const { options: plotOptions } = useOptions(
    () => plotsApi.list({ per_page: 500 }),
    [],
  );

  const [searchParams, setSearchParams] = useSearchParams();

  const [plotId, setPlotId] = useState(searchParams.get("plot_id") ?? "");

  const [cooperativeId, setCooperativeId] = useState(
    searchParams.get("cooperative_id") ?? "",
  );

  const [campaignId, setCampaignId] = useState(
    searchParams.get("campaign_id") ?? "",
  );

  useEffect(() => {
    const nextPlotId = searchParams.get("plot_id") ?? "";
    const nextCooperativeId = searchParams.get("cooperative_id") ?? "";
    const nextCampaignId = searchParams.get("campaign_id") ?? "";

    setPlotId((current) => (current === nextPlotId ? current : nextPlotId));

    setCooperativeId((current) =>
      current === nextCooperativeId ? current : nextCooperativeId,
    );

    setCampaignId((current) =>
      current === nextCampaignId ? current : nextCampaignId,
    );
  }, [searchParams]);

  const selectedPlot = useMemo(
    () => plotOptions.find((plot) => String(plot.id) === plotId) ?? null,
    [plotOptions, plotId],
  );

  // Campagnes RÉELLEMENT associées à la parcelle sélectionnée (relation Plot -> CropCycle ->
  // AgriculturalCampaign, renvoyée par le backend — jamais devinée ni filtrée par nom côté
  // frontend). Chargées à la demande uniquement quand une parcelle précise est active.
  const [plotCampaigns, setPlotCampaigns] = useState<AgriculturalCampaign[]>(
    [],
  );

  const [plotContextLoading, setPlotContextLoading] = useState(false);

  const [plotContextError, setPlotContextError] = useState<string | null>(null);

  // Identifiant de requête : une parcelle changée rapidement ne doit jamais laisser une
  // réponse arrivée en retard écraser l'état de la parcelle réellement sélectionnée.
  const plotCampaignsRequestRef = useRef(0);

  const loadPlotCampaigns = useCallback((id: string) => {
    const requestId = ++plotCampaignsRequestRef.current;
    setPlotContextLoading(true);
    setPlotContextError(null);

    getPlotCampaigns(Number(id))
      .then((result) => {
        if (plotCampaignsRequestRef.current !== requestId) return;
        setPlotCampaigns(result);
      })
      .catch(() => {
        if (plotCampaignsRequestRef.current !== requestId) return;
        setPlotContextError(
          "Impossible de charger les relations de cette parcelle.",
        );
      })
      .finally(() => {
        if (plotCampaignsRequestRef.current === requestId) {
          setPlotContextLoading(false);
        }
      });
  }, []);

  // Changement de parcelle : les relations de contexte sont rechargées pour la nouvelle
  // parcelle uniquement. La coopérative est définie dans handlePlotChange(), ce qui évite
  // une cascade de setState déclenchée par un effet secondaire.
  useEffect(() => {
    if (!plotId) {
      setPlotCampaigns([]);
      setPlotContextError(null);
      setPlotContextLoading(false);
      return;
    }

    setPlotCampaigns([]);
    loadPlotCampaigns(plotId);
  }, [plotId, loadPlotCampaigns]);

  /** Sélection d'une parcelle : elle devient la source de vérité du contexte. */
  function handlePlotChange(value: string) {
    const nextParams = new URLSearchParams();

    if (value) {
      const plot = plotOptions.find((item) => String(item.id) === value);

      const nextCooperativeId = plot?.cooperative_id
        ? String(plot.cooperative_id)
        : "";

      nextParams.set("plot_id", value);

      if (nextCooperativeId) {
        nextParams.set("cooperative_id", nextCooperativeId);
      }
    }

    // Une nouvelle parcelle réinitialise toujours la campagne.
    setSearchParams(nextParams, {
      replace: true,
    });
  }

  function handleCampaignChange(value: string) {
    const nextParams = new URLSearchParams(searchParams);

    if (value) {
      nextParams.set("campaign_id", value);
    } else {
      nextParams.delete("campaign_id");
    }

    setSearchParams(nextParams, {
      replace: true,
    });
  }

  /** Changement de coopérative : une parcelle sélectionnée dans l'ancienne coopérative
   * ne doit jamais rester active. */
  function handleCooperativeChange(value: string) {
    const nextParams = new URLSearchParams();

    if (value) {
      nextParams.set("cooperative_id", value);
    }

    // Changer de coopérative réinitialise la parcelle
    // et la campagne, car elles appartiennent à l'ancien contexte.
    setSearchParams(nextParams, {
      replace: true,
    });
  }

  const [summary, setSummary] = useState<DashboardSummary | null>(null);

  const [loading, setLoading] = useState(true);

  const [error, setError] = useState<string | null>(null);

  /* ------------------------------------------------------------------------ */
  /* Dashboard summary                                                       */
  /* ------------------------------------------------------------------------ */

  const fetchSummary = useCallback(() => {
    setLoading(true);
    setError(null);

    return getDashboardSummary({
      plot_id: plotId ? Number(plotId) : undefined,

      cooperative_id: cooperativeId ? Number(cooperativeId) : undefined,

      campaign_id: campaignId ? Number(campaignId) : undefined,
    })
      .then(setSummary)
      .catch((err) => {
        setError(toApiError(err).message);
      })
      .finally(() => {
        setLoading(false);
      });
  }, [plotId, cooperativeId, campaignId]);

  useEffect(() => {
    fetchSummary();
  }, [fetchSummary]);

  /* ------------------------------------------------------------------------ */
  /* Last AI prediction                                                      */
  /* ------------------------------------------------------------------------ */

  // undefined = chargement (skeleton) ; null = état vide ; objet = dernière prédiction.
  const [lastPrediction, setLastPrediction] = useState<
    ProductionPrediction | null | undefined
  >(undefined);

  // Garde-fou anti-obsolescence (même mécanisme que `loadPlotCampaigns` ci-dessous) : un
  // changement rapide de parcelle ne doit jamais laisser une réponse arrivée en retard afficher
  // la prédiction d'une AUTRE parcelle que celle actuellement sélectionnée (§12).
  const predictionRequestRef = useRef(0);

  useEffect(() => {
    const requestId = ++predictionRequestRef.current;
    // Reset immédiat vers l'état "chargement" — jamais la prédiction de l'ancienne parcelle
    // affichée pendant qu'on récupère celle de la nouvelle (§8).
    setLastPrediction(undefined);

    // GET /predictions?plot_id=... (déjà supporté par ProductionPredictionController::index(),
    // déjà scopé par rôle, déjà trié par date décroissante) — réutilisé tel quel, sans nouvel
    // endpoint ni filtrage frontend d'une liste complète (§6/§7).
    predictionsApi
      .list({ plot_id: plotId ? Number(plotId) : undefined, per_page: 1 })
      .then((res) => {
        if (predictionRequestRef.current !== requestId) return;
        setLastPrediction(res.data[0] ?? null);
      })
      .catch(() => {
        if (predictionRequestRef.current !== requestId) return;
        setLastPrediction(null);
      });
  }, [plotId]);

  /* ------------------------------------------------------------------------ */
  /* Plots                                                                    */
  /* ------------------------------------------------------------------------ */

  const [map, setMap] = useState<L.Map | null>(null);

  // Une seule source de données pour les parcelles. Le select charge au maximum 500 parcelles
  // via useOptions() ci-dessus ; la carte et la liste utilisent ensuite ce même tableau.
  // Le filtre coopérative est local : cela évite de relancer GET /plots à chaque changement
  // de coopérative et évite les réponses concurrentes qui provoquaient ERR_INSUFFICIENT_RESOURCES.
  const plots = useMemo(
    () =>
      cooperativeId
        ? plotOptions.filter(
            (plot) => String(plot.cooperative_id) === String(cooperativeId),
          )
        : plotOptions,
    [plotOptions, cooperativeId],
  );

  /* ------------------------------------------------------------------------ */
  /* Leaflet layers                                                           */
  /* ------------------------------------------------------------------------ */

  useEffect(() => {
    if (!map) {
      return;
    }

    let frameId: number | null = null;
    const layer = L.layerGroup().addTo(map);
    const boundsPoints: L.LatLngExpression[] = [];

    // Une parcelle sélectionnée est prioritaire. Sinon, la carte affiche les parcelles
    // correspondant au filtre coopérative courant.
    const plotsToRender = plotId && selectedPlot ? [selectedPlot] : plots;

    plotsToRender
      .filter((plot) => plot.geometry)
      .forEach((plot) => {
        const latLngs: L.LatLngExpression[] = plot.geometry!.coordinates[0].map(
          ([lng, lat]) => [lat, lng],
        );

        boundsPoints.push(...latLngs);

        L.polygon(latLngs, {
          color: "#1f7a4d",
          weight: 2,
          fillOpacity: 0.3,
        })
          .bindPopup(plot.name)
          .addTo(layer);
      });

    if (boundsPoints.length > 0) {
      // Évite qu'un ancien zoom animé continue pendant le remplacement de la couche.
      // C'est précisément la situation qui peut produire l'erreur Leaflet `_leaflet_pos`.
      try {
        map.stop();
      } catch {
        // La carte peut être en cours de destruction par le composant parent.
      }

      const bounds = L.latLngBounds(boundsPoints);

      frameId = window.requestAnimationFrame(() => {
        try {
          if (map.getContainer().isConnected) {
            map.fitBounds(bounds, {
              padding: [30, 30],
              maxZoom: 14,
              animate: false,
            });
          }
        } catch {
          // Ne pas faire remonter une transition Leaflet interrompue au niveau global.
        }
      });
    }

    return () => {
      if (frameId !== null) {
        window.cancelAnimationFrame(frameId);
      }

      try {
        map.stop();
      } catch {
        // La carte peut déjà être détruite.
      }

      layer.remove();
    };
  }, [map, plots, plotId, selectedPlot]);

  /* ------------------------------------------------------------------------ */
  /* Weather                                                                  */
  /* ------------------------------------------------------------------------ */

  const representativePlot = useMemo(
    () => plots.find((plot) => plot.geometry) ?? null,
    [plots],
  );

  const [weather, setWeather] = useState<PlotWeather | null | undefined>(
    undefined,
  );

  useEffect(() => {
    if (!representativePlot) {
      setWeather(null);
      return;
    }

    setWeather(undefined);

    getPlotWeather(representativePlot.id)
      .then(setWeather)
      .catch(() => setWeather(null));
  }, [representativePlot]);

  /* ------------------------------------------------------------------------ */
  /* KPI calculations                                                         */
  /* ------------------------------------------------------------------------ */

  const primaryUnit = summary
    ? dominantUnit(summary.harvest_totals_by_unit)
    : null;

  const primaryTotal = summary?.harvest_totals_by_unit.find(
    (item) => item.unit === primaryUnit,
  );

  const primaryYield = summary?.average_yield_by_unit.find(
    (item) => item.unit === primaryUnit,
  );

  const totalInputCost = useMemo(
    () =>
      (summary?.input_consumption ?? []).reduce(
        (sum, row) => sum + row.total_cost,
        0,
      ),
    [summary],
  );

  const detailUrls = useMemo(
    () => ({
      cooperatives: buildDetailUrl("/cooperatives", {
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),

      farmers: buildDetailUrl("/farmers", {
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),

      plots: buildDetailUrl("/plots", {
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),

      campaigns: buildDetailUrl("/campaigns", {
        cooperative_id: cooperativeId,
        plot_id: plotId,
        campaign_id: campaignId,
      }),

      sales: buildDetailUrl("/sales", {
        cooperative_id: cooperativeId,
        campaign_id: campaignId,
        plot_id: plotId,
      }),

      harvests: buildDetailUrl("/harvests", {
        cooperative_id: cooperativeId,
        campaign_id: campaignId,
        plot_id: plotId,
      }),

      inputUsages: buildDetailUrl("/inputs/usages", {
        cooperative_id: cooperativeId,
        campaign_id: campaignId,
        plot_id: plotId,
      }),
    }),
    [cooperativeId, campaignId, plotId],
  );

  /* ------------------------------------------------------------------------ */
  /* Charts                                                                   */
  /* ------------------------------------------------------------------------ */

  const productionChartData = useMemo(
    () =>
      (summary?.production_by_crop ?? [])
        .filter((row) => row.unit === primaryUnit)
        .map((row) => ({
          name: row.crop_name,
          quantite: row.total_quantity,
        })),
    [summary, primaryUnit],
  );

  const evolutionChartData = useMemo(
    () =>
      (summary?.production_by_campaign ?? [])
        .filter((row) => row.unit === primaryUnit)
        .map((row) => ({
          name: `${row.campaign_name} (${row.year})`,
          quantite: row.total_quantity,
        })),
    [summary, primaryUnit],
  );

  // Module M — Ventes agricoles. `total_amount` est une grandeur monétaire homogène (toujours
  // en MGA par défaut, voir Sale.currency) : sommable directement, contrairement aux quantités
  // de récolte ci-dessus qui restent groupées par unité.
  const salesByCropChartData = useMemo(
    () =>
      (summary?.sales.amount_by_crop ?? []).map((row) => ({
        name: row.crop_name,
        montant: row.total_amount,
      })),
    [summary],
  );

  const salesEvolutionChartData = useMemo(
    () =>
      (summary?.sales_by_campaign ?? []).map((row) => ({
        name: `${row.campaign_name} (${row.year})`,
        montant: row.total_amount,
      })),
    [summary],
  );

  const inputChartData = useMemo(
    () =>
      (summary?.input_consumption ?? []).map((row) => ({
        name: row.input_name,
        cout: row.total_cost,
      })),
    [summary],
  );

  /* ------------------------------------------------------------------------ */
  /* UI                                                                       */
  /* ------------------------------------------------------------------------ */

  return (
    <div className="min-h-full bg-canvas">
      <div className="flex flex-col gap-5">
        {/* ================================================================ */}
        {/* HEADER                                                            */}
        {/* ================================================================ */}

        <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
          <div>
            <p className="mb-1 text-xs font-semibold uppercase tracking-[0.12em] text-primary">
              Vue d'ensemble agricole
            </p>

            <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-[1.75rem]">
              Bienvenue
              {user?.name ? `, ${user.name}` : ""}
            </h1>

            <p className="mt-1 max-w-2xl text-sm text-muted">
              Visualisez les principaux indicateurs de votre activité agricole
              et prenez des décisions basées sur vos données réelles.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {!isScopedRole && (
              <>
                {/* Parcelle : contexte de référence (§ Dashboard — filtre Parcelle). Une
                    sélection dérive automatiquement Coopérative/Campagne ci-dessous à partir
                    des relations réelles de la parcelle, jamais d'une correspondance devinée. */}
                <select
                  aria-label="Filtrer par parcelle"
                  className="min-w-[190px] rounded-xl border border-line bg-surface px-3 py-2.5 text-sm font-medium text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                  value={plotId}
                  onChange={(event) => handlePlotChange(event.target.value)}
                >
                  <option value="">Toutes les parcelles</option>

                  {plotOptions.map((plot) => (
                    <option key={plot.id} value={plot.id}>
                      {plot.name}
                    </option>
                  ))}
                </select>

                <select
                  aria-label="Filtrer par coopérative"
                  className="min-w-[190px] rounded-xl border border-line bg-surface px-3 py-2.5 text-sm font-medium text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70"
                  value={cooperativeId}
                  onChange={(event) =>
                    handleCooperativeChange(event.target.value)
                  }
                  disabled={Boolean(plotId) && !selectedPlot?.cooperative_id}
                >
                  {plotId ? (
                    selectedPlot?.cooperative_id ? (
                      <>
                        <option value="">Toutes les coopératives</option>
                        <option value={selectedPlot.cooperative_id}>
                          {selectedPlot.cooperative?.name ??
                            `Coopérative #${selectedPlot.cooperative_id}`}
                        </option>
                      </>
                    ) : (
                      <option value="">Aucune coopérative associée</option>
                    )
                  ) : (
                    <>
                      <option value="">Toutes les coopératives</option>

                      {cooperatives.map((cooperative) => (
                        <option key={cooperative.id} value={cooperative.id}>
                          {cooperative.name}
                        </option>
                      ))}
                    </>
                  )}
                </select>

                <select
                  aria-label="Filtrer par campagne"
                  className="min-w-[180px] rounded-xl border border-line bg-surface px-3 py-2.5 text-sm font-medium text-ink shadow-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-70"
                  value={campaignId}
                  onChange={(event) => handleCampaignChange(event.target.value)}
                  disabled={
                    Boolean(plotId) &&
                    (plotContextLoading ||
                      (!plotContextError && plotCampaigns.length === 0))
                  }
                >
                  {plotId ? (
                    plotContextLoading ? (
                      <option value="">Chargement…</option>
                    ) : plotContextError ? (
                      <option value="">Erreur de chargement</option>
                    ) : plotCampaigns.length > 0 ? (
                      <>
                        <option value="">Toutes les campagnes</option>
                        {plotCampaigns.map((campaign) => (
                          <option key={campaign.id} value={campaign.id}>
                            {campaign.name} ({campaign.year})
                          </option>
                        ))}
                      </>
                    ) : (
                      <option value="">Aucune campagne associée</option>
                    )
                  ) : (
                    <>
                      <option value="">Toutes les campagnes</option>

                      {campaigns.map((campaign) => (
                        <option key={campaign.id} value={campaign.id}>
                          {campaign.name} ({campaign.year})
                        </option>
                      ))}
                    </>
                  )}
                </select>
              </>
            )}

            <Button
              variant="secondary"
              onClick={fetchSummary}
              disabled={loading}
              className="gap-2 shadow-sm"
            >
              <RefreshCw size={16} className={loading ? "animate-spin" : ""} />
              Actualiser
            </Button>
          </div>
        </div>

        {/* Erreur de chargement des relations de la parcelle (§ Dashboard — filtre Parcelle) :
            message discret + retry, distinct de l'erreur générale du tableau de bord
            ci-dessous — ne bloque jamais l'affichage. */}
        {!isScopedRole && plotId && plotContextError && (
          <p className="-mt-3 flex flex-wrap items-center gap-2 text-xs text-warning">
            {plotContextError}
            <button
              type="button"
              onClick={() => loadPlotCampaigns(plotId)}
              className="font-semibold underline underline-offset-2"
            >
              Réessayer
            </button>
          </p>
        )}

        {/* ================================================================ */}
        {/* ERROR                                                             */}
        {/* ================================================================ */}

        {error && (
          <Alert>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <span>{error}</span>

              <Button variant="secondary" onClick={fetchSummary}>
                Réessayer
              </Button>
            </div>
          </Alert>
        )}

        {/* ================================================================ */}
        {/* LOADING                                                           */}
        {/* ================================================================ */}

        {loading && <DashboardSkeleton />}

        {!loading && summary && (
          <>
            {/* ============================================================ */}
            {/* MAIN STATISTICS                                               */}
            {/* ============================================================ */}

            <StatStrip>
              <StatItem
                icon={Building2}
                value={String(summary.kpis.cooperatives_count ?? 0)}
                label="Coopératives"
                to={detailUrls.cooperatives}
              />

              <StatItem
                icon={Users}
                value={String(summary.kpis.farmers_count ?? 0)}
                label="Agriculteurs"
                to={detailUrls.farmers}
              />

              <StatItem
                icon={Square}
                value={String(summary.kpis.plots_count ?? 0)}
                label="Parcelles"
                to={detailUrls.plots}
              />

              <StatItem
                icon={MapIcon}
                value={`${summary.kpis.total_area_ha.toFixed(2)} ha`}
                label="Superficie"
                to={detailUrls.plots}
              />

              <StatItem
                icon={CalendarRange}
                value={String(summary.kpis.campaigns_count ?? 0)}
                label="Campagnes"
                to={detailUrls.campaigns}
              />
            </StatStrip>

            {/* ============================================================ */}
            {/* KPI PRODUCTION                                                */}
            {/* ============================================================ */}

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <KpiCard
                icon={Banknote}
                value={`${summary.sales.total_amount.toLocaleString("fr-FR")} MGA`}
                label="Chiffre d'affaires"
                description="Montant total des ventes enregistrées (Module Ventes)."
                to={detailUrls.sales}
              />

              <KpiCard
                icon={Wheat}
                value={
                  primaryTotal
                    ? `${primaryTotal.total_quantity.toLocaleString(
                        "fr-FR",
                      )} ${primaryTotal.unit}`
                    : "—"
                }
                label="Production totale"
                description="Quantité totale issue des récoltes enregistrées."
                to={detailUrls.harvests}
              />

              <KpiCard
                icon={TrendingUp}
                value={
                  primaryYield
                    ? `${primaryYield.average_yield.toLocaleString(
                        "fr-FR",
                      )} ${primaryUnit}/ha`
                    : "—"
                }
                label="Rendement moyen"
                description="Rendement moyen calculé sur les récoltes disponibles."
                to={detailUrls.harvests}
              />

              <KpiCard
                icon={Boxes}
                value={`${totalInputCost.toLocaleString("fr-FR")} Ar`}
                label="Coût des intrants"
                description="Coût cumulé des intrants agricoles enregistrés."
                to={detailUrls.inputUsages}
              />
            </div>

            {/* ============================================================ */}
            {/* MAP + WEATHER                                                  */}
            {/* ============================================================ */}

            <div className="grid gap-4 xl:grid-cols-[1.55fr_0.9fr]">
              {/* FIELD OVERVIEW */}

              <SectionCard
                title="Aperçu des parcelles"
                icon={MapIcon}
                action={
                  <Link
                    to="/map"
                    className="inline-flex items-center gap-1 text-xs font-semibold text-primary transition hover:gap-2 hover:underline"
                  >
                    Voir la carte
                    <ArrowRight size={14} />
                  </Link>
                }
              >
                <div className="grid gap-4 lg:grid-cols-[1fr_190px]">
                  <div className="overflow-hidden rounded-xl border border-line bg-canvas">
                    <MapErrorBoundary>
                      <LeafletMap
                        center={DEFAULT_CENTER}
                        zoom={DEFAULT_ZOOM}
                        onReady={setMap}
                        className="h-[300px] w-full sm:h-[330px]"
                      />
                    </MapErrorBoundary>

                    {plotId
                      ? // Parcelle sélectionnée : sa géométrie est déjà chargée (voir
                        // `plotOptions`) — le seul cas restant à signaler est l'absence de
                        // localisation exploitable, jamais une position devinée à sa place.
                        !selectedPlot?.geometry && (
                          <div className="border-t border-line px-3 py-2 text-xs text-muted">
                            {selectedPlot
                              ? "Localisation indisponible pour cette parcelle."
                              : "Chargement de la parcelle…"}
                          </div>
                        )
                      : plots.length === 0 && (
                          <div className="border-t border-line px-3 py-2 text-xs text-muted">
                            Aucune parcelle géolocalisée pour le moment.
                          </div>
                        )}
                  </div>

                  {/* PLOTS LIST */}

                  <div className="flex flex-col">
                    <div className="mb-3 flex items-center justify-between">
                      <span className="text-xs font-semibold uppercase tracking-wide text-muted">
                        Parcelles
                      </span>

                      <span className="rounded-full bg-primary-light px-2 py-1 text-[10px] font-bold text-primary">
                        {plots.length}
                      </span>
                    </div>

                    <div className="flex max-h-[300px] flex-col gap-1.5 overflow-y-auto pr-1 sm:max-h-[330px]">
                      {plots.length > 0 ? (
                        plots.slice(0, 12).map((plot) => (
                          <div
                            key={plot.id}
                            className="flex items-center gap-2 rounded-xl bg-canvas px-3 py-2.5 transition hover:bg-primary-light"
                          >
                            <span className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg bg-primary-light text-primary">
                              <Square size={13} />
                            </span>

                            <span className="min-w-0 flex-1 truncate text-xs font-medium text-ink">
                              {plot.name}
                            </span>

                            {plot.geometry && (
                              <CheckCircle2
                                size={14}
                                className="flex-shrink-0 text-primary"
                              />
                            )}
                          </div>
                        ))
                      ) : (
                        <EmptyState message="Aucune parcelle disponible." />
                      )}

                      {plots.length > 12 && (
                        <Link
                          to="/map"
                          className="mt-1 flex items-center justify-center gap-1 rounded-xl border border-dashed border-line px-3 py-2 text-xs font-semibold text-primary hover:bg-primary-light"
                        >
                          Voir toutes les parcelles
                          <ArrowRight size={13} />
                        </Link>
                      )}
                    </div>
                  </div>
                </div>
              </SectionCard>

              {/* WEATHER */}

              <SectionCard
                title="Météo"
                icon={CloudSun}
                action={
                  <Link
                    to="/weather"
                    className="inline-flex items-center gap-1 text-xs font-semibold text-primary transition hover:gap-2 hover:underline"
                  >
                    Détails
                    <ArrowRight size={14} />
                  </Link>
                }
              >
                {weather === undefined ? (
                  <div className="flex flex-col gap-3">
                    <div className="h-14 w-28 animate-pulse rounded-xl bg-canvas" />
                    <div className="h-5 w-full animate-pulse rounded bg-canvas" />
                    <div className="h-5 w-4/5 animate-pulse rounded bg-canvas" />
                  </div>
                ) : weather?.forecast ? (
                  <div className="flex flex-col gap-5">
                    <div>
                      <p className="text-xs text-muted">Parcelle</p>

                      <p className="mt-1 truncate text-sm font-semibold text-ink">
                        {representativePlot?.name}
                      </p>
                    </div>

                    {/* CURRENT WEATHER */}

                    <div className="rounded-2xl bg-primary-light p-4">
                      <div className="flex items-center justify-between gap-3">
                        <div>
                          <p className="text-[10px] font-bold uppercase tracking-wider text-primary">
                            Conditions actuelles
                          </p>

                          <div className="mt-1 flex items-baseline gap-1">
                            <span className="text-4xl font-bold text-ink">
                              {weather.forecast.current.temperature ?? "—"}
                            </span>

                            <span className="text-lg font-medium text-muted">
                              °C
                            </span>
                          </div>
                        </div>

                        {(weather.forecast.current.precipitation ?? 0) > 0 ? (
                          <CloudRain size={46} className="text-primary" />
                        ) : (
                          <Sun size={46} className="text-accent" />
                        )}
                      </div>
                    </div>

                    {/* WEATHER METRICS */}

                    <div className="grid grid-cols-3 gap-2">
                      <div className="rounded-xl bg-canvas p-3 text-center">
                        <Droplets size={17} className="mx-auto text-primary" />

                        <p className="mt-1 text-sm font-bold text-ink">
                          {weather.forecast.current.humidity ?? "—"}%
                        </p>

                        <p className="text-[10px] text-muted">Humidité</p>
                      </div>

                      <div className="rounded-xl bg-canvas p-3 text-center">
                        <CloudRain size={17} className="mx-auto text-primary" />

                        <p className="mt-1 text-sm font-bold text-ink">
                          {weather.forecast.current.precipitation ?? "—"} mm
                        </p>

                        <p className="text-[10px] text-muted">Pluie</p>
                      </div>

                      <div className="rounded-xl bg-canvas p-3 text-center">
                        <Wind size={17} className="mx-auto text-primary" />

                        <p className="mt-1 text-sm font-bold text-ink">
                          {weather.forecast.current.wind_speed ?? "—"}
                        </p>

                        <p className="text-[10px] text-muted">km/h</p>
                      </div>
                    </div>

                    {/* FORECAST */}

                    {weather.forecast.daily.length > 1 && (
                      <div>
                        <p className="mb-2 text-xs font-semibold text-ink">
                          Prévisions
                        </p>

                        <div className="grid grid-cols-4 gap-1.5">
                          {weather.forecast.daily.slice(1, 5).map((day) => (
                            <div
                              key={day.date}
                              className="rounded-xl border border-line bg-canvas px-1.5 py-2 text-center"
                            >
                              <span className="block text-[9px] font-bold uppercase text-muted">
                                {new Date(day.date).toLocaleDateString(
                                  "fr-FR",
                                  {
                                    weekday: "short",
                                  },
                                )}
                              </span>

                              {(day.precipitation_sum ?? 0) > 0 ? (
                                <CloudRain
                                  size={15}
                                  className="mx-auto my-1 text-primary"
                                />
                              ) : (
                                <Sun
                                  size={15}
                                  className="mx-auto my-1 text-accent"
                                />
                              )}

                              <span className="block text-[10px] font-semibold text-ink">
                                {day.temperature_max ?? "—"}°
                                <span className="text-muted">
                                  {" "}
                                  / {day.temperature_min ?? "—"}°
                                </span>
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    <p className="text-[10px] leading-relaxed text-muted">
                      Source : Open-Meteo ·{" "}
                      {new Date(weather.forecast.fetched_at).toLocaleString(
                        "fr-FR",
                      )}
                    </p>
                  </div>
                ) : weather === null && representativePlot ? (
                  <Alert tone="warning">
                    Les données météorologiques sont temporairement
                    indisponibles.
                  </Alert>
                ) : (
                  <EmptyState message="Aucune parcelle géolocalisée disponible pour afficher la météo." />
                )}
              </SectionCard>
            </div>

            {/* ============================================================ */}
            {/* PRODUCTION ANALYTICS                                          */}
            {/* ============================================================ */}

            <div className="grid gap-4 xl:grid-cols-2">
              <SectionCard
                title={`Production par culture${
                  primaryUnit ? ` (${primaryUnit})` : ""
                }`}
                icon={TrendingUp}
                hint="Répartition de la production enregistrée par culture."
              >
                {productionChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height={270}>
                    <BarChart
                      data={productionChartData}
                      margin={{
                        top: 5,
                        right: 10,
                        left: -15,
                        bottom: 5,
                      }}
                    >
                      <CartesianGrid strokeDasharray="3 3" vertical={false} />

                      <XAxis
                        dataKey="name"
                        tick={{
                          fontSize: 11,
                        }}
                        axisLine={false}
                        tickLine={false}
                      />

                      <YAxis
                        tick={{
                          fontSize: 10,
                        }}
                        axisLine={false}
                        tickLine={false}
                      />

                      <Tooltip />

                      <Bar
                        dataKey="quantite"
                        name={`Quantité (${primaryUnit})`}
                        fill="#1f7a4d"
                        radius={[6, 6, 0, 0]}
                      />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <EmptyState message="Aucune donnée de production disponible pour les filtres sélectionnés." />
                )}
              </SectionCard>

              <SectionCard
                title={`Évolution de la production par campagne${
                  primaryUnit ? ` (${primaryUnit})` : ""
                }`}
                icon={CalendarRange}
                hint="Évolution des quantités récoltées au fil des campagnes."
              >
                {evolutionChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height={270}>
                    <LineChart
                      data={evolutionChartData}
                      margin={{
                        top: 5,
                        right: 10,
                        left: -15,
                        bottom: 5,
                      }}
                    >
                      <CartesianGrid strokeDasharray="3 3" vertical={false} />

                      <XAxis
                        dataKey="name"
                        tick={{
                          fontSize: 10,
                        }}
                        axisLine={false}
                        tickLine={false}
                      />

                      <YAxis
                        tick={{
                          fontSize: 10,
                        }}
                        axisLine={false}
                        tickLine={false}
                      />

                      <Tooltip />

                      <Legend />

                      <Line
                        type="monotone"
                        dataKey="quantite"
                        name={`Quantité (${primaryUnit})`}
                        stroke="#1f7a4d"
                        strokeWidth={3}
                        dot={{
                          r: 4,
                        }}
                        activeDot={{
                          r: 6,
                        }}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                ) : (
                  <EmptyState message="Pas encore assez de campagnes avec récoltes." />
                )}
              </SectionCard>
            </div>

            {/* ============================================================ */}
            {/* VENTES (Module M)                                             */}
            {/* ============================================================ */}

            <div className="grid gap-4 lg:grid-cols-2">
              <SectionCard
                title="Ventes par culture (MGA)"
                icon={Banknote}
                hint="Chiffre d'affaires réparti par culture vendue."
              >
                {salesByCropChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height={270}>
                    <BarChart
                      data={salesByCropChartData}
                      margin={{ top: 5, right: 10, left: -15, bottom: 5 }}
                    >
                      <CartesianGrid strokeDasharray="3 3" vertical={false} />
                      <XAxis
                        dataKey="name"
                        tick={{ fontSize: 11 }}
                        axisLine={false}
                        tickLine={false}
                      />
                      <YAxis
                        tick={{ fontSize: 10 }}
                        axisLine={false}
                        tickLine={false}
                      />
                      <Tooltip />
                      <Bar
                        dataKey="montant"
                        name="Montant (MGA)"
                        fill="#b8860b"
                        radius={[6, 6, 0, 0]}
                      />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <EmptyState message="Aucune vente rattachée à une culture pour les filtres sélectionnés." />
                )}
              </SectionCard>

              <SectionCard
                title="Évolution des ventes par campagne (MGA)"
                icon={CalendarRange}
                hint="Chiffre d'affaires au fil des campagnes agricoles."
              >
                {salesEvolutionChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height={270}>
                    <LineChart
                      data={salesEvolutionChartData}
                      margin={{ top: 5, right: 10, left: -15, bottom: 5 }}
                    >
                      <CartesianGrid strokeDasharray="3 3" vertical={false} />
                      <XAxis
                        dataKey="name"
                        tick={{ fontSize: 10 }}
                        axisLine={false}
                        tickLine={false}
                      />
                      <YAxis
                        tick={{ fontSize: 10 }}
                        axisLine={false}
                        tickLine={false}
                      />
                      <Tooltip />
                      <Legend />
                      <Line
                        type="monotone"
                        dataKey="montant"
                        name="Montant (MGA)"
                        stroke="#b8860b"
                        strokeWidth={3}
                        dot={{ r: 4 }}
                        activeDot={{ r: 6 }}
                      />
                    </LineChart>
                  </ResponsiveContainer>
                ) : (
                  <EmptyState message="Pas encore assez de campagnes avec ventes." />
                )}
              </SectionCard>
            </div>

            {/* ============================================================ */}
            {/* AI                                                               */}
            {/* ============================================================ */}

            <div className="grid gap-4 xl:grid-cols-2">
              {/* AI PREDICTION */}

              <SectionCard
                title="Prévision IA"
                icon={BrainCircuit}
                action={
                  <Link
                    to="/predictions"
                    className="text-xs font-semibold text-primary hover:underline"
                  >
                    Toutes les prévisions
                  </Link>
                }
              >
                {lastPrediction === undefined ? (
                  <div className="h-28 animate-pulse rounded-xl bg-canvas" />
                ) : lastPrediction ? (
                  <div className="flex flex-col gap-4">
                    <div className="rounded-2xl bg-primary-light p-4">
                      <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                          <p className="text-[10px] font-bold uppercase tracking-wider text-primary">
                            Rendement prédit
                          </p>
                          <h3>
                            Résultat de la prédiction —{" "}
                            {lastPrediction.crop?.name ??
                              "Culture non renseignée (prédiction antérieure)"}
                          </h3>

                          <p className="mt-1 text-3xl font-bold text-primary-dark">
                            {lastPrediction.predicted_yield.toLocaleString(
                              "fr-FR",
                            )}{" "}
                            {lastPrediction.unit}
                          </p>
                        </div>

                        <BrainCircuit size={34} className="text-primary" />
                      </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                      <div className="rounded-xl border border-line bg-canvas p-3">
                        <p className="text-[10px] uppercase tracking-wide text-muted">
                          Parcelle
                        </p>

                        <p className="mt-1 truncate text-xs font-semibold text-ink">
                          {lastPrediction.plot?.name ??
                            `Parcelle #${lastPrediction.plot_id}`}
                        </p>
                      </div>

                      <div className="rounded-xl border border-line bg-canvas p-3">
                        <p className="text-[10px] uppercase tracking-wide text-muted">
                          Modèle
                        </p>

                        <p className="mt-1 truncate text-xs font-semibold text-ink">
                          {lastPrediction.model?.algorithm ?? "—"}
                        </p>
                      </div>
                    </div>

                    <p className="text-xs text-muted">
                      Demandée le{" "}
                      {new Date(lastPrediction.predicted_at).toLocaleString(
                        "fr-FR",
                      )}
                    </p>

                    {lastPrediction.is_experimental && (
                      <span className="text-xs text-muted-foreground">
                        Modèle expérimental
                      </span>
                    )}

                    <Link
                      to={buildDetailUrl("/predictions", {
                        cooperative_id: cooperativeId,
                        plot_id: plotId,
                        campaign_id: campaignId,
                      })}
                      className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:gap-2"
                    >
                      Voir les prévisions IA
                      <ArrowRight size={15} />
                    </Link>
                  </div>
                ) : (
                  <EmptyState
                    message={
                      plotId
                        ? `Aucune prédiction IA n'a encore été réalisée pour ${
                            selectedPlot?.name ?? "cette parcelle"
                          }.`
                        : "Prévision IA indisponible : aucune prédiction n'a encore été demandée."
                    }
                    action={
                      <Link to="/predictions">
                        <Button variant="secondary">
                          Créer une prédiction
                        </Button>
                      </Link>
                    }
                  />
                )}
              </SectionCard>

              {/* AI EXPLANATION */}

              <SectionCard title="Intelligence artificielle" icon={Sprout}>
                <div className="flex h-full flex-col justify-between gap-5">
                  <div className="rounded-2xl bg-canvas p-5">
                    <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-primary-light text-primary">
                      <BrainCircuit size={22} />
                    </div>

                    <h3 className="text-base font-bold text-ink">
                      Analyse intelligente des données agricoles
                    </h3>

                    <p className="mt-2 text-sm leading-6 text-muted">
                      Le modèle d'apprentissage automatique analyse les données
                      agricoles disponibles afin d'estimer le rendement
                      potentiel des cultures.
                    </p>

                    <p className="mt-3 text-xs leading-5 text-muted">
                      Une prédiction reste une estimation et doit être
                      interprétée avec les autres informations disponibles sur
                      la parcelle.
                    </p>
                  </div>

                  <Link
                    to="/predictions/about"
                    className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:gap-2"
                  >
                    Découvrir le fonctionnement de l'IA
                    <ArrowRight size={15} />
                  </Link>
                </div>
              </SectionCard>
            </div>

            {/* ============================================================ */}
            {/* ACTIVITY + ALERTS + SALES                                     */}
            {/* ============================================================ */}

            <div className="grid gap-4 xl:grid-cols-3">
              <SectionCard
                title="Activité récente"
                hint="Dernières opérations enregistrées dans le système."
              >
                {/* Scroll interne propre à cette carte (§ Dashboard scroll) : toutes les
                    activités restent disponibles, seule la hauteur affichée est bornée. */}
                <div
                  role="region"
                  aria-label="Activité récente"
                  className={`dashboard-scroll ${scrollSectionHeight} overflow-y-auto`}
                >
                  {summary.recent_activity.length > 0 ? (
                    <ul className="flex flex-col divide-y divide-line">
                      {summary.recent_activity.map((entry, index) => {
                        const Icon = ACTIVITY_ICONS[entry.type];

                        return (
                          <li
                            key={index}
                            className="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                          >
                            <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary-light text-primary">
                              <Icon size={14} />
                            </span>

                            <div className="min-w-0 flex-1">
                              <p className="text-sm leading-5 text-ink">
                                {entry.description}
                              </p>

                              <p className="mt-1 text-[10px] text-muted">
                                {new Date(entry.date).toLocaleString("fr-FR")}
                              </p>
                            </div>

                            <span className="mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                          </li>
                        );
                      })}
                    </ul>
                  ) : (
                    <EmptyState message="Aucune activité récente." />
                  )}
                </div>
              </SectionCard>

              <SectionCard
                title="Alertes & recommandations"
                icon={AlertTriangle}
                hint="Points nécessitant votre attention."
              >
                <div
                  role="region"
                  aria-label="Alertes & recommandations"
                  className={`dashboard-scroll ${scrollSectionHeight} overflow-y-auto`}
                >
                  {summary.alerts.length > 0 ? (
                    <div className="flex flex-col gap-2">
                      {summary.alerts.map((alert, index) => (
                        <div
                          key={index}
                          className="flex items-start gap-3 rounded-xl border border-warning/20 bg-warning-bg p-3"
                        >
                          <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-surface text-warning">
                            <AlertTriangle size={16} />
                          </div>

                          <p className="pt-1 text-sm leading-5 text-warning">
                            {alert.message}
                          </p>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <EmptyState message="Vous n'avez aucune alerte actuellement." />
                  )}
                </div>
              </SectionCard>

              <SectionCard
                title="Dernières ventes"
                icon={Banknote}
                hint="Ventes agricoles les plus récentes."
                action={
                  <Link
                    to="/sales"
                    className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:gap-1.5"
                  >
                    Voir tout
                    <ArrowRight size={13} />
                  </Link>
                }
              >
                <div
                  role="region"
                  aria-label="Dernières ventes"
                  className={`dashboard-scroll ${scrollSectionHeight} overflow-y-auto`}
                >
                  {summary.recent_sales.length > 0 ? (
                    <ul className="flex flex-col divide-y divide-line">
                      {summary.recent_sales.map((sale) => (
                        <li
                          key={sale.id}
                          className="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                          <div className="min-w-0">
                            <p className="truncate text-sm leading-5 text-ink">
                              {sale.crop_name ?? "Culture non renseignée"}
                              {sale.buyer_name ? ` — ${sale.buyer_name}` : ""}
                            </p>
                            <p className="mt-1 text-[10px] text-muted">
                              {new Date(sale.sale_date).toLocaleDateString(
                                "fr-FR",
                              )}
                            </p>
                          </div>
                          <span className="flex-shrink-0 text-sm font-semibold text-ink">
                            {sale.total_amount.toLocaleString("fr-FR")}{" "}
                            {sale.currency}
                          </span>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <EmptyState message="Aucune vente enregistrée." />
                  )}
                </div>
              </SectionCard>
            </div>

            {/* ============================================================ */}
            {/* INPUTS + YIELD                                                 */}
            {/* ============================================================ */}

            <div className="grid gap-4 xl:grid-cols-2">
              <SectionCard
                title="Consommation d'intrants"
                icon={Boxes}
                hint="Coût des intrants utilisés, en Ariary."
              >
                {inputChartData.length > 0 ? (
                  <ResponsiveContainer width="100%" height={290}>
                    <BarChart
                      data={inputChartData}
                      layout="vertical"
                      margin={{
                        top: 5,
                        right: 10,
                        left: 5,
                        bottom: 5,
                      }}
                    >
                      <CartesianGrid strokeDasharray="3 3" horizontal={false} />

                      <XAxis
                        type="number"
                        tick={{
                          fontSize: 10,
                        }}
                        axisLine={false}
                        tickLine={false}
                      />

                      <YAxis
                        type="category"
                        dataKey="name"
                        width={115}
                        tick={{
                          fontSize: 10,
                        }}
                        axisLine={false}
                        tickLine={false}
                      />

                      <Tooltip
                        formatter={(value) =>
                          Number(value).toLocaleString("fr-FR")
                        }
                      />

                      <Bar
                        dataKey="cout"
                        name="Coût (Ar)"
                        fill="#b8860b"
                        radius={[0, 6, 6, 0]}
                      />
                    </BarChart>
                  </ResponsiveContainer>
                ) : (
                  <EmptyState message="Aucune utilisation d'intrant enregistrée." />
                )}
              </SectionCard>

              <SectionCard
                title="Rendement par culture"
                hint="Rendement moyen de chaque culture disponible."
              >
                <div className="overflow-x-auto">
                  <table className="w-full border-collapse">
                    <thead>
                      <tr className="border-b border-line">
                        <th className={th}>Culture</th>
                        <th className={th}>Unité</th>
                        <th className={th}>Rendement moyen</th>
                      </tr>
                    </thead>

                    <tbody>
                      {summary.yield_by_crop.length === 0 && (
                        <tr>
                          <td colSpan={3} className={`${td} text-muted`}>
                            Pas de rendement disponible.
                          </td>
                        </tr>
                      )}

                      {summary.yield_by_crop.map((row) => (
                        <tr
                          key={`${row.crop_id}-${row.unit}`}
                          className="border-b border-line last:border-0"
                        >
                          <td className={`${td} font-medium`}>
                            {row.crop_name}
                          </td>

                          <td className={td}>
                            <span className="rounded-full bg-canvas px-2 py-1 text-xs font-medium">
                              {row.unit}
                            </span>
                          </td>

                          <td className={`${td} font-semibold`}>
                            {row.average_yield.toLocaleString("fr-FR")} /ha
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </SectionCard>
            </div>

            {/* ============================================================ */}
            {/* HARVESTS                                                       */}
            {/* ============================================================ */}

            <SectionCard
              title="Récoltes par unité"
              icon={Wheat}
              hint="Les quantités sont regroupées par unité de mesure ; des unités différentes (kg, sac, tonne…) ne sont jamais additionnées entre elles."
            >
              {/* overflow-auto (et non overflow-x-auto seul) : le tableau reste scrollable
                  verticalement si de nombreuses unités existent, tout en gardant le scroll
                  horizontal indépendant déjà en place pour les petits écrans. En-tête collant
                  (sticky) pour garder les colonnes lisibles pendant le défilement. */}
              <div
                role="region"
                aria-label="Récoltes par unité"
                className={`dashboard-scroll ${scrollSectionHeight} overflow-auto`}
              >
                <table className="w-full border-collapse">
                  <thead className="sticky top-0 z-10 bg-surface">
                    <tr className="border-b border-line">
                      <th className={th}>Unité</th>
                      <th className={th}>Quantité totale</th>
                      <th className={th}>Nb. récoltes</th>
                      <th className={th}>Rendement moyen</th>
                    </tr>
                  </thead>

                  <tbody>
                    {summary.harvest_totals_by_unit.length === 0 && (
                      <tr>
                        <td colSpan={4} className={`${td} text-muted`}>
                          Aucune récolte enregistrée.
                        </td>
                      </tr>
                    )}

                    {summary.harvest_totals_by_unit.map((row) => {
                      const avg = summary.average_yield_by_unit.find(
                        (item) => item.unit === row.unit,
                      );

                      return (
                        <tr
                          key={row.unit}
                          className="border-b border-line last:border-0"
                        >
                          <td className={`${td} font-semibold`}>
                            <span className="rounded-full bg-primary-light px-2.5 py-1 text-xs font-bold text-primary">
                              {row.unit}
                            </span>
                          </td>

                          <td className={td}>
                            {row.total_quantity.toLocaleString("fr-FR")}
                          </td>

                          <td className={td}>{row.harvests_count}</td>

                          <td className={td}>
                            {avg
                              ? `${avg.average_yield.toLocaleString(
                                  "fr-FR",
                                )} ${row.unit}/ha`
                              : "—"}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </SectionCard>

            {/* ============================================================ */}
            {/* GEOGRAPHIC STATISTICS                                         */}
            {/* ============================================================ */}

            <SectionCard
              title="Statistiques géographiques"
              icon={MapIcon}
              hint="Répartition des parcelles et des superficies par région."
            >
              {/* Toutes les régions restent affichées (aucune limitation à 5/10) : c'est le
                  scroll interne qui absorbe la hauteur, jamais une troncature des données. */}
              <div
                role="region"
                aria-label="Statistiques géographiques"
                className={`dashboard-scroll ${scrollSectionHeight} overflow-auto`}
              >
                <table className="w-full border-collapse">
                  <thead className="sticky top-0 z-10 bg-surface">
                    <tr className="border-b border-line">
                      <th className={th}>Région</th>
                      <th className={th}>Parcelles</th>
                      <th className={th}>Superficie</th>
                    </tr>
                  </thead>

                  <tbody>
                    {summary.geography.length === 0 && (
                      <tr>
                        <td colSpan={3} className={`${td} text-muted`}>
                          Aucune parcelle géolocalisée.
                        </td>
                      </tr>
                    )}

                    {summary.geography.map((row) => (
                      <tr
                        key={row.region}
                        className="border-b border-line last:border-0"
                      >
                        <td className={`${td} font-medium`}>{row.region}</td>

                        <td className={td}>
                          <span className="rounded-full bg-canvas px-2.5 py-1 text-xs font-semibold">
                            {row.plots_count}
                          </span>
                        </td>

                        <td className={`${td} font-semibold`}>
                          {row.total_area_ha.toFixed(2)} ha
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </SectionCard>
          </>
        )}
      </div>
    </div>
  );
}

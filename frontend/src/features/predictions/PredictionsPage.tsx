import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type FormEvent,
} from "react";
import { Link, useSearchParams } from "react-router-dom";
import { useAuth } from "../../hooks/useAuth";
import { useOptions } from "../../hooks/useOptions";
import { useResourceList } from "../../hooks/useResourceList";
import { plotsApi } from "../plots/api";
import { campaignsApi } from "../campaigns/api";
import { cropsApi, getCropModelStatus } from "../crops/api";
import { getSuggestedInputs, predictionsApi } from "./api";
import { DataTable, type Column } from "../../components/DataTable";
import { Pagination } from "../../components/Pagination";
import { Button } from "../../components/Button";
import { Alert } from "../../components/Alert";
import { PageHeader } from "../../components/PageHeader";
import { FormField } from "../../components/FormField";
import { Badge } from "../../components/Badge";
import { toApiError, type NormalizedError } from "../../services/http";
import type {
  CropModelStatus,
  PredictionFeatures,
  ProductionPrediction,
  VarietyClass,
} from "../../types/api";
import "./PredictionsPage.css";

/** Cahier des charges §16 : le statut vient de l'API, jamais d'une liste codée en dur dans React. */
const MODEL_STATUS_LABELS: Record<CropModelStatus["status"], string> = {
  ready: "● Modèle disponible",
  training_required: "○ Modèle à entraîner",
  inactive: "○ Modèle désactivé",
  unavailable: "○ Statut indisponible",
};

const EMPTY_FEATURES: PredictionFeatures = {
  area_ha: 1,
  fertilizer_kg_per_ha: 0,
  rainfall_mm: 1200,
  planting_month: new Date().getMonth() + 1,
  variety_class: "traditionnelle",
};

const FEATURE_LABELS: Record<string, string> = {
  area_ha: "Superficie (ha)",
  fertilizer_kg_per_ha: "Engrais (kg/ha)",
  rainfall_mm: "Pluviométrie (mm)",
  planting_month: "Mois de plantation",
  variety_class_amelioree: "Variété améliorée",
  variety_class_traditionnelle: "Variété traditionnelle",
};

function FeatureImportanceBars({
  importances,
}: {
  importances: Record<string, number>;
}) {
  const entries = Object.entries(importances).sort((a, b) => b[1] - a[1]);
  const max = Math.max(...entries.map(([, v]) => v), 0.0001);

  return (
    <div className="stack">
      {entries.map(([key, value]) => (
        <div key={key} className="importance-row">
          <span className="importance-label">{FEATURE_LABELS[key] ?? key}</span>
          <div className="importance-track">
            <div
              className="importance-fill"
              style={{ width: `${(value / max) * 100}%` }}
            />
          </div>
          <span className="importance-value">{(value * 100).toFixed(1)}%</span>
        </div>
      ))}
    </div>
  );
}

function PredictionResultCard({
  prediction,
}: {
  prediction: ProductionPrediction;
}) {
  return (
    <div className="card stack prediction-result">
      <div className="row-between">
        <div>
          <h3>
            Résultat de la prédiction —{" "}
            {prediction.crop?.name ??
              "Culture non renseignée (prédiction antérieure)"}
          </h3>
          <p className="dashboard-hint">
            Modèle {prediction.model?.name ?? "—"} —{" "}
            {prediction.model?.algorithm} v{prediction.model?.version}
          </p>
        </div>
        {prediction.is_experimental && (
          <Badge label="Expérimental (données synthétiques)" tone="warning" />
        )}
      </div>

      <div className="prediction-headline">
        <span className="prediction-value">
          {prediction.predicted_yield.toLocaleString("fr-FR")}
        </span>
        <span className="prediction-unit">{prediction.unit}</span>
      </div>

      <div className="form-grid">
        <div>
          <h3>Métriques du modèle (jeu de test)</h3>
          <table className="dashboard-table">
            <tbody>
              <tr>
                <td>MAE</td>
                <td>{prediction.model?.metrics.mae}</td>
              </tr>
              <tr>
                <td>RMSE</td>
                <td>{prediction.model?.metrics.rmse}</td>
              </tr>
              <tr>
                <td title="Part de la variabilité du rendement expliquée par le modèle sur le jeu de test (1 = parfait, 0 = aucun pouvoir prédictif) — pas un niveau de confiance.">
                  R² ⓘ
                </td>
                <td>{prediction.model?.metrics.r2}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div>
          <h3>Date de la prédiction</h3>
          <p>{new Date(prediction.predicted_at).toLocaleString("fr-FR")}</p>
          <h3>Niveau de confiance</h3>
          <p className="dashboard-hint">
            Non affiché : son calcul rigoureux (intervalle de prédiction) n'est
            pas encore implémenté — un chiffre non justifié statistiquement ne
            sera jamais affiché ici.
          </p>
        </div>
      </div>

      {prediction.feature_importances && (
        <div>
          <h3>Variables importantes</h3>
          <FeatureImportanceBars importances={prediction.feature_importances} />
        </div>
      )}
    </div>
  );
}

export function PredictionsPage() {
  const { user } = useAuth();
  const [searchParams] = useSearchParams();

  const cooperativeIdFromUrl = searchParams.get("cooperative_id") ?? undefined;

  const plotIdFromUrl = searchParams.get("plot_id") ?? undefined;

  const campaignIdFromUrl = searchParams.get("campaign_id") ?? undefined;
  const canPredict =
    user?.role === "admin" || user?.role === "cooperative_manager";

  const { options: plots } = useOptions(
    () => plotsApi.list({ per_page: 200 }),
    [],
  );
  const { options: campaigns } = useOptions(
    () => campaignsApi.list({ per_page: 200 }),
    [],
  );
  const { options: crops } = useOptions(
    () => cropsApi.list({ per_page: 200 }),
    [],
  );

  // §13/§16 : statut du modèle par culture, obtenu depuis l'API (jamais deviné/codé en dur).
  const [modelStatus, setModelStatus] = useState<CropModelStatus[]>([]);
  useEffect(() => {
    let cancelled = false;
    getCropModelStatus()
      .then((data) => {
        if (!cancelled) setModelStatus(data);
      })
      .catch(() => {
        // Statut non-bloquant : une indisponibilité du service IA ne doit pas empêcher de
        // sélectionner une culture — seule la soumission finale peut échouer (§19/§24).
      });
    return () => {
      cancelled = true;
    };
  }, []);
  const statusByCropId = useMemo(
    () => new Map(modelStatus.map((s) => [s.id, s])),
    [modelStatus],
  );

  const [plotId, setPlotId] = useState("");
  const [campaignId, setCampaignId] = useState("");
  const [cropId, setCropId] = useState("");
  const [cropSource, setCropSource] = useState<string | null>(null);
  const [features, setFeatures] = useState<PredictionFeatures>(EMPTY_FEATURES);
  const [sources, setSources] = useState<Record<string, string | null>>({});
  const [suggesting, setSuggesting] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);
  const [result, setResult] = useState<ProductionPrediction | null>(null);

  const list = useCallback(
    (params: Record<string, unknown>) =>
      predictionsApi.list({
        ...params,
        cooperative_id: cooperativeIdFromUrl,
        plot_id: plotIdFromUrl,
        campaign_id: campaignIdFromUrl,
      }),
    [cooperativeIdFromUrl, plotIdFromUrl, campaignIdFromUrl],
  );

  const history = useResourceList<ProductionPrediction>(list, {
    page: 1,
    per_page: 10,
  });

  async function handleSuggest() {
    if (!plotId) return;
    setSuggesting(true);
    setError(null);
    try {
      const suggestion = await getSuggestedInputs(
        Number(plotId),
        campaignId ? Number(campaignId) : undefined,
      );
      setFeatures((prev) => ({
        area_ha: suggestion.area_ha ?? prev.area_ha,
        fertilizer_kg_per_ha:
          suggestion.fertilizer_kg_per_ha ?? prev.fertilizer_kg_per_ha,
        rainfall_mm: suggestion.rainfall_mm ?? prev.rainfall_mm,
        planting_month: suggestion.planting_month ?? prev.planting_month,
        variety_class: suggestion.variety_class ?? prev.variety_class,
      }));
      setSources(suggestion.sources);
      // §22 : auto-détection de la culture depuis le cycle cultural actif de la parcelle —
      // ne force jamais un choix, l'utilisateur peut toujours changer la culture proposée.
      if (suggestion.crop_id) {
        setCropId(String(suggestion.crop_id));
        setCropSource(
          "Détectée depuis le cycle cultural actif de cette parcelle.",
        );
      } else {
        setCropSource(null);
      }
    } catch (err) {
      setError(toApiError(err));
    } finally {
      setSuggesting(false);
    }
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    // §7/§40 : aucune culture sélectionnée => aucune prédiction possible, jamais un repli sur
    // le riz. Le bouton est déjà désactivé dans ce cas (voir plus bas), ceinture et bretelles.
    if (!plotId || !cropId) return;
    setSubmitting(true);
    setError(null);
    setResult(null);
    try {
      const prediction = await predictionsApi.create({
        plot_id: Number(plotId),
        crop_id: Number(cropId),
        campaign_id: campaignId ? Number(campaignId) : null,
        features,
      });
      setResult(prediction);
      history.refetch();
    } catch (err) {
      setError(toApiError(err));
    } finally {
      setSubmitting(false);
    }
  }

  const columns: Column<ProductionPrediction>[] = [
    {
      key: "predicted_at",
      header: "Date",
      render: (p) => new Date(p.predicted_at).toLocaleString("fr-FR"),
    },
    {
      key: "plot",
      header: "Parcelle",
      render: (p) => p.plot?.name ?? p.plot_id,
    },
    { key: "crop", header: "Culture", render: (p) => p.crop?.name ?? "—" },
    {
      key: "predicted_yield",
      header: "Rendement prédit",
      render: (p) => `${p.predicted_yield.toLocaleString("fr-FR")} ${p.unit}`,
      align: "right",
    },
    {
      key: "model",
      header: "Modèle",
      render: (p) =>
        p.model ? `${p.model.algorithm} (R²=${p.model.metrics.r2})` : "—",
    },
    {
      key: "experimental",
      header: "",
      render: (p) =>
        p.is_experimental ? (
          <Badge label="Expérimental" tone="warning" />
        ) : null,
    },
  ];

  const fieldErrors = error?.errors ?? {};

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        title="Prévisions de production"
        description={
          <>
            Modèle entraîné sur des données <strong>synthétiques</strong> — à
            interpréter comme expérimental, pas comme une prévision agronomique
            réelle.{" "}
            <Link
              to="/predictions/about"
              className="font-medium text-primary hover:underline"
            >
              En savoir plus sur ce modèle →
            </Link>
          </>
        }
      />

      {canPredict && (
        <form
          className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm"
          onSubmit={handleSubmit}
        >
          <h2 className="text-base font-semibold text-ink">Nouvelle demande</h2>
          {error && !Object.keys(fieldErrors).length && (
            <Alert>{error.message}</Alert>
          )}

          <div className="form-grid">
            <FormField
              label="Parcelle"
              htmlFor="plot_id"
              required
              errors={fieldErrors.plot_id}
            >
              <select
                id="plot_id"
                value={plotId}
                onChange={(e) => setPlotId(e.target.value)}
                required
              >
                <option value="" disabled>
                  Sélectionner…
                </option>
                {plots.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField
              label="Campagne"
              htmlFor="campaign_id"
              hint="Optionnel, affine la suggestion."
            >
              <select
                id="campaign_id"
                value={campaignId}
                onChange={(e) => setCampaignId(e.target.value)}
              >
                <option value="">Aucune</option>
                {campaigns.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} ({c.year})
                  </option>
                ))}
              </select>
            </FormField>
            <FormField
              label="Culture"
              htmlFor="crop_id"
              required
              hint={
                cropSource ??
                "Détermine le modèle utilisé — chaque culture dispose de son propre modèle entraîné. Aucune culture sélectionnée = aucune prédiction possible."
              }
            >
              <select
                id="crop_id"
                value={cropId}
                required
                onChange={(e) => {
                  setCropId(e.target.value);
                  setCropSource(null);
                }}
              >
                <option value="" disabled>
                  Sélectionner une culture…
                </option>
                {crops.map((c) => {
                  const status = statusByCropId.get(c.id);
                  return (
                    <option key={c.id} value={c.id}>
                      {c.name}
                      {status ? ` — ${MODEL_STATUS_LABELS[status.status]}` : ""}
                    </option>
                  );
                })}
              </select>
            </FormField>
          </div>

          {cropId &&
            statusByCropId.get(Number(cropId)) &&
            !statusByCropId.get(Number(cropId))!.available && (
              <Alert tone="warning">
                <strong>Modèle non disponible.</strong> Cette culture est
                configurée dans la plateforme, mais son modèle de rendement
                n'est pas encore entraîné ou actif (statut :{" "}
                {
                  MODEL_STATUS_LABELS[
                    statusByCropId.get(Number(cropId))!.status
                  ]
                }
                ) — aucune prédiction ne sera inventée pour la remplacer.
              </Alert>
            )}

          <div className="row">
            <Button
              type="button"
              variant="secondary"
              onClick={handleSuggest}
              loading={suggesting}
              disabled={!plotId}
            >
              Suggérer les valeurs depuis les données réelles
            </Button>
          </div>

          <div className="form-grid">
            <FormField
              label="Superficie (ha)"
              htmlFor="area_ha"
              required
              hint={sources.area_ha ?? undefined}
              errors={fieldErrors["features.area_ha"]}
            >
              <input
                id="area_ha"
                type="number"
                min={0.01}
                step="any"
                value={features.area_ha}
                onChange={(e) =>
                  setFeatures({ ...features, area_ha: Number(e.target.value) })
                }
                required
              />
            </FormField>
            <FormField
              label="Engrais (kg/ha)"
              htmlFor="fertilizer_kg_per_ha"
              required
              hint={sources.fertilizer_kg_per_ha ?? undefined}
              errors={fieldErrors["features.fertilizer_kg_per_ha"]}
            >
              <input
                id="fertilizer_kg_per_ha"
                type="number"
                min={0}
                step="any"
                value={features.fertilizer_kg_per_ha}
                onChange={(e) =>
                  setFeatures({
                    ...features,
                    fertilizer_kg_per_ha: Number(e.target.value),
                  })
                }
                required
              />
            </FormField>
            <FormField
              label="Pluviométrie cumulée (mm)"
              htmlFor="rainfall_mm"
              required
              hint={
                sources.rainfall_mm ??
                "Cliquer sur « Suggérer les valeurs » pour tenter de la déduire des observations météo réelles (module Météo)."
              }
              errors={fieldErrors["features.rainfall_mm"]}
            >
              <input
                id="rainfall_mm"
                type="number"
                min={0}
                step="any"
                value={features.rainfall_mm}
                onChange={(e) =>
                  setFeatures({
                    ...features,
                    rainfall_mm: Number(e.target.value),
                  })
                }
                required
              />
            </FormField>
            <FormField
              label="Mois de plantation"
              htmlFor="planting_month"
              required
              hint={sources.planting_month ?? undefined}
              errors={fieldErrors["features.planting_month"]}
            >
              <input
                id="planting_month"
                type="number"
                min={1}
                max={12}
                value={features.planting_month}
                onChange={(e) =>
                  setFeatures({
                    ...features,
                    planting_month: Number(e.target.value),
                  })
                }
                required
              />
            </FormField>
            <FormField
              label="Variété"
              htmlFor="variety_class"
              required
              hint={
                sources.variety_class ??
                "Non déductible du catalogue actuel — à choisir manuellement."
              }
              errors={fieldErrors["features.variety_class"]}
            >
              <select
                id="variety_class"
                value={features.variety_class}
                onChange={(e) =>
                  setFeatures({
                    ...features,
                    variety_class: e.target.value as VarietyClass,
                  })
                }
              >
                <option value="traditionnelle">Traditionnelle</option>
                <option value="amelioree">Améliorée</option>
              </select>
            </FormField>
          </div>

          <div className="form-actions">
            <Button
              type="submit"
              loading={submitting}
              disabled={!plotId || !cropId}
            >
              Demander une prédiction
            </Button>
          </div>
        </form>
      )}

      {result && <PredictionResultCard prediction={result} />}

      <div className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm">
        <h2 className="text-base font-semibold text-ink">
          Historique des prédictions
        </h2>
        <DataTable
          columns={columns}
          rows={history.data}
          rowKey={(p) => p.id}
          loading={history.loading}
          emptyMessage="Aucune prédiction demandée."
        />
        <Pagination
          meta={history.meta}
          onPageChange={(page) => history.setParam("page", page)}
        />
      </div>
    </div>
  );
}

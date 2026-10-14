import { useEffect, useState, type FormEvent } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft } from "lucide-react";
import { cooperativesApi, type CooperativePayload } from "./api";
import { FormField } from "../../components/FormField";
import { Button } from "../../components/Button";
import { Alert } from "../../components/Alert";
import { Spinner } from "../../components/Spinner";
import { PageHeader } from "../../components/PageHeader";
import { toApiError, type NormalizedError } from "../../services/http";

const emptyForm: CooperativePayload = {
  name: "",
  region: "",
  district: "",
  commune: "",
  status: "active",
};

export function CooperativeFormPage() {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();

  const [form, setForm] = useState<CooperativePayload>(emptyForm);
  const [latitude, setLatitude] = useState("");
  const [longitude, setLongitude] = useState("");
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<NormalizedError | null>(null);

  useEffect(() => {
    if (!id) return;
    cooperativesApi
      .get(Number(id))
      .then((coop) => {
        setForm({
          name: coop.name,
          region: coop.region,
          district: coop.district ?? "",
          commune: coop.commune ?? "",
          manager_user_id: coop.manager_user_id,
          status: coop.status,
        });
        if (coop.geometry) {
          setLongitude(String(coop.geometry.coordinates[0]));
          setLatitude(String(coop.geometry.coordinates[1]));
        }
      })
      .catch((err) => setError(toApiError(err)))
      .finally(() => setLoading(false));
  }, [id]);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);

    const payload: CooperativePayload = {
      ...form,
      district: form.district || null,
      commune: form.commune || null,
      geometry:
        latitude && longitude
          ? {
              type: "Point",
              coordinates: [Number(longitude), Number(latitude)],
            }
          : null,
    };

    try {
      if (isEdit) {
        await cooperativesApi.update(Number(id), payload);
      } else {
        await cooperativesApi.create(payload);
      }

      navigate("/cooperatives");
    } catch (err) {
      setError(toApiError(err));
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <Spinner />;

  const fieldErrors = error?.errors ?? {};

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link
          to="/cooperatives"
          className="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-muted transition-colors hover:text-ink"
        >
          <ArrowLeft size={16} aria-hidden />
          Retour
        </Link>
        <PageHeader
          title={isEdit ? "Modifier la coopérative" : "Nouvelle coopérative"}
        />
      </div>

      <form
        className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm"
        onSubmit={handleSubmit}
      >
        {error && !Object.keys(fieldErrors).length && (
          <Alert>{error.message}</Alert>
        )}

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <FormField
            label="Nom"
            htmlFor="name"
            required
            errors={fieldErrors.name}
          >
            <input
              id="name"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              required
            />
          </FormField>
          <FormField
            label="Région"
            htmlFor="region"
            required
            errors={fieldErrors.region}
          >
            <input
              id="region"
              value={form.region}
              onChange={(e) => setForm({ ...form, region: e.target.value })}
              required
            />
          </FormField>
          <FormField
            label="District"
            htmlFor="district"
            errors={fieldErrors.district}
          >
            <input
              id="district"
              value={form.district ?? ""}
              onChange={(e) => setForm({ ...form, district: e.target.value })}
            />
          </FormField>
          <FormField
            label="Commune"
            htmlFor="commune"
            errors={fieldErrors.commune}
          >
            <input
              id="commune"
              value={form.commune ?? ""}
              onChange={(e) => setForm({ ...form, commune: e.target.value })}
            />
          </FormField>
          <FormField
            label="Latitude (siège)"
            htmlFor="lat"
            hint="SRID 4326 — laisser vide si inconnu."
            errors={fieldErrors["geometry.coordinates"]}
          >
            <input
              id="lat"
              type="number"
              step="any"
              value={latitude}
              onChange={(e) => setLatitude(e.target.value)}
            />
          </FormField>
          <FormField label="Longitude (siège)" htmlFor="lng">
            <input
              id="lng"
              type="number"
              step="any"
              value={longitude}
              onChange={(e) => setLongitude(e.target.value)}
            />
          </FormField>
          <FormField
            label="Statut"
            htmlFor="status"
            errors={fieldErrors.status}
          >
            <select
              id="status"
              value={form.status}
              onChange={(e) =>
                setForm({
                  ...form,
                  status: e.target.value as "active" | "inactive",
                })
              }
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </FormField>
          <FormField
            label="ID utilisateur responsable"
            htmlFor="manager_user_id"
            hint="Optionnel. La gestion complète des utilisateurs est prévue en phase ultérieure."
            errors={fieldErrors.manager_user_id}
          >
            <input
              id="manager_user_id"
              type="number"
              value={form.manager_user_id ?? ""}
              onChange={(e) =>
                setForm({
                  ...form,
                  manager_user_id: e.target.value
                    ? Number(e.target.value)
                    : null,
                })
              }
            />
          </FormField>
        </div>

        <div className="mt-2 flex justify-end gap-3">
          <Button
            type="button"
            variant="secondary"
            onClick={() => navigate(-1)}
          >
            Annuler
          </Button>
          <Button type="submit" loading={saving}>
            Enregistrer
          </Button>
        </div>
      </form>
    </div>
  );
}

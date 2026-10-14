import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { salesApi, type SalePayload } from './api'
import { cooperativesApi } from '../cooperatives/api'
import { farmersApi } from '../farmers/api'
import { plotsApi } from '../plots/api'
import { cropsApi, cropVarietiesApi } from '../crops/api'
import { campaignsApi } from '../campaigns/api'
import { cropCyclesApi } from '../cropCycles/api'
import { useAuth } from '../../hooks/useAuth'
import { useOptions } from '../../hooks/useOptions'
import { FormField } from '../../components/FormField'
import { Button } from '../../components/Button'
import { Alert } from '../../components/Alert'
import { Spinner } from '../../components/Spinner'
import { toApiError, type NormalizedError } from '../../services/http'
import type { CropCycle, Farmer, Plot } from '../../types/api'

const emptyForm: SalePayload = {
  sale_date: '',
  quantity: 0,
  unit: 'kg',
  unit_price: 0,
  currency: 'MGA',
  buyer_name: '',
  buyer_contact: '',
  observations: '',
}

// §6/§24 : deux cycles peuvent légitimement partager la même parcelle et la même culture (deux
// campagnes successives) — jamais un doublon à fusionner, seulement un libellé à désambiguïser.
const CYCLE_STATUS_LABEL: Record<CropCycle['status'], string> = { in_progress: 'en cours', harvested: 'récolté', failed: 'échoué' }
function cycleLabel(c: CropCycle): string {
  const base = `${c.plot?.name ?? c.plot_id} — ${c.crop?.name ?? c.crop_id}`
  const campaign = c.campaign ? `${c.campaign.name} (${c.campaign.year})` : null
  const details = [campaign, CYCLE_STATUS_LABEL[c.status]].filter(Boolean).join(', ')
  return details ? `${base} — ${details}` : base
}

type Mode = 'cycle' | 'aggregate'

export function SaleFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'

  // §28 : le cycle cultural est désormais la relation RACINE du formulaire — plus la
  // coopérative. « Vente agrégée » (§14/§17/§18) reste un second mode explicite et séparé,
  // jamais une fausse option du menu Cycle, pour les ventes qu'aucun cycle ne représente.
  const [mode, setMode] = useState<Mode>('cycle')

  const [form, setForm] = useState<SalePayload>(emptyForm)
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<NormalizedError | null>(null)

  // §16 : le cycle cultural est chargé UNE SEULE FOIS avec toutes ses relations (parcelle ->
  // agriculteur/coopérative, culture, variété, campagne — voir CropCycleController::index()) :
  // sélectionner un cycle ne déclenche plus aucun appel réseau supplémentaire, tout est déjà là.
  // Le périmètre (quels cycles sont visibles) reste géré côté serveur selon le rôle, comme pour
  // toutes les autres ressources — jamais élargi ici.
  const { options: cropCycles, loading: loadingCycles } = useOptions(() => cropCyclesApi.list({ per_page: 200 }), [])

  const selectedCycle = useMemo(
    () => cropCycles.find((c) => c.id === form.crop_cycle_id) ?? null,
    [cropCycles, form.crop_cycle_id],
  )

  // §20 : `selectedCropCycle` est la seule source de vérité en mode "cycle" — tout le reste
  // (parcelle/culture/variété/agriculteur/coopérative/campagne affichés) en est DÉRIVÉ à chaque
  // rendu, jamais maintenu comme un état indépendant qui pourrait diverger.
  const derived = useMemo(() => {
    if (mode !== 'cycle' || !selectedCycle) return null
    return {
      plotName: selectedCycle.plot?.name ?? null,
      cropName: selectedCycle.crop?.name ?? null,
      varietyName: selectedCycle.variety?.name ?? null,
      farmerName: selectedCycle.plot?.farmer?.full_name ?? null,
      cooperativeName: selectedCycle.plot?.cooperative?.name ?? null,
      campaignLabel: selectedCycle.campaign ? `${selectedCycle.campaign.name} (${selectedCycle.campaign.year})` : null,
    }
  }, [mode, selectedCycle])

  // §12 : sélectionner un cycle recalcule IMMÉDIATEMENT toute la chaîne dérivée — aucune valeur
  // de l'ancien cycle ne doit survivre. Les identifiants envoyés au serveur restent dérivés du
  // cycle (le backend les revalide et les re-dérive de toute façon, §17 : lui seul fait foi).
  function handleCycleChange(value: string) {
    const cycleId = value ? Number(value) : null
    const cycle = cropCycles.find((c) => c.id === cycleId) ?? null
    setForm((prev) => ({
      ...prev,
      crop_cycle_id: cycleId,
      plot_id: cycle?.plot_id ?? null,
      crop_id: cycle?.crop_id ?? null,
      variety_id: cycle?.variety_id ?? null,
      campaign_id: cycle?.campaign_id ?? null,
      farmer_id: cycle?.plot?.farmer_id ?? null,
      cooperative_id: cycle?.plot?.cooperative_id ?? prev.cooperative_id,
    }))
  }

  function switchMode(next: Mode) {
    setMode(next)
    // §12/§15 : changer de mode abandonne toute sélection relationnelle de l'autre mode — jamais
    // un mélange (ex. un cooperative_id choisi en mode agrégé qui resterait après retour au mode
    // cycle, ou l'inverse).
    setForm((prev) => ({
      ...emptyForm,
      sale_date: prev.sale_date,
      quantity: prev.quantity,
      unit: prev.unit,
      unit_price: prev.unit_price,
      currency: prev.currency,
      buyer_name: prev.buyer_name,
      buyer_contact: prev.buyer_contact,
      observations: prev.observations,
      cooperative_id: next === 'aggregate' && !isAdmin ? undefined : prev.cooperative_id,
    }))
  }

  // ------------------------------------------------------------------------------------------
  // Mode "vente agrégée" (§14/§17/§18) : comportement préexistant conservé tel quel — la
  // coopérative y redevient le point d'entrée (choisie par l'admin, ou celle du
  // cooperative_manager), parcelle/agriculteur restant optionnels et filtrés par elle.
  // ------------------------------------------------------------------------------------------
  const { options: cooperatives } = useOptions(() => cooperativesApi.list({ per_page: 200 }), [])
  const { options: crops } = useOptions(() => cropsApi.list({ per_page: 200 }), [])
  const { options: campaigns } = useOptions(() => campaignsApi.list({ per_page: 200 }), [])
  const [varieties, setVarieties] = useState<{ id: number; name: string }[]>([])

  useEffect(() => {
    if (mode !== 'aggregate' || !form.crop_id) {
      setVarieties([])
      return
    }
    let cancelled = false
    cropVarietiesApi.list({ crop_id: form.crop_id, per_page: 200 }).then((res) => {
      if (!cancelled) setVarieties(res.data)
    })
    return () => {
      cancelled = true
    }
  }, [mode, form.crop_id])

  const effectiveCooperativeId = isAdmin ? (form.cooperative_id ?? null) : (user?.managed_cooperative_id ?? null)
  const [aggPlots, setAggPlots] = useState<Plot[]>([])
  const [aggFarmers, setAggFarmers] = useState<Farmer[]>([])
  const [loadingAggRelations, setLoadingAggRelations] = useState(false)
  const [aggRelationsError, setAggRelationsError] = useState<string | null>(null)

  useEffect(() => {
    if (mode !== 'aggregate' || !effectiveCooperativeId) {
      setAggPlots([])
      setAggFarmers([])
      return
    }
    let cancelled = false
    setLoadingAggRelations(true)
    setAggRelationsError(null)
    Promise.all([
      plotsApi.list({ cooperative_id: effectiveCooperativeId, per_page: 200 }),
      farmersApi.list({ cooperative_id: effectiveCooperativeId, per_page: 200 }),
    ])
      .then(([plotsRes, farmersRes]) => {
        if (cancelled) return
        setAggPlots(plotsRes.data)
        setAggFarmers(farmersRes.data)
      })
      .catch((err) => {
        if (cancelled) return
        setAggRelationsError(toApiError(err).message)
      })
      .finally(() => {
        if (!cancelled) setLoadingAggRelations(false)
      })
    return () => {
      cancelled = true
    }
  }, [mode, effectiveCooperativeId])

  function handleAggregateCooperativeChange(value: string) {
    const newCooperativeId = value ? Number(value) : undefined
    setForm((prev) => ({ ...prev, cooperative_id: newCooperativeId, plot_id: null, farmer_id: null }))
  }

  // ------------------------------------------------------------------------------------------

  useEffect(() => {
    if (!id) return
    salesApi
      .get(Number(id))
      .then((s) => {
        setMode(s.crop_cycle_id ? 'cycle' : 'aggregate')
        setForm({
          cooperative_id: s.cooperative_id,
          farmer_id: s.farmer_id,
          plot_id: s.plot_id,
          crop_id: s.crop_id,
          variety_id: s.variety_id,
          crop_cycle_id: s.crop_cycle_id,
          campaign_id: s.campaign_id,
          buyer_name: s.buyer_name ?? '',
          buyer_contact: s.buyer_contact ?? '',
          sale_date: s.sale_date,
          quantity: s.quantity,
          unit: s.unit,
          unit_price: s.unit_price,
          currency: s.currency,
          observations: s.observations ?? '',
        })
      })
      .catch((err) => setError(toApiError(err)))
      .finally(() => setLoading(false))
  }, [id])

  // Aperçu uniquement — §29 : le backend recalcule systématiquement ce montant.
  const totalPreview = useMemo(() => (form.quantity || 0) * (form.unit_price || 0), [form.quantity, form.unit_price])

  const canSubmit = mode === 'aggregate' ? true : form.crop_cycle_id != null

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    if (!canSubmit) return
    setSaving(true)
    setError(null)
    try {
      const payload: SalePayload = {
        ...form,
        buyer_name: form.buyer_name || null,
        buyer_contact: form.buyer_contact || null,
        observations: form.observations || null,
      }
      if (isEdit) {
        await salesApi.update(Number(id), payload)
      } else {
        await salesApi.create(payload)
      }
      navigate('/sales')
    } catch (err) {
      setError(toApiError(err))
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <Spinner />
  const fieldErrors = error?.errors ?? {}

  return (
    <div className="flex flex-col gap-5">
      <h1 className="text-2xl font-semibold text-ink">{isEdit ? 'Modifier la vente' : 'Nouvelle vente'}</h1>

      <form className="flex flex-col gap-4 rounded-lg border border-line bg-surface p-6 shadow-sm" onSubmit={handleSubmit}>
        {error && !Object.keys(fieldErrors).length && <Alert>{error.message}</Alert>}
        {aggRelationsError && <Alert>{aggRelationsError}</Alert>}

        <div className="flex items-center justify-between gap-3 rounded-md bg-canvas px-4 py-3">
          <p className="text-sm text-ink">
            {mode === 'cycle'
              ? 'Le cycle cultural détermine automatiquement parcelle, culture, variété, agriculteur, coopérative et campagne.'
              : 'Vente agrégée — non rattachée à un cycle cultural précis.'}
          </p>
          <Button type="button" variant="secondary" onClick={() => switchMode(mode === 'cycle' ? 'aggregate' : 'cycle')} disabled={isEdit}>
            {mode === 'cycle' ? 'Basculer en vente agrégée (sans cycle)' : 'Revenir à une vente liée à un cycle'}
          </Button>
        </div>

        {mode === 'cycle' ? (
          <div className="form-grid">
            <FormField
              label="Cycle cultural / récolte"
              htmlFor="crop_cycle_id"
              required
              hint={
                loadingCycles
                  ? 'Chargement des cycles…'
                  : cropCycles.length === 0
                    ? 'Aucun cycle cultural disponible.'
                    : !form.crop_cycle_id
                      ? "Sélectionnez d'abord un cycle cultural / une récolte."
                      : undefined
              }
              errors={fieldErrors.crop_cycle_id}
            >
              <select id="crop_cycle_id" value={form.crop_cycle_id ?? ''} onChange={(e) => handleCycleChange(e.target.value)} required disabled={loadingCycles}>
                <option value="" disabled>
                  Sélectionner…
                </option>
                {cropCycles.map((c) => (
                  <option key={c.id} value={c.id}>
                    {cycleLabel(c)}
                  </option>
                ))}
              </select>
            </FormField>

            {/* §4-§9/§11/§25 : champs dérivés — désactivés et réduits à la seule valeur cohérente
                avec le cycle sélectionné, jamais une sélection libre (§7/§8/§9). */}
            <FormField label="Parcelle" htmlFor="derived_plot">
              <select id="derived_plot" value={derived?.plotName ? '1' : ''} disabled>
                <option value="">{selectedCycle ? 'Non spécifiée' : '—'}</option>
                {derived?.plotName && <option value="1">{derived.plotName}</option>}
              </select>
            </FormField>

            <FormField label="Culture" htmlFor="derived_crop">
              <select id="derived_crop" value={derived?.cropName ? '1' : ''} disabled>
                <option value="">{selectedCycle ? 'Non spécifiée' : '—'}</option>
                {derived?.cropName && <option value="1">{derived.cropName}</option>}
              </select>
            </FormField>

            <FormField label="Variété" htmlFor="derived_variety">
              <select id="derived_variety" value={derived?.varietyName ? '1' : ''} disabled>
                <option value="">Non spécifiée</option>
                {derived?.varietyName && <option value="1">{derived.varietyName}</option>}
              </select>
            </FormField>

            <FormField label="Agriculteur" htmlFor="derived_farmer">
              <select id="derived_farmer" value={derived?.farmerName ? '1' : ''} disabled>
                <option value="">Non spécifié</option>
                {derived?.farmerName && <option value="1">{derived.farmerName}</option>}
              </select>
            </FormField>

            <FormField label="Coopérative" htmlFor="derived_cooperative">
              <select id="derived_cooperative" value={derived?.cooperativeName ? '1' : ''} disabled>
                <option value="">{selectedCycle ? 'Non spécifiée' : '—'}</option>
                {derived?.cooperativeName && <option value="1">{derived.cooperativeName}</option>}
              </select>
            </FormField>

            <FormField label="Campagne agricole" htmlFor="derived_campaign">
              <select id="derived_campaign" value={derived?.campaignLabel ? '1' : ''} disabled>
                <option value="">Non spécifiée</option>
                {derived?.campaignLabel && <option value="1">{derived.campaignLabel}</option>}
              </select>
            </FormField>
          </div>
        ) : (
          <div className="form-grid">
            {isAdmin && (
              <FormField label="Coopérative" htmlFor="cooperative_id" required errors={fieldErrors.cooperative_id}>
                <select id="cooperative_id" value={form.cooperative_id ?? ''} onChange={(e) => handleAggregateCooperativeChange(e.target.value)} required disabled={isEdit}>
                  <option value="" disabled>
                    Sélectionner…
                  </option>
                  {cooperatives.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </select>
              </FormField>
            )}

            <FormField label="Culture" htmlFor="crop_id" errors={fieldErrors.crop_id}>
              <select
                id="crop_id"
                value={form.crop_id ?? ''}
                onChange={(e) => setForm({ ...form, crop_id: e.target.value ? Number(e.target.value) : null, variety_id: null })}
              >
                <option value="">Non spécifiée</option>
                {crops.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </FormField>

            <FormField label="Variété" htmlFor="variety_id" errors={fieldErrors.variety_id}>
              <select
                id="variety_id"
                value={form.variety_id ?? ''}
                onChange={(e) => setForm({ ...form, variety_id: e.target.value ? Number(e.target.value) : null })}
                disabled={!form.crop_id}
              >
                <option value="">Non spécifiée</option>
                {varieties.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.name}
                  </option>
                ))}
              </select>
            </FormField>

            <FormField
              label="Parcelle"
              htmlFor="plot_id"
              errors={fieldErrors.plot_id}
              hint={!effectiveCooperativeId ? "Sélectionnez d'abord une coopérative." : loadingAggRelations ? 'Chargement des parcelles…' : aggPlots.length === 0 ? 'Aucune parcelle disponible pour cette coopérative.' : undefined}
            >
              <select
                id="plot_id"
                value={form.plot_id ?? ''}
                onChange={(e) => setForm({ ...form, plot_id: e.target.value ? Number(e.target.value) : null })}
                disabled={!effectiveCooperativeId || loadingAggRelations}
              >
                <option value="">Non spécifiée</option>
                {aggPlots.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
            </FormField>

            <FormField
              label="Agriculteur"
              htmlFor="farmer_id"
              errors={fieldErrors.farmer_id}
              hint={!effectiveCooperativeId ? "Sélectionnez d'abord une coopérative." : loadingAggRelations ? 'Chargement des agriculteurs…' : aggFarmers.length === 0 ? 'Aucun agriculteur disponible pour cette coopérative.' : undefined}
            >
              <select
                id="farmer_id"
                value={form.farmer_id ?? ''}
                onChange={(e) => setForm({ ...form, farmer_id: e.target.value ? Number(e.target.value) : null })}
                disabled={!effectiveCooperativeId || loadingAggRelations}
              >
                <option value="">Non spécifié</option>
                {aggFarmers.map((f) => (
                  <option key={f.id} value={f.id}>
                    {f.full_name}
                  </option>
                ))}
              </select>
            </FormField>

            <FormField label="Campagne agricole" htmlFor="campaign_id" errors={fieldErrors.campaign_id}>
              <select id="campaign_id" value={form.campaign_id ?? ''} onChange={(e) => setForm({ ...form, campaign_id: e.target.value ? Number(e.target.value) : null })}>
                <option value="">Non spécifiée</option>
                {campaigns.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} ({c.year})
                  </option>
                ))}
              </select>
            </FormField>
          </div>
        )}

        <div className="form-grid">
          <FormField label="Acheteur" htmlFor="buyer_name" errors={fieldErrors.buyer_name}>
            <input id="buyer_name" value={form.buyer_name ?? ''} onChange={(e) => setForm({ ...form, buyer_name: e.target.value })} />
          </FormField>

          <FormField label="Contact de l'acheteur" htmlFor="buyer_contact" errors={fieldErrors.buyer_contact}>
            <input id="buyer_contact" value={form.buyer_contact ?? ''} onChange={(e) => setForm({ ...form, buyer_contact: e.target.value })} />
          </FormField>

          <FormField label="Date de vente" htmlFor="sale_date" required errors={fieldErrors.sale_date}>
            <input id="sale_date" type="date" value={form.sale_date} onChange={(e) => setForm({ ...form, sale_date: e.target.value })} required />
          </FormField>

          <FormField label="Quantité" htmlFor="quantity" required errors={fieldErrors.quantity}>
            <input
              id="quantity"
              type="number"
              min={0.01}
              step="any"
              value={form.quantity}
              onChange={(e) => setForm({ ...form, quantity: Number(e.target.value) })}
              required
            />
          </FormField>

          <FormField label="Unité" htmlFor="unit" required errors={fieldErrors.unit}>
            <input id="unit" value={form.unit} onChange={(e) => setForm({ ...form, unit: e.target.value })} placeholder="kg, sac, tonne…" required />
          </FormField>

          <FormField label="Prix unitaire" htmlFor="unit_price" required errors={fieldErrors.unit_price}>
            <input
              id="unit_price"
              type="number"
              min={0}
              step="any"
              value={form.unit_price}
              onChange={(e) => setForm({ ...form, unit_price: Number(e.target.value) })}
              required
            />
          </FormField>

          <FormField label="Devise" htmlFor="currency" errors={fieldErrors.currency}>
            <select id="currency" value={form.currency} onChange={(e) => setForm({ ...form, currency: e.target.value })}>
              <option value="MGA">MGA (Ariary malgache)</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </FormField>
        </div>

        <div className="rounded-md bg-primary-light px-4 py-3 text-sm text-primary-dark">
          Montant total (calculé, confirmé par le serveur à l'enregistrement) :{' '}
          <strong>{totalPreview.toLocaleString('fr-FR')} {form.currency}</strong>
        </div>

        <FormField label="Observations" htmlFor="observations" errors={fieldErrors.observations}>
          <textarea id="observations" value={form.observations ?? ''} onChange={(e) => setForm({ ...form, observations: e.target.value })} rows={3} />
        </FormField>

        <div className="form-actions">
          <Button type="button" variant="secondary" onClick={() => navigate(-1)}>
            Annuler
          </Button>
          <Button type="submit" loading={saving} disabled={!canSubmit}>
            Enregistrer
          </Button>
        </div>
      </form>
    </div>
  )
}

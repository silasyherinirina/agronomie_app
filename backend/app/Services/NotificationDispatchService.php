<?php

namespace App\Services;

use App\Models\AgriculturalCampaign;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\ProductionPrediction;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\CampaignActivatedNotification;
use App\Notifications\HarvestRecordedNotification;
use App\Notifications\PredictionReadyNotification;
use App\Notifications\SaleRecordedNotification;
use App\Notifications\WeatherAlertNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Centralise "qui reçoit quelle notification" pour les événements métier réels de
 * l'application (§6/§22 du centre de notifications) : jamais dans les Policies/FormRequests,
 * toujours appelé après une persistance réussie, et toujours borné aux utilisateurs
 * réellement concernés par l'entité (respect strict des rôles — voir les Policies existantes,
 * inchangées par ce service).
 */
class NotificationDispatchService
{
    /**
     * Récolte enregistrée (Module F) : notifie l'agriculteur propriétaire de la parcelle, s'il
     * dispose d'un compte utilisateur. Le cooperative_manager qui vient de la saisir n'a pas
     * besoin d'être notifié de sa propre action.
     */
    public function harvestRecorded(Harvest $harvest): void
    {
        $harvest->loadMissing(['cropCycle.plot.farmer.user', 'cropCycle.crop']);

        $farmerUser = $harvest->cropCycle?->plot?->farmer?->user;

        $farmerUser?->notify(new HarvestRecordedNotification($harvest));
    }

    /**
     * Vente enregistrée (Module M) : notifie l'agriculteur concerné, quand la vente est
     * rattachée à un `farmer_id` avec compte utilisateur. Une vente agrégée sans agriculteur
     * ne notifie personne — pas de destinataire "par défaut" inventé.
     */
    public function saleRecorded(Sale $sale): void
    {
        $sale->loadMissing(['farmer.user', 'crop']);

        $farmerUser = $sale->farmer?->user;

        $farmerUser?->notify(new SaleRecordedNotification($sale));
    }

    /**
     * Prévision IA disponible (Module J/K) : notifie l'agriculteur de la parcelle et le
     * responsable de la coopérative propriétaire de la parcelle — les deux rôles qui consultent
     * réellement les prévisions pour cette parcelle (cf. ProductionPredictionPolicy::view()).
     */
    public function predictionReady(ProductionPrediction $prediction): void
    {
        $prediction->loadMissing(['plot.farmer.user', 'plot.cooperative.manager']);

        $plot = $prediction->plot;
        if (! $plot) {
            return;
        }

        $notification = new PredictionReadyNotification($prediction);

        collect([$plot->farmer?->user, $plot->cooperative?->manager])
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * Campagne agricole activée : événement global (une campagne n'est pas rattachée à une
     * coopérative), notifié à tous les utilisateurs. `Notification::send()` avec une Collection
     * en mémoire reste adapté à l'échelle d'une plateforme coopérative (pas des millions de
     * comptes) — à revoir avec un traitement par lot si le volume d'utilisateurs grandit
     * significativement.
     */
    public function campaignActivated(AgriculturalCampaign $campaign): void
    {
        Notification::send(User::all(), new CampaignActivatedNotification($campaign));
    }

    /**
     * Alerte météo (extension Module L) : notifie l'agriculteur de la parcelle et le
     * responsable de sa coopérative. Appelée uniquement par la commande planifiée
     * `weather:check-alerts` à partir d'une prévision réelle (jamais générée à la main).
     *
     * @param  array{date: string, precipitation_mm: float, source: string}  $context
     */
    public function weatherAlert(Plot $plot, array $context): void
    {
        $plot->loadMissing(['farmer.user', 'cooperative.manager']);

        $notification = new WeatherAlertNotification($plot, $context);

        collect([$plot->farmer?->user, $plot->cooperative?->manager])
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $user->notify($notification));
    }
}

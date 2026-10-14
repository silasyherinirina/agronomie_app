<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgriculturalCampaign;
use App\Models\Plot;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Module H — Tableau de bord analytique (Phase 7). Accessible à tous les rôles authentifiés ;
 * le périmètre des données (coopérative, agriculteur) est déterminé par DashboardService selon
 * le rôle, jamais par les paramètres de requête pour cooperative_manager/farmer.
 *
 * Filtre Parcelle (extension) : quand `plot_id` est fourni, le backend reste seul juge de la
 * cohérence des autres filtres — jamais une confiance aveugle dans les valeurs envoyées par le
 * frontend (cahier des charges — "le backend reste la source de vérité"). `cooperative_id`/
 * `campaign_id` incohérents avec la parcelle sont rejetés (422), pas silencieusement corrigés.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): JsonResponse
    {
        $cooperativeId = $request->filled('cooperative_id') ? (int) $request->query('cooperative_id') : null;
        $campaignId = $request->filled('campaign_id') ? (int) $request->query('campaign_id') : null;
        $plotId = $request->filled('plot_id') ? (int) $request->query('plot_id') : null;

        if ($plotId !== null) {
            $plot = Plot::findOrFail($plotId);
            // Mêmes règles que la fiche parcelle (PlotPolicy::view) : un cooperative_manager/
            // farmer ne peut pas utiliser l'id d'une parcelle hors de son périmètre, même en le
            // forçant dans l'URL — jamais une confiance aveugle dans le frontend (§29).
            $this->authorize('view', $plot);

            if ($cooperativeId !== null && $cooperativeId !== $plot->cooperative_id) {
                throw ValidationException::withMessages([
                    'cooperative_id' => ["Cette coopérative ne correspond pas à la parcelle sélectionnée."],
                ]);
            }

            if ($campaignId !== null) {
                $campaignBelongsToPlot = AgriculturalCampaign::query()
                    ->where('id', $campaignId)
                    ->whereHas('cropCycles', fn ($q) => $q->where('plot_id', $plot->id))
                    ->exists();

                if (! $campaignBelongsToPlot) {
                    throw ValidationException::withMessages([
                        'campaign_id' => ["Cette campagne n'est pas associée à la parcelle sélectionnée."],
                    ]);
                }
            }
        }

        return response()->json([
            'data' => $this->dashboard->summary($request->user(), $cooperativeId, $campaignId, $plotId),
        ]);
    }
}

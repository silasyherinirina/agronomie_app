<?php

use App\Http\Controllers\Api\AgriculturalCampaignController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CooperativeController;
use App\Http\Controllers\Api\CropController;
use App\Http\Controllers\Api\CropCycleController;
use App\Http\Controllers\Api\CropModelStatusController;
use App\Http\Controllers\Api\CropVarietyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FarmerController;
use App\Http\Controllers\Api\HarvestController;
use App\Http\Controllers\Api\InputController;
use App\Http\Controllers\Api\InputUsageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PlotController;
use App\Http\Controllers\Api\ProductionPredictionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\WeatherAdviceController;
use App\Http\Controllers\Api\WeatherForecastController;
use App\Http\Controllers\Api\WeatherObservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentification (SPA Sanctum — cookie httpOnly + CSRF)
|--------------------------------------------------------------------------
| Flux : GET /sanctum/csrf-cookie (auto Sanctum) -> POST /api/login -> GET /api/user.
| Phase D : inscription, mot de passe oublié/réinitialisation, vérification d'e-mail.
*/
// throttle:login/register/password-reset — Phase 13/D, voir AppServiceProvider.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');

// Lien signé reçu par e-mail : nécessite une session active dans le même navigateur (voir
// EmailVerificationRequest — Laravel exige $request->user() pour vérifier que l'id de la
// route correspond bien à l'utilisateur connecté), d'où auth:sanctum ici.
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1');

    // Phase D, §9 — profil du compte connecté
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Module H — Tableau de bord analytique (Phase 7)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Module A — Coopératives
    Route::apiResource('cooperatives', CooperativeController::class);

    // Module B — Agriculteurs
    Route::apiResource('farmers', FarmerController::class);

    // Module C — Parcelles (PostGIS)
    Route::apiResource('plots', PlotController::class);
    // Campagnes réellement associées à une parcelle (via ses cycles culturaux) — alimente le
    // filtre "Campagne" du Dashboard une fois une parcelle précise sélectionnée. Segment
    // supplémentaire après {plot} : aucune collision possible avec plots/{plot} (show).
    Route::get('plots/{plot}/campaigns', [PlotController::class, 'campaigns']);

    // Module D — Cultures (référentiel)
    // Doit être déclarée AVANT apiResource('crops', ...) : sinon "model-status" serait capturé
    // par la route crops/{crop} (liaison de modèle sur un id) au lieu de ce contrôleur dédié.
    Route::get('/crops/model-status', [CropModelStatusController::class, 'index']);
    Route::apiResource('crops', CropController::class);
    Route::apiResource('crop-varieties', CropVarietyController::class);

    // Module E — Campagnes agricoles (référentiel)
    Route::apiResource('agricultural-campaigns', AgriculturalCampaignController::class);

    // Association parcelle × culture × campagne
    Route::apiResource('crop-cycles', CropCycleController::class);

    // Module F — Récoltes (suppression restreinte à l'administrateur, cf. HarvestPolicy)
    Route::apiResource('harvests', HarvestController::class);

    // Module G — Intrants (référentiel + utilisations, suppression restreinte à l'administrateur)
    Route::apiResource('inputs', InputController::class);
    Route::apiResource('input-usages', InputUsageController::class);

    // Module J/K — Prévisions de production (Phase 10 : Laravel -> FastAPI -> ML)
    Route::get('plots/{plot}/prediction-inputs', [ProductionPredictionController::class, 'suggestedInputs']);
    Route::apiResource('predictions', ProductionPredictionController::class)->only(['index', 'store', 'show']);

    // Module L — Météo (Phase 11, extension non bloquante). Prévision réelle (Open-Meteo,
    // sans clé) + observations manuelles existantes, combinées pour une parcelle donnée.
    Route::get('plots/{plot}/weather', [WeatherForecastController::class, 'show']);
    // Conseil agronomique prédictif basé sur la météo (extension) : région -> zone
    // agroécologique -> analyse météo historique/actuelle/prévisionnelle -> cultures
    // recommandées. `date` obligatoire (query string) : détermine seule le type d'analyse.
    Route::get('plots/{plot}/agronomic-advice', [WeatherAdviceController::class, 'show']);
    Route::apiResource('weather-observations', WeatherObservationController::class);

    // Module M — Ventes agricoles (déclarée avant apiResource('sales', ...) : même raison que
    // /crops/model-status ci-dessus, sinon "statistics" serait capturé par sales/{sale}).
    Route::get('/sales/statistics', [SaleController::class, 'statistics']);
    Route::apiResource('sales', SaleController::class);

    // Module Rapports — export CSV/Excel/PDF (production, récoltes, ventes ; §18 priorité).
    Route::get('/reports/{type}', [ReportController::class, 'generate']);

    // Centre de notifications — Database Notifications Laravel (Notifiable déjà sur User).
    // unread-count/read-all déclarées avant notifications/{notification} : même raison que
    // /crops/model-status et /sales/statistics ci-dessus (sinon capturées par le paramètre).
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/{notification}/unread', [NotificationController::class, 'markAsUnread']);
});

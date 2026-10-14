<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Weather\WeatherAdviceRequest;
use App\Models\Plot;
use App\Services\WeatherAgronomicAdviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Module — Conseil agronomique prédictif basé sur la météo. Reste un simple point d'entrée
 * fin (§17/§29 : "éviter de mettre toute cette logique dans le Controller") : toute la
 * logique vit dans WeatherAgronomicAdviceService et les services qu'il orchestre.
 *
 * Extension de la route plots/{plot}/weather déjà existante (WeatherForecastController) —
 * même convention d'URL imbriquée, même politique d'autorisation ('view' sur la parcelle),
 * même binding implicite de route (404 automatique si la parcelle n'existe pas).
 */
class WeatherAdviceController extends Controller
{
    public function __construct(private readonly WeatherAgronomicAdviceService $advice) {}

    public function show(WeatherAdviceRequest $request, Plot $plot): JsonResponse
    {
        $date = Carbon::createFromFormat('Y-m-d', $request->validated('date'))->startOfDay();

        return response()->json(['data' => $this->advice->adviceFor($plot, $date)]);
    }
}

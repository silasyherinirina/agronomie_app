<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WeatherProviderException;
use App\Http\Controllers\Controller;
use App\Http\Resources\WeatherObservationResource;
use App\Models\Plot;
use App\Services\WeatherProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Module L — Météo (Phase 11/H). Combine la météo réelle d'un fournisseur externe (Open-Meteo,
 * sans clé) pour le centroïde de la parcelle avec les observations manuelles déjà enregistrées
 * (Phase 11 initiale) — les deux sources coexistent, comme prévu au cahier des charges §19.
 */
class WeatherForecastController extends Controller
{
    public function __construct(private readonly WeatherProviderService $weather) {}

    public function show(Plot $plot): JsonResponse
    {
        $this->authorize('view', $plot);

        $centroid = DB::selectOne(
            'SELECT ST_Y(ST_Centroid(geom)) AS lat, ST_X(ST_Centroid(geom)) AS lng FROM plots WHERE id = ?',
            [$plot->id]
        );

        $forecast = null;
        $providerError = null;

        try {
            $forecast = $this->weather->forecast((float) $centroid->lat, (float) $centroid->lng);
        } catch (WeatherProviderException $e) {
            // Ne bloque jamais l'affichage : la parcelle garde ses observations manuelles même
            // si le fournisseur externe est indisponible (cahier des charges §19 — fallback).
            $providerError = $e->getMessage();
        }

        $recentObservations = $plot->weatherObservations()
            ->latest('observed_date')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'plot_id' => $plot->id,
                'centroid' => ['lat' => (float) $centroid->lat, 'lng' => (float) $centroid->lng],
                'forecast' => $forecast,
                'provider_error' => $providerError,
                'recent_manual_observations' => WeatherObservationResource::collection($recentObservations),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeatherObservation\StoreWeatherObservationRequest;
use App\Http\Requests\WeatherObservation\UpdateWeatherObservationRequest;
use App\Http\Resources\WeatherObservationResource;
use App\Models\Plot;
use App\Models\WeatherObservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Module L — Météo (Phase 11, extension non bloquante). Saisie manuelle d'observations
 * réelles ; aucun appel à un fournisseur météo externe n'est effectué ici (voir
 * PredictionService::suggestInputs pour comment ces données sont réutilisées en Phase 10).
 */
class WeatherObservationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WeatherObservation::class);

        $query = WeatherObservation::query()->with('plot');
        $user = $request->user();

        $query = match ($user->role) {
            'cooperative_manager' => $query->whereHas('plot', fn ($q) => $q->where('cooperative_id', $user->managedCooperative?->id ?? 0)),
            'farmer' => $query->whereHas('plot', fn ($q) => $q->where('farmer_id', $user->farmer?->id ?? 0)),
            default => $query, // admin, analyst
        };

        if ($plotId = $request->query('plot_id')) {
            $query->where('plot_id', $plotId);
        }

        if ($from = $request->query('from')) {
            $query->where('observed_date', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('observed_date', '<=', $to);
        }

        return WeatherObservationResource::collection(
            $query->orderByDesc('observed_date')->paginate($this->perPage($request))
        );
    }

    public function store(StoreWeatherObservationRequest $request): WeatherObservationResource
    {
        $data = $request->validated();
        $user = $request->user();

        $plot = Plot::findOrFail($data['plot_id']);

        if ($user->role === 'cooperative_manager' && $plot->cooperative_id !== $user->managedCooperative?->id) {
            throw ValidationException::withMessages([
                'plot_id' => ["Cette parcelle n'appartient pas à votre coopérative."],
            ]);
        }

        $observation = WeatherObservation::create($data);

        return new WeatherObservationResource($observation->load('plot'));
    }

    public function show(WeatherObservation $weatherObservation): WeatherObservationResource
    {
        $this->authorize('view', $weatherObservation);

        return new WeatherObservationResource($weatherObservation->load('plot'));
    }

    public function update(UpdateWeatherObservationRequest $request, WeatherObservation $weatherObservation): WeatherObservationResource
    {
        $weatherObservation->update($request->validated());

        return new WeatherObservationResource($weatherObservation->load('plot'));
    }

    public function destroy(WeatherObservation $weatherObservation): Response
    {
        $this->authorize('delete', $weatherObservation);

        $weatherObservation->delete();

        return response()->noContent();
    }
}

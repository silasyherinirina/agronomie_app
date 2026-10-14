<?php

namespace Tests\Feature\Api;

use App\Models\Plot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

/**
 * Module L — Météo (Phase 11/H). Le fournisseur externe (Open-Meteo) est simulé via Http::fake
 * : ces tests ne dépendent jamais du réseau réel (cohérent avec le reste de la suite).
 */
class WeatherForecastApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    private function squarePlot(int $cooperativeId): Plot
    {
        return Plot::factory()->create([
            'cooperative_id' => $cooperativeId,
            'geom' => new Polygon([
                new LineString([
                    new Point(-18.88, 47.50),
                    new Point(-18.88, 47.51),
                    new Point(-18.87, 47.51),
                    new Point(-18.87, 47.50),
                    new Point(-18.88, 47.50),
                ]),
            ]),
        ]);
    }

    public function test_returns_real_provider_forecast_for_plot_centroid(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-09-03T10:00',
                    'temperature_2m' => 22.5,
                    'relative_humidity_2m' => 60,
                    'precipitation' => 0.0,
                    'wind_speed_10m' => 12.0,
                ],
                'daily' => [
                    'time' => ['2026-09-03', '2026-09-04'],
                    'precipitation_sum' => [0.0, 5.2],
                    'temperature_2m_max' => [25.0, 24.0],
                    'temperature_2m_min' => [15.0, 14.0],
                ],
            ], 200),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = $this->squarePlot($coop->id);

        $response = $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/weather")->assertOk();

        $response->assertJsonPath('data.forecast.current.temperature', 22.5)
            ->assertJsonPath('data.forecast.source', 'open-meteo')
            ->assertJsonPath('data.provider_error', null)
            ->assertJsonCount(2, 'data.forecast.daily');

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'api.open-meteo.com'));
    }

    public function test_falls_back_gracefully_when_provider_is_unreachable(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response(null, 500),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = $this->squarePlot($coop->id);

        \App\Models\WeatherObservation::factory()->create(['plot_id' => $plot->id, 'temperature' => 21]);

        $response = $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/weather")->assertOk();

        $response->assertJsonPath('data.forecast', null)
            ->assertJsonCount(1, 'data.recent_manual_observations');
        $this->assertNotNull($response->json('data.provider_error'));
    }

    public function test_forecast_is_cached_and_not_requested_twice(): void
    {
        Cache::flush();
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => ['time' => '2026-09-03T10:00', 'temperature_2m' => 20, 'relative_humidity_2m' => 50, 'precipitation' => 0, 'wind_speed_10m' => 5],
                'daily' => ['time' => [], 'precipitation_sum' => [], 'temperature_2m_max' => [], 'temperature_2m_min' => []],
            ], 200),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = $this->squarePlot($coop->id);

        $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/weather")->assertOk();
        $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/weather")->assertOk();

        Http::assertSentCount(1);
    }

    public function test_farmer_cannot_view_weather_of_a_plot_that_is_not_theirs(): void
    {
        [, $coop] = $this->makeCooperativeManager();
        $plot = $this->squarePlot($coop->id);
        [$farmerUser] = $this->makeFarmerUser();

        $this->actingAs($farmerUser)->getJson("/api/plots/{$plot->id}/weather")->assertForbidden();
    }
}

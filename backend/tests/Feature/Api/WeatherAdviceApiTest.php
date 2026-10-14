<?php

namespace Tests\Feature\Api;

use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\Plot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

/**
 * Module — Conseil agronomique prédictif basé sur la météo. Le fournisseur externe
 * (Open-Meteo, prévision ET archive) est simulé via Http::fake, comme WeatherForecastApiTest :
 * ces tests ne dépendent jamais du réseau réel.
 */
class WeatherAdviceApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    private function squarePlot(int $cooperativeId): Plot
    {
        return Plot::factory()->create([
            'cooperative_id' => $cooperativeId,
            'area_ha' => 1.2,
            'geom' => new Polygon([
                new LineString([
                    new Point(-19.86, 47.03),
                    new Point(-19.86, 47.04),
                    new Point(-19.85, 47.04),
                    new Point(-19.85, 47.03),
                    new Point(-19.86, 47.03),
                ]),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeDaily(array $dates): array
    {
        $n = count($dates);

        return [
            'time' => $dates,
            'temperature_2m_max' => array_fill(0, $n, 22.0),
            'temperature_2m_min' => array_fill(0, $n, 12.0),
            'precipitation_sum' => array_fill(0, $n, 1.5),
            'relative_humidity_2m_mean' => array_fill(0, $n, 70.0),
            'wind_speed_10m_max' => array_fill(0, $n, 15.0),
        ];
    }

    public function test_past_date_produces_a_historical_analysis(): void
    {
        Cache::flush();
        Http::fake([
            'archive-api.open-meteo.com/*' => Http::response([
                'daily' => $this->fakeDaily(['2020-01-12', '2020-01-13', '2020-01-14', '2020-01-15', '2020-01-16']),
            ], 200),
        ]);

        // Le catalogue `crops` d'une base de test fraîche est vide : au moins une culture de la
        // zone doit exister pour que AgroEcologicalZoneService::cropsForZone() résolve quelque
        // chose (comportement identique en production, cf. cahier des charges §11).
        Crop::factory()->create(['name' => 'Pomme de terre']);
        Crop::factory()->create(['name' => 'Riz']);

        [$manager, $coop] = $this->makeCooperativeManager();
        Cooperative::query()->where('id', $coop->id)->update(['region' => 'Vakinankaratra']);
        $plot = $this->squarePlot($coop->id);

        $response = $this->actingAs($manager)
            ->getJson("/api/plots/{$plot->id}/agronomic-advice?date=2020-01-15")
            ->assertOk();

        $response->assertJsonPath('data.analysis_type', 'historical')
            ->assertJsonPath('data.region', 'Vakinankaratra')
            ->assertJsonPath('data.agroecological_zone.name', 'Hautes Terres')
            ->assertJsonPath('data.unavailable_reason', null)
            ->assertJsonPath('data.weather.temperature_max', 22);

        $this->assertNotEmpty($response->json('data.recommendations'));
        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'archive-api.open-meteo.com'));
    }

    public function test_today_produces_a_current_conditions_analysis(): void
    {
        Cache::flush();
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => ['time' => Carbon::now()->toIso8601String(), 'temperature_2m' => 19.5, 'relative_humidity_2m' => 65, 'precipitation' => 0.2, 'wind_speed_10m' => 10.0],
                'daily' => $this->fakeDaily([
                    Carbon::today()->subDays(3)->toDateString(),
                    Carbon::today()->subDays(2)->toDateString(),
                    Carbon::today()->subDay()->toDateString(),
                    Carbon::today()->toDateString(),
                    Carbon::today()->addDay()->toDateString(),
                ]),
            ], 200),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        Cooperative::query()->where('id', $coop->id)->update(['region' => 'Boeny']);
        $plot = $this->squarePlot($coop->id);

        $response = $this->actingAs($manager)
            ->getJson('/api/plots/'.$plot->id.'/agronomic-advice?date='.Carbon::today()->toDateString())
            ->assertOk();

        $response->assertJsonPath('data.analysis_type', 'current')
            ->assertJsonPath('data.agroecological_zone.name', 'Ouest / Savane')
            ->assertJsonPath('data.weather.humidity', 65)
            ->assertJsonPath('data.weather.precipitation', 0.2);
    }

    public function test_future_date_produces_a_forecast_analysis(): void
    {
        Cache::flush();
        $target = Carbon::today()->addDays(5);
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'daily' => $this->fakeDaily([
                    $target->copy()->subDays(3)->toDateString(),
                    $target->copy()->subDays(2)->toDateString(),
                    $target->copy()->subDay()->toDateString(),
                    $target->toDateString(),
                    $target->copy()->addDay()->toDateString(),
                ]),
            ], 200),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        Cooperative::query()->where('id', $coop->id)->update(['region' => 'Analamanga']);
        $plot = $this->squarePlot($coop->id);

        $response = $this->actingAs($manager)
            ->getJson('/api/plots/'.$plot->id.'/agronomic-advice?date='.$target->toDateString())
            ->assertOk();

        $response->assertJsonPath('data.analysis_type', 'forecast')
            ->assertJsonPath('data.unavailable_reason', null);
    }

    public function test_date_too_far_in_the_future_is_reported_honestly_without_inventing_data(): void
    {
        Cache::flush();
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['error' => true, 'reason' => "Parameter 'start_date' is out of allowed range from 2026-06-08 to 2026-09-24"], 400),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        Cooperative::query()->where('id', $coop->id)->update(['region' => 'Analamanga']);
        $plot = $this->squarePlot($coop->id);

        $response = $this->actingAs($manager)
            ->getJson('/api/plots/'.$plot->id.'/agronomic-advice?date='.Carbon::today()->addDays(90)->toDateString())
            ->assertOk();

        $response->assertJsonPath('data.recommendations', [])
            ->assertJsonPath('data.summary', 'Les prévisions météorologiques disponibles ne couvrent pas encore cette date.');
    }

    public function test_plot_with_unrecognized_region_gets_an_honest_message_and_no_advice(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        Cooperative::query()->where('id', $coop->id)->update(['region' => 'Région Inconnue']);
        $plot = $this->squarePlot($coop->id);

        $response = $this->actingAs($manager)
            ->getJson('/api/plots/'.$plot->id.'/agronomic-advice?date='.Carbon::today()->toDateString())
            ->assertOk();

        $response->assertJsonPath('data.agroecological_zone', null)
            ->assertJsonPath('data.recommendations', []);
        $this->assertNotNull($response->json('data.unavailable_reason'));
    }

    public function test_returns_404_for_a_nonexistent_plot(): void
    {
        $manager = $this->makeAdmin();

        $this->actingAs($manager)
            ->getJson('/api/plots/999999/agronomic-advice?date=2026-01-01')
            ->assertNotFound();
    }

    public function test_returns_422_when_date_is_missing_or_invalid(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = $this->squarePlot($coop->id);

        $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/agronomic-advice")->assertStatus(422);
        $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/agronomic-advice?date=12-09-2026")->assertStatus(422);
    }

    public function test_farmer_cannot_view_advice_for_a_plot_that_is_not_theirs(): void
    {
        [, $coop] = $this->makeCooperativeManager();
        $plot = $this->squarePlot($coop->id);
        [$farmerUser] = $this->makeFarmerUser();

        $this->actingAs($farmerUser)
            ->getJson("/api/plots/{$plot->id}/agronomic-advice?date=2026-01-01")
            ->assertForbidden();
    }
}

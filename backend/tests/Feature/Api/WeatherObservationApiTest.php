<?php

namespace Tests\Feature\Api;

use App\Models\Plot;
use App\Models\WeatherObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

/**
 * Module L — Météo (Phase 11, extension non bloquante). Saisie manuelle uniquement : aucun
 * fournisseur météo externe n'est appelé (cahier des charges §24 — pas de valeur inventée).
 */
class WeatherObservationApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_store_requires_at_least_one_measurement(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);

        $response = $this->actingAs($manager)->postJson('/api/weather-observations', [
            'plot_id' => $plot->id,
            'observed_date' => '2026-08-20',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('temperature');
    }

    public function test_store_rejects_future_date(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);

        $response = $this->actingAs($manager)->postJson('/api/weather-observations', [
            'plot_id' => $plot->id,
            'observed_date' => now()->addDay()->toDateString(),
            'precipitation' => 10,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('observed_date');
    }

    public function test_store_rejects_plot_from_another_cooperative(): void
    {
        [$manager] = $this->makeCooperativeManager();
        $foreignPlot = Plot::factory()->create();

        $response = $this->actingAs($manager)->postJson('/api/weather-observations', [
            'plot_id' => $foreignPlot->id,
            'observed_date' => '2026-08-20',
            'precipitation' => 15,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('plot_id');
    }

    public function test_store_creates_observation_with_valid_data(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);

        $response = $this->actingAs($manager)->postJson('/api/weather-observations', [
            'plot_id' => $plot->id,
            'observed_date' => '2026-08-20',
            'temperature' => 24.5,
            'precipitation' => 12.3,
            'humidity' => 78,
            'source' => 'relevé terrain',
        ]);

        $response->assertCreated()->assertJsonPath('data.plot_id', $plot->id);
        $this->assertDatabaseCount('weather_observations', 1);
    }

    public function test_farmer_cannot_create_observation(): void
    {
        [$user, $farmer, $coop] = $this->makeFarmerUser();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id]);

        $response = $this->actingAs($user)->postJson('/api/weather-observations', [
            'plot_id' => $plot->id,
            'observed_date' => '2026-08-20',
            'precipitation' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_index_scopes_by_cooperative(): void
    {
        [$manager, $ownCoop] = $this->makeCooperativeManager();
        $ownPlot = Plot::factory()->create(['cooperative_id' => $ownCoop->id]);
        WeatherObservation::factory()->create(['plot_id' => $ownPlot->id]);

        [, $otherCoop] = $this->makeCooperativeManager();
        $otherPlot = Plot::factory()->create(['cooperative_id' => $otherCoop->id]);
        WeatherObservation::factory()->create(['plot_id' => $otherPlot->id]);

        $response = $this->actingAs($manager)->getJson('/api/weather-observations')->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_show_and_update_and_delete(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $observation = WeatherObservation::factory()->create(['plot_id' => $plot->id, 'precipitation' => 5]);

        $this->actingAs($manager)->getJson("/api/weather-observations/{$observation->id}")->assertOk();

        $this->actingAs($manager)
            ->putJson("/api/weather-observations/{$observation->id}", ['precipitation' => 20])
            ->assertOk()
            ->assertJsonPath('data.precipitation', 20);

        $this->actingAs($manager)->deleteJson("/api/weather-observations/{$observation->id}")->assertNoContent();
        $this->assertDatabaseMissing('weather_observations', ['id' => $observation->id]);
    }
}

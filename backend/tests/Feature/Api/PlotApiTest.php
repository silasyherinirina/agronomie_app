<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\CropCycle;
use App\Models\Plot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class PlotApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    private function validPolygon(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [47.50, -18.88],
                [47.51, -18.88],
                [47.51, -18.87],
                [47.50, -18.87],
                [47.50, -18.88],
            ]],
        ];
    }

    public function test_store_requires_geometry(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->postJson('/api/plots', ['name' => 'Sans géométrie'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('geometry');
    }

    public function test_store_rejects_non_polygon_geometry_type(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->postJson('/api/plots', [
            'name' => 'Type invalide',
            'geometry' => ['type' => 'Point', 'coordinates' => [47.5, -18.8]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('geometry.type');
    }

    public function test_index_filters_by_cooperative_id(): void
    {
        [$admin] = [$this->makeAdmin()];
        [, $coopA] = $this->makeCooperativeManager();
        [, $coopB] = $this->makeCooperativeManager();
        Plot::factory()->count(2)->create(['cooperative_id' => $coopA->id]);
        Plot::factory()->count(3)->create(['cooperative_id' => $coopB->id]);

        $this->actingAs($admin)->getJson("/api/plots?cooperative_id={$coopA->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_geometry_round_trips_correctly_in_json_response(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $response = $this->actingAs($manager)->postJson('/api/plots', [
            'name' => 'Parcelle géométrie',
            'geometry' => $this->validPolygon(),
        ])->assertCreated();

        $response->assertJsonPath('data.geometry.type', 'Polygon');
        $this->assertCount(1, $response->json('data.geometry.coordinates'));
    }

    public function test_destroy_soft_deletes(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);

        $this->actingAs($manager)->deleteJson("/api/plots/{$plot->id}")->assertNoContent();

        $this->assertSoftDeleted('plots', ['id' => $plot->id]);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->getJson('/api/plots/999999')->assertNotFound();
    }

    // --- GET /plots/{plot}/campaigns (filtre Dashboard : Parcelle -> Campagne) -------------

    public function test_plot_campaigns_returns_only_campaigns_linked_via_its_crop_cycles(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $linked = AgriculturalCampaign::factory()->create(['year' => 2026]);
        $unrelated = AgriculturalCampaign::factory()->create(['year' => 2025]);
        CropCycle::factory()->create(['plot_id' => $plot->id, 'campaign_id' => $linked->id]);

        $response = $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/campaigns")->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($linked->id));
        $this->assertFalse($ids->contains($unrelated->id));
    }

    public function test_plot_campaigns_deduplicates_a_campaign_shared_by_several_crop_cycles(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $campaign = AgriculturalCampaign::factory()->create();
        // Trois cycles culturaux (riz, maïs, haricot) rattachés à la MÊME campagne.
        CropCycle::factory()->count(3)->create(['plot_id' => $plot->id, 'campaign_id' => $campaign->id]);

        $response = $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/campaigns")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($campaign->id, $response->json('data.0.id'));
    }

    public function test_plot_campaigns_is_empty_when_the_plot_has_no_crop_cycle(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        AgriculturalCampaign::factory()->create();

        $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/campaigns")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_cooperative_manager_cannot_read_campaigns_of_another_cooperatives_plot(): void
    {
        [$manager] = $this->makeCooperativeManager();
        [, $otherCoop] = $this->makeCooperativeManager();
        $foreignPlot = Plot::factory()->create(['cooperative_id' => $otherCoop->id]);

        $this->actingAs($manager)->getJson("/api/plots/{$foreignPlot->id}/campaigns")->assertForbidden();
    }

    public function test_guest_cannot_read_plot_campaigns(): void
    {
        $plot = Plot::factory()->create();

        $this->getJson("/api/plots/{$plot->id}/campaigns")->assertUnauthorized();
    }
}

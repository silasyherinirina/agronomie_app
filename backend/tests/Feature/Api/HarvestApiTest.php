<?php

namespace Tests\Feature\Api;

use App\Models\CropCycle;
use App\Models\Plot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class HarvestApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_declared_yield_requires_yield_value(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'area_ha' => 5]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);

        $this->actingAs($manager)->postJson('/api/harvests', [
            'crop_cycle_id' => $cycle->id,
            'harvest_date' => now()->toDateString(),
            'quantity' => 1000,
            'unit' => 'kg',
            'yield_source' => 'declared',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('yield_value');
    }

    public function test_declared_yield_value_is_kept_as_is(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'area_ha' => 5]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);

        $response = $this->actingAs($manager)->postJson('/api/harvests', [
            'crop_cycle_id' => $cycle->id,
            'harvest_date' => now()->toDateString(),
            'quantity' => 1000,
            'unit' => 'kg',
            'yield_source' => 'declared',
            'yield_value' => 3333,
        ])->assertCreated();

        $this->assertEquals(3333.0, $response->json('data.yield_value'));
    }

    public function test_calculated_yield_is_null_when_plot_area_unknown(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'area_ha' => null]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);

        $response = $this->actingAs($manager)->postJson('/api/harvests', [
            'crop_cycle_id' => $cycle->id,
            'harvest_date' => now()->toDateString(),
            'quantity' => 1000,
            'unit' => 'kg',
        ])->assertCreated();

        $this->assertNull($response->json('data.yield_value'));
    }

    public function test_quantity_must_be_non_negative(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);

        $this->actingAs($manager)->postJson('/api/harvests', [
            'crop_cycle_id' => $cycle->id,
            'harvest_date' => now()->toDateString(),
            'quantity' => -5,
            'unit' => 'kg',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_index_filters_by_farmer_id_and_cooperative_id(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        [$farmerUser, $farmer] = $this->makeFarmerUser($coop);

        $ownPlot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id]);
        $otherPlot = Plot::factory()->create(['cooperative_id' => $coop->id]);

        $ownCycle = CropCycle::factory()->create(['plot_id' => $ownPlot->id]);
        $otherCycle = CropCycle::factory()->create(['plot_id' => $otherPlot->id]);

        \App\Models\Harvest::factory()->create(['crop_cycle_id' => $ownCycle->id]);
        \App\Models\Harvest::factory()->create(['crop_cycle_id' => $otherCycle->id]);

        $byFarmer = $this->actingAs($manager)->getJson("/api/harvests?farmer_id={$farmer->id}")->assertOk();
        $byFarmer->assertJsonCount(1, 'data');

        $byCoop = $this->actingAs($manager)->getJson("/api/harvests?cooperative_id={$coop->id}")->assertOk();
        $byCoop->assertJsonCount(2, 'data');

        $this->assertNotNull($farmerUser);
    }
}

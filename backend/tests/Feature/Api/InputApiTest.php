<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\Farmer;
use App\Models\Input;
use App\Models\Plot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class InputApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_store_rejects_invalid_type(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->postJson('/api/inputs', [
            'name' => 'Produit X',
            'type' => 'invalide',
            'unit' => 'kg',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_index_filters_by_type(): void
    {
        Input::factory()->create(['type' => 'engrais']);
        Input::factory()->create(['type' => 'semence']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/inputs?type=engrais')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_usage_rejects_farmer_from_another_cooperative(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $input = Input::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();
        $foreignFarmer = Farmer::factory()->create(); // autre coopérative

        $this->actingAs($manager)->postJson('/api/input-usages', [
            'input_id' => $input->id,
            'plot_id' => $plot->id,
            'campaign_id' => $campaign->id,
            'farmer_id' => $foreignFarmer->id,
            'quantity' => 10,
            'date' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('farmer_id');
    }

    public function test_usage_requires_quantity_and_date(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $input = Input::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();

        $this->actingAs($manager)->postJson('/api/input-usages', [
            'input_id' => $input->id,
            'plot_id' => $plot->id,
            'campaign_id' => $campaign->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'date']);
    }
}

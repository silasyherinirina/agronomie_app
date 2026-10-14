<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class AgriculturalCampaignApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_end_date_must_be_after_or_equal_start_date(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->postJson('/api/agricultural-campaigns', [
            'name' => 'Campagne test',
            'year' => 2026,
            'start_date' => '2026-10-01',
            'end_date' => '2026-01-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_index_filters_by_year(): void
    {
        AgriculturalCampaign::factory()->create(['year' => 2025]);
        AgriculturalCampaign::factory()->create(['year' => 2026]);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/agricultural-campaigns?year=2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.year', 2026);
    }

    public function test_non_admin_cannot_create_campaign(): void
    {
        $manager = $this->makeUser('cooperative_manager');

        $this->actingAs($manager)->postJson('/api/agricultural-campaigns', [
            'name' => 'Campagne test',
            'year' => 2026,
            'start_date' => '2026-01-01',
        ])->assertForbidden();
    }
}

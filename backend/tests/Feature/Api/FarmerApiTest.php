<?php

namespace Tests\Feature\Api;

use App\Models\Farmer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class FarmerApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_index_is_scoped_to_managers_own_cooperative(): void
    {
        [$manager, $ownCoop] = $this->makeCooperativeManager();
        Farmer::factory()->count(2)->create(['cooperative_id' => $ownCoop->id]);
        Farmer::factory()->count(5)->create(); // autres coopératives

        $this->actingAs($manager)->getJson('/api/farmers')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_requires_first_and_last_name(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->postJson('/api/farmers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_update_partial_fields_only(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $farmer = Farmer::factory()->create(['cooperative_id' => $coop->id, 'first_name' => 'Avant']);

        $this->actingAs($manager)->putJson("/api/farmers/{$farmer->id}", ['first_name' => 'Après'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Après')
            ->assertJsonPath('data.last_name', $farmer->last_name);
    }

    public function test_destroy_soft_deletes(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $farmer = Farmer::factory()->create(['cooperative_id' => $coop->id]);

        $this->actingAs($manager)->deleteJson("/api/farmers/{$farmer->id}")->assertNoContent();

        $this->assertSoftDeleted('farmers', ['id' => $farmer->id]);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->getJson('/api/farmers/999999')->assertNotFound();
    }
}

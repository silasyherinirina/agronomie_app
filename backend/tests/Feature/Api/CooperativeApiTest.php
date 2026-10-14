<?php

namespace Tests\Feature\Api;

use App\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class CooperativeApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_index_returns_paginated_envelope(): void
    {
        Cooperative::factory()->count(3)->create();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/cooperatives?per_page=2')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_per_page_is_capped_regardless_of_client_request(): void
    {
        Cooperative::factory()->count(3)->create();
        $admin = $this->makeAdmin();

        // Phase 13 — sécurité/performance : un client ne peut pas forcer une page de taille
        // arbitraire (Controller::perPage()).
        $this->actingAs($admin)->getJson('/api/cooperatives?per_page=999999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_index_search_filters_by_name(): void
    {
        Cooperative::factory()->create(['name' => 'Coopérative Vontovorona']);
        Cooperative::factory()->create(['name' => 'Coopérative Ambohimanga']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/cooperatives?search=Vontovorona')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Coopérative Vontovorona');
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/cooperatives/999999')->assertNotFound();
    }

    public function test_store_requires_name_and_region(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->postJson('/api/cooperatives', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'region']);
    }

    public function test_update_rejects_invalid_status(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();

        $this->actingAs($manager)->putJson("/api/cooperatives/{$coop->id}", ['status' => 'invalide'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_destroy_soft_deletes_and_hides_from_default_listing(): void
    {
        $admin = $this->makeAdmin();
        $coop = Cooperative::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/cooperatives/{$coop->id}")->assertNoContent();

        $this->assertSoftDeleted('cooperatives', ['id' => $coop->id]);
        $this->actingAs($admin)->getJson('/api/cooperatives')->assertJsonMissing(['id' => $coop->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/cooperatives')->assertUnauthorized();
    }
}

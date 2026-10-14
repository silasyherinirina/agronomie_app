<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\CropVariety;
use App\Models\Farmer;
use App\Models\Plot;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

/**
 * Module M — Ventes agricoles (Phase 6 : tests API).
 */
class SaleApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'sale_date' => now()->toDateString(),
            'quantity' => 1500,
            'unit' => 'kg',
            'unit_price' => 2500,
            'buyer_name' => 'Grossiste Andraharo',
        ], $overrides);
    }

    public function test_total_amount_is_always_recomputed_by_the_backend(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $response = $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'quantity' => 1500,
            'unit_price' => 2500,
            // Tentative de falsification : le backend doit l'ignorer et recalculer 1500*2500.
            'total_amount' => 1,
        ]))->assertCreated();

        $this->assertEquals(3_750_000.0, $response->json('data.total_amount'));
        $this->assertEquals('MGA', $response->json('data.currency'));
    }

    public function test_quantity_must_be_positive(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload(['quantity' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_unit_price_must_not_be_negative(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload(['unit_price' => -10]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unit_price');
    }

    public function test_sale_date_is_required(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload(['sale_date' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sale_date');
    }

    public function test_cooperative_manager_cannot_assign_a_sale_to_another_cooperative(): void
    {
        [$manager] = $this->makeCooperativeManager();
        [, $otherCoop] = $this->makeCooperativeManager();

        $response = $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'cooperative_id' => $otherCoop->id,
        ]))->assertCreated();

        // La valeur envoyée est silencieusement ignorée : la vente appartient à SA coopérative.
        $this->assertNotEquals($otherCoop->id, $response->json('data.cooperative_id'));
    }

    public function test_admin_must_specify_a_cooperative_id(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->postJson('/api/sales', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cooperative_id');
    }

    /** §26 : une parcelle d'une autre coopérative ne peut jamais être associée à cette vente. */
    public function test_plot_from_another_cooperative_is_rejected(): void
    {
        [$manager] = $this->makeCooperativeManager();
        $foreignPlot = Plot::factory()->create(); // autre coopérative (factory par défaut)

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'plot_id' => $foreignPlot->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('plot_id');
    }

    /** §26 : un agriculteur d'une autre coopérative ne peut jamais être associé à cette vente. */
    public function test_farmer_from_another_cooperative_is_rejected(): void
    {
        [$manager] = $this->makeCooperativeManager();
        [, $foreignFarmer] = $this->makeFarmerUser();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'farmer_id' => $foreignFarmer->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('farmer_id');
    }

    /** §26 : un cycle cultural incohérent (culture différente de celle déclarée) est rejeté. */
    public function test_crop_cycle_inconsistent_with_explicit_crop_is_rejected(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $cropA = Crop::factory()->create();
        $cropB = Crop::factory()->create();
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id, 'crop_id' => $cropA->id]);

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
            'crop_id' => $cropB->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('crop_id');
    }

    public function test_crop_cycle_auto_fills_a_consistent_sale(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);

        $response = $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
            'plot_id' => $plot->id,
            'crop_id' => $cycle->crop_id,
        ]))->assertCreated();

        $this->assertEquals($cycle->id, $response->json('data.crop_cycle_id'));
    }

    /**
     * §21/§23 : un cycle cultural dont la PARCELLE appartient à une autre coopérative doit être
     * rejeté — pas seulement une incohérence de culture explicite (cas déjà couvert ci-dessus).
     */
    public function test_crop_cycle_belonging_to_another_cooperatives_plot_is_rejected(): void
    {
        [$manager] = $this->makeCooperativeManager();
        $foreignPlot = Plot::factory()->create(); // autre coopérative (factory par défaut)
        $foreignCycle = CropCycle::factory()->create(['plot_id' => $foreignPlot->id]);

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $foreignCycle->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('crop_cycle_id');
    }

    /** §23 : coopérative A + parcelle A (valide) + agriculteur B (invalide) — rejeté sur farmer_id. */
    public function test_valid_plot_with_foreign_farmer_is_rejected(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => null]);
        [, $foreignFarmer] = $this->makeFarmerUser();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'plot_id' => $plot->id,
            'farmer_id' => $foreignFarmer->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('farmer_id');
    }

    public function test_farmer_can_only_view_their_own_sales(): void
    {
        [$farmerUser, $farmer, $coop] = $this->makeFarmerUser();
        Sale::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id]);
        Sale::factory()->create(['cooperative_id' => $coop->id]); // vente d'un autre agriculteur

        $response = $this->actingAs($farmerUser)->getJson('/api/sales')->assertOk();

        $response->assertJsonCount(1, 'data');
    }

    public function test_cooperative_manager_only_sees_their_cooperative_sales(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        [, $otherCoop] = $this->makeCooperativeManager();
        Sale::factory()->count(2)->create(['cooperative_id' => $coop->id]);
        Sale::factory()->create(['cooperative_id' => $otherCoop->id]);

        $response = $this->actingAs($manager)->getJson('/api/sales')->assertOk();

        $response->assertJsonCount(2, 'data');
    }

    public function test_farmer_cannot_create_a_sale(): void
    {
        [$farmerUser] = $this->makeFarmerUser();

        $this->actingAs($farmerUser)->postJson('/api/sales', $this->validPayload())
            ->assertForbidden();
    }

    public function test_cooperative_manager_cannot_update_a_sale_from_another_cooperative(): void
    {
        [$manager] = $this->makeCooperativeManager();
        [, $otherCoop] = $this->makeCooperativeManager();
        $sale = Sale::factory()->create(['cooperative_id' => $otherCoop->id]);

        $this->actingAs($manager)->putJson("/api/sales/{$sale->id}", ['quantity' => 10])
            ->assertForbidden();
    }

    public function test_updating_quantity_recomputes_total_amount(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $sale = Sale::factory()->create(['cooperative_id' => $coop->id, 'quantity' => 100, 'unit_price' => 10, 'total_amount' => 1000]);

        $response = $this->actingAs($manager)->putJson("/api/sales/{$sale->id}", ['quantity' => 200])
            ->assertOk();

        $this->assertEquals(2000.0, $response->json('data.total_amount'));
    }

    public function test_statistics_are_scoped_and_grouped_by_unit(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        Sale::factory()->create(['cooperative_id' => $coop->id, 'quantity' => 100, 'unit' => 'kg', 'unit_price' => 10, 'total_amount' => 1000]);
        Sale::factory()->create(['cooperative_id' => $coop->id, 'quantity' => 5, 'unit' => 'tonne', 'unit_price' => 100, 'total_amount' => 500]);
        [, $otherCoop] = $this->makeCooperativeManager();
        Sale::factory()->create(['cooperative_id' => $otherCoop->id, 'total_amount' => 999_999]);

        $response = $this->actingAs($manager)->getJson('/api/sales/statistics')->assertOk();

        $this->assertEquals(2, $response->json('data.sales_count'));
        $this->assertEquals(1500.0, $response->json('data.total_amount'));
        $byUnit = collect($response->json('data.quantity_by_unit'))->keyBy('unit');
        $this->assertEquals(100.0, $byUnit['kg']['total_quantity']);
        $this->assertEquals(5.0, $byUnit['tonne']['total_quantity']);
    }

    public function test_analyst_can_view_but_not_create_sales(): void
    {
        $analyst = $this->makeAnalyst();
        Sale::factory()->create();

        $this->actingAs($analyst)->getJson('/api/sales')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($analyst)->postJson('/api/sales', $this->validPayload())->assertForbidden();
    }

    public function test_filters_by_campaign_and_period(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $campaign = AgriculturalCampaign::factory()->create();
        Sale::factory()->create(['cooperative_id' => $coop->id, 'campaign_id' => $campaign->id, 'sale_date' => '2026-05-15']);
        Sale::factory()->create(['cooperative_id' => $coop->id, 'sale_date' => '2026-01-01']);

        $byCampaign = $this->actingAs($manager)->getJson("/api/sales?campaign_id={$campaign->id}")->assertOk();
        $byCampaign->assertJsonCount(1, 'data');

        $byPeriod = $this->actingAs($manager)->getJson('/api/sales?start_date=2026-05-01&end_date=2026-05-31')->assertOk();
        $byPeriod->assertJsonCount(1, 'data');
    }

    /*
     * ================================================================================
     * Restructuration "cycle cultural = relation racine" (§27) — matrice de tests exigée.
     * ================================================================================
     */

    /**
     * Fixture représentative du cas réel audité (Coopérative Démo Bongolava 01, id=59) : un
     * cycle avec parcelle, culture, variété, agriculteur (via la parcelle) et campagne tous
     * réellement rattachés à la même coopérative.
     *
     * @return array{0: \App\Models\User, 1: \App\Models\Cooperative, 2: CropCycle}
     */
    private function makeCompleteCycleFixture(): array
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $farmer = Farmer::factory()->create(['cooperative_id' => $coop->id]);
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id]);
        $crop = Crop::factory()->create();
        $variety = CropVariety::factory()->create(['crop_id' => $crop->id]);
        $campaign = AgriculturalCampaign::factory()->create();
        $cycle = CropCycle::factory()->create([
            'plot_id' => $plot->id, 'crop_id' => $crop->id, 'variety_id' => $variety->id, 'campaign_id' => $campaign->id,
        ]);

        return [$manager, $coop, $cycle];
    }

    public function test_cycle_root_valid_cycle_is_accepted_and_derives_the_full_chain(): void
    {
        [$manager, $coop, $cycle] = $this->makeCompleteCycleFixture();

        $response = $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
        ]))->assertCreated();

        // Toute la chaîne est dérivée du cycle, sans que le client n'ait rien envoyé d'autre.
        $response->assertJsonPath('data.crop_cycle_id', $cycle->id)
            ->assertJsonPath('data.plot_id', $cycle->plot_id)
            ->assertJsonPath('data.crop_id', $cycle->crop_id)
            ->assertJsonPath('data.variety_id', $cycle->variety_id)
            ->assertJsonPath('data.campaign_id', $cycle->campaign_id)
            ->assertJsonPath('data.farmer_id', $cycle->plot->farmer_id)
            ->assertJsonPath('data.cooperative_id', $coop->id);
    }

    public function test_cycle_root_wrong_plot_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $wrongPlot = Plot::factory()->create(['cooperative_id' => $cycle->plot->cooperative_id]);

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
            'plot_id' => $wrongPlot->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('plot_id');
    }

    public function test_cycle_root_wrong_crop_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $wrongCrop = Crop::factory()->create();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
            'crop_id' => $wrongCrop->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('crop_id');
    }

    public function test_cycle_root_wrong_variety_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $wrongVariety = CropVariety::factory()->create();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
            'variety_id' => $wrongVariety->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('variety_id');
    }

    public function test_cycle_root_wrong_farmer_is_rejected(): void
    {
        [$manager, $coop, $cycle] = $this->makeCompleteCycleFixture();
        $wrongFarmer = Farmer::factory()->create(['cooperative_id' => $coop->id]);

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
            'farmer_id' => $wrongFarmer->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('farmer_id');
    }

    public function test_cycle_root_wrong_cooperative_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $otherCoop = \App\Models\Cooperative::factory()->create();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
            'cooperative_id' => $otherCoop->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('cooperative_id');
    }

    public function test_cycle_root_wrong_campaign_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $wrongCampaign = AgriculturalCampaign::factory()->create();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
            'campaign_id' => $wrongCampaign->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('campaign_id');
    }

    /** §14 : un admin peut créer une vente via un cycle sans jamais préciser cooperative_id lui-même — dérivé du cycle. */
    public function test_cycle_root_admin_does_not_need_to_specify_cooperative_id(): void
    {
        [, $coop, $cycle] = $this->makeCompleteCycleFixture();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $cycle->id,
        ]))->assertCreated();

        $response->assertJsonPath('data.cooperative_id', $coop->id);
    }

    /** §8/§21 : un cooperative_manager ne peut pas utiliser un cycle d'une autre coopérative, même en essayant de l'associer à sa propre coopérative. */
    public function test_cycle_root_manager_cannot_use_a_cycle_from_another_cooperative(): void
    {
        [$manager] = $this->makeCooperativeManager();
        [, , $foreignCycle] = $this->makeCompleteCycleFixture();

        $this->actingAs($manager)->postJson('/api/sales', $this->validPayload([
            'crop_cycle_id' => $foreignCycle->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('crop_cycle_id');
    }
}

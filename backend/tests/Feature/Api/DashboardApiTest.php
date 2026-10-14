<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Input;
use App\Models\InputUsage;
use App\Models\Plot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_admin_sees_global_kpis_and_unit_grouped_totals(): void
    {
        [$managerA, $coopA] = $this->makeCooperativeManager();
        [, $coopB] = $this->makeCooperativeManager();

        $plotA1 = Plot::factory()->create(['cooperative_id' => $coopA->id, 'area_ha' => 2]);
        $plotA2 = Plot::factory()->create(['cooperative_id' => $coopA->id, 'area_ha' => 3]);
        Plot::factory()->create(['cooperative_id' => $coopB->id, 'area_ha' => 10]);

        $rice = Crop::factory()->create(['name' => 'Riz']);
        $campaign = AgriculturalCampaign::factory()->create(['year' => 2026]);

        $cycle1 = CropCycle::factory()->create(['plot_id' => $plotA1->id, 'crop_id' => $rice->id, 'campaign_id' => $campaign->id]);
        $cycle2 = CropCycle::factory()->create(['plot_id' => $plotA2->id, 'crop_id' => $rice->id, 'campaign_id' => $campaign->id]);

        // Deux récoltes en kg pour la même culture : doivent être sommées ensemble (2000).
        Harvest::factory()->create(['crop_cycle_id' => $cycle1->id, 'quantity' => 1200, 'unit' => 'kg', 'yield_value' => 600]);
        Harvest::factory()->create(['crop_cycle_id' => $cycle2->id, 'quantity' => 800, 'unit' => 'kg', 'yield_value' => 267]);
        // Une récolte en "sac" : ne doit JAMAIS être additionnée aux kg (unités hétérogènes).
        Harvest::factory()->create(['crop_cycle_id' => $cycle1->id, 'quantity' => 50, 'unit' => 'sac', 'yield_value' => null]);

        $urea = Input::factory()->create(['name' => 'Urée', 'type' => 'engrais', 'unit' => 'kg']);
        InputUsage::factory()->create(['input_id' => $urea->id, 'plot_id' => $plotA1->id, 'campaign_id' => $campaign->id, 'quantity' => 30, 'cost' => 50000]);
        InputUsage::factory()->create(['input_id' => $urea->id, 'plot_id' => $plotA2->id, 'campaign_id' => $campaign->id, 'quantity' => 20, 'cost' => 30000]);

        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->getJson('/api/dashboard')->assertOk();

        $response->assertJsonPath('data.kpis.cooperatives_count', 2)
            ->assertJsonPath('data.kpis.plots_count', 3);
        // PHP sérialise un float "rond" (15.0) en JSON comme 15 sans JSON_PRESERVE_ZERO_FRACTION
        // (comportement normal, sans impact applicatif) : comparaison numérique plutôt que stricte.
        $this->assertEquals(15.0, $response->json('data.kpis.total_area_ha'));

        $kgTotal = collect($response->json('data.harvest_totals_by_unit'))->firstWhere('unit', 'kg');
        $this->assertEquals(2000.0, $kgTotal['total_quantity']);
        $this->assertSame(2, $kgTotal['harvests_count']);

        $sacTotal = collect($response->json('data.harvest_totals_by_unit'))->firstWhere('unit', 'sac');
        $this->assertEquals(50.0, $sacTotal['total_quantity']);

        $riceProduction = collect($response->json('data.production_by_crop'))->firstWhere('unit', 'kg');
        $this->assertSame('Riz', $riceProduction['crop_name']);
        $this->assertEquals(2000.0, $riceProduction['total_quantity']);

        $ureaConsumption = collect($response->json('data.input_consumption'))->first();
        $this->assertEquals(50.0, $ureaConsumption['total_quantity']);
        $this->assertEquals(80000.0, $ureaConsumption['total_cost']);

        // $managerA n'est pas utilisé directement mais garantit que coopA a bien un responsable.
        $this->assertNotNull($managerA);
    }

    public function test_cooperative_manager_scope_cannot_be_overridden_by_query_param(): void
    {
        [$manager, $ownCoop] = $this->makeCooperativeManager();
        [, $otherCoop] = $this->makeCooperativeManager();

        Plot::factory()->create(['cooperative_id' => $ownCoop->id, 'area_ha' => 4]);
        Plot::factory()->create(['cooperative_id' => $otherCoop->id, 'area_ha' => 99]);

        // Tente de regarder les données de l'autre coopérative : doit être ignoré.
        $response = $this->actingAs($manager)->getJson("/api/dashboard?cooperative_id={$otherCoop->id}")->assertOk();

        $response->assertJsonPath('data.filters.cooperative_id', $ownCoop->id)
            ->assertJsonPath('data.kpis.plots_count', 1);
        $this->assertEquals(4.0, $response->json('data.kpis.total_area_ha'));
    }

    public function test_farmer_sees_only_own_plots_and_no_global_counts(): void
    {
        [$user, $farmer, $coop] = $this->makeFarmerUser();
        $ownPlot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id, 'area_ha' => 1.5]);
        Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => null, 'area_ha' => 20]);

        $response = $this->actingAs($user)->getJson('/api/dashboard')->assertOk();

        $response->assertJsonPath('data.kpis.cooperatives_count', null)
            ->assertJsonPath('data.kpis.farmers_count', null)
            ->assertJsonPath('data.kpis.plots_count', 1)
            ->assertJsonPath('data.kpis.total_area_ha', 1.5);

        $this->assertNotNull($ownPlot);
    }

    public function test_campaign_filter_restricts_harvest_aggregates(): void
    {
        $admin = $this->makeAdmin();
        $plot = Plot::factory()->create(['area_ha' => 1]);
        $campaign2025 = AgriculturalCampaign::factory()->create(['year' => 2025]);
        $campaign2026 = AgriculturalCampaign::factory()->create(['year' => 2026]);

        $cycle2025 = CropCycle::factory()->create(['plot_id' => $plot->id, 'campaign_id' => $campaign2025->id]);
        $cycle2026 = CropCycle::factory()->create(['plot_id' => $plot->id, 'campaign_id' => $campaign2026->id]);

        Harvest::factory()->create(['crop_cycle_id' => $cycle2025->id, 'quantity' => 100, 'unit' => 'kg']);
        Harvest::factory()->create(['crop_cycle_id' => $cycle2026->id, 'quantity' => 400, 'unit' => 'kg']);

        $response = $this->actingAs($admin)->getJson("/api/dashboard?campaign_id={$campaign2026->id}")->assertOk();

        $kgTotal = collect($response->json('data.harvest_totals_by_unit'))->firstWhere('unit', 'kg');
        $this->assertEquals(400.0, $kgTotal['total_quantity']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_recent_activity_lists_real_events_sorted_by_date(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $oldPlot = Plot::factory()->create(['cooperative_id' => $coop->id, 'name' => 'Parcelle Nord', 'created_at' => now()->subDays(5)]);
        $oldFarmer = Farmer::factory()->create(['cooperative_id' => $coop->id, 'created_at' => now()->subDays(3)]);
        $newPlot = Plot::factory()->create(['cooperative_id' => $coop->id, 'name' => 'Parcelle Sud', 'created_at' => now()->subHour()]);

        $response = $this->actingAs($manager)->getJson('/api/dashboard')->assertOk();

        $activity = $response->json('data.recent_activity');
        $this->assertNotEmpty($activity);
        // Le plus récent (Parcelle Sud, il y a 1h) doit précéder les évènements plus anciens.
        $this->assertSame('plot_created', $activity[0]['type']);
        $this->assertStringContainsString('Parcelle Sud', $activity[0]['description']);

        $this->assertNotNull($oldFarmer);
        $this->assertNotNull($newPlot);
        $this->assertNotNull($oldPlot);
    }

    public function test_alerts_flag_active_plots_without_an_in_progress_cycle(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $idlePlot = Plot::factory()->create(['cooperative_id' => $coop->id, 'status' => 'active', 'name' => 'Parcelle Inactive']);
        $workingPlot = Plot::factory()->create(['cooperative_id' => $coop->id, 'status' => 'active', 'name' => 'Parcelle Active']);
        CropCycle::factory()->create(['plot_id' => $workingPlot->id, 'status' => 'in_progress']);

        $response = $this->actingAs($manager)->getJson('/api/dashboard')->assertOk();

        $alerts = collect($response->json('data.alerts'));
        $this->assertTrue($alerts->contains(fn ($a) => str_contains($a['message'], 'Parcelle Inactive')));
        $this->assertFalse($alerts->contains(fn ($a) => str_contains($a['message'], 'Parcelle Active')));
    }

    // --- Filtre Parcelle (plot_id) : Parcelle -> Coopérative + Campagne --------------------

    public function test_plot_id_absent_keeps_the_current_global_behavior(): void
    {
        $admin = $this->makeAdmin();
        Plot::factory()->count(2)->create(['area_ha' => 5]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard')->assertOk();

        $this->assertNull($response->json('data.filters.plot_id'));
        $this->assertEquals(10.0, $response->json('data.kpis.total_area_ha'));
    }

    public function test_selecting_a_plot_scopes_kpis_and_harvests_to_that_plot_only(): void
    {
        $admin = $this->makeAdmin();
        $plotA = Plot::factory()->create(['area_ha' => 2]);
        $plotB = Plot::factory()->create(['area_ha' => 30]);
        $cycleA = CropCycle::factory()->create(['plot_id' => $plotA->id]);
        $cycleB = CropCycle::factory()->create(['plot_id' => $plotB->id]);
        Harvest::factory()->create(['crop_cycle_id' => $cycleA->id, 'quantity' => 100, 'unit' => 'kg']);
        Harvest::factory()->create(['crop_cycle_id' => $cycleB->id, 'quantity' => 900, 'unit' => 'kg']);

        $response = $this->actingAs($admin)->getJson("/api/dashboard?plot_id={$plotA->id}")->assertOk();

        $response->assertJsonPath('data.filters.plot_id', $plotA->id)
            ->assertJsonPath('data.kpis.plots_count', 1);
        $this->assertEquals(2.0, $response->json('data.kpis.total_area_ha'));

        $kgTotal = collect($response->json('data.harvest_totals_by_unit'))->firstWhere('unit', 'kg');
        $this->assertEquals(100.0, $kgTotal['total_quantity']);
    }

    public function test_cooperative_id_inconsistent_with_the_selected_plot_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        [, $realCoop] = $this->makeCooperativeManager();
        [, $otherCoop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $realCoop->id]);

        $this->actingAs($admin)
            ->getJson("/api/dashboard?plot_id={$plot->id}&cooperative_id={$otherCoop->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cooperative_id');
    }

    public function test_campaign_id_not_associated_with_the_plot_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $plot = Plot::factory()->create();
        $linkedCampaign = AgriculturalCampaign::factory()->create();
        $unrelatedCampaign = AgriculturalCampaign::factory()->create();
        CropCycle::factory()->create(['plot_id' => $plot->id, 'campaign_id' => $linkedCampaign->id]);

        $this->actingAs($admin)
            ->getJson("/api/dashboard?plot_id={$plot->id}&campaign_id={$unrelatedCampaign->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('campaign_id');
    }

    public function test_campaign_id_associated_with_the_plot_is_accepted(): void
    {
        $admin = $this->makeAdmin();
        $plot = Plot::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id, 'campaign_id' => $campaign->id]);
        Harvest::factory()->create(['crop_cycle_id' => $cycle->id, 'quantity' => 250, 'unit' => 'kg']);

        $response = $this->actingAs($admin)
            ->getJson("/api/dashboard?plot_id={$plot->id}&campaign_id={$campaign->id}")
            ->assertOk();

        $kgTotal = collect($response->json('data.harvest_totals_by_unit'))->firstWhere('unit', 'kg');
        $this->assertEquals(250.0, $kgTotal['total_quantity']);
    }

    public function test_a_farmer_cannot_use_a_plot_id_outside_their_own_scope(): void
    {
        [$user] = $this->makeFarmerUser();
        $foreignPlot = Plot::factory()->create();

        $this->actingAs($user)->getJson("/api/dashboard?plot_id={$foreignPlot->id}")->assertForbidden();
    }

    public function test_an_unknown_plot_id_returns_404(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/dashboard?plot_id=999999')->assertNotFound();
    }

    /**
     * Carte "Aperçu des parcelles" / section "Statistiques géographiques" : la géométrie et la
     * localisation d'une parcelle restent accessibles dès que `plot_id` est fourni, sans
     * qu'aucune campagne ne soit sélectionnée — c'est exactement le scénario qui ne
     * fonctionnait pas côté carte avant correction (le filtre Parcelle ne doit jamais dépendre
     * de campaign_id pour produire un résultat).
     */
    public function test_geography_is_scoped_to_the_selected_plot_without_any_campaign(): void
    {
        $admin = $this->makeAdmin();
        [, $coop] = $this->makeCooperativeManager();
        $plotA = Plot::factory()->create(['cooperative_id' => $coop->id, 'area_ha' => 3]);
        Plot::factory()->create(['cooperative_id' => $coop->id, 'area_ha' => 40]);

        $response = $this->actingAs($admin)->getJson("/api/dashboard?plot_id={$plotA->id}")->assertOk();

        $response->assertJsonPath('data.filters.plot_id', $plotA->id)
            ->assertJsonPath('data.filters.campaign_id', null);

        $geography = collect($response->json('data.geography'));
        $this->assertCount(1, $geography);
        $this->assertEquals(1, $geography->first()['plots_count']);
        $this->assertEquals(3.0, $geography->first()['total_area_ha']);
    }

    /**
     * Module M — Ventes agricoles (§27) : le chiffre d'affaires affiché au tableau de bord doit
     * être STRICTEMENT identique à celui renvoyé par GET /api/sales/statistics pour les mêmes
     * filtres — les deux réutilisent SaleService::statistics(), jamais un second calcul divergent.
     */
    public function test_dashboard_sales_figures_match_the_dedicated_statistics_endpoint(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        \App\Models\Sale::factory()->create(['cooperative_id' => $coop->id, 'quantity' => 1500, 'unit' => 'kg', 'unit_price' => 2500, 'total_amount' => 3_750_000]);
        \App\Models\Sale::factory()->create(['cooperative_id' => $coop->id, 'quantity' => 10, 'unit' => 'tonne', 'unit_price' => 100000, 'total_amount' => 1_000_000]);
        [, $otherCoop] = $this->makeCooperativeManager();
        \App\Models\Sale::factory()->create(['cooperative_id' => $otherCoop->id, 'total_amount' => 999_999]);

        $dashboard = $this->actingAs($manager)->getJson('/api/dashboard')->assertOk();
        $statistics = $this->actingAs($manager)->getJson('/api/sales/statistics')->assertOk();

        $this->assertEquals($statistics->json('data.total_amount'), $dashboard->json('data.sales.total_amount'));
        $this->assertEquals($statistics->json('data.sales_count'), $dashboard->json('data.sales.sales_count'));
        $this->assertEquals(4_750_000.0, $dashboard->json('data.sales.total_amount'));
    }
}

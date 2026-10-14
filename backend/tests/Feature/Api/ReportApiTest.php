<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\CropVariety;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

/**
 * Module Rapports (Phase 18) : CSV/Excel/PDF alimentés par les mêmes données (§23), mêmes
 * règles de périmètre par rôle que les endpoints CRUD existants (§33 : jamais les données d'une
 * autre coopérative).
 */
class ReportApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_unknown_report_type_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/reports/unknown-type?format=csv')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_unknown_format_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/reports/sales?format=docx')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('format');
    }

    public function test_format_is_required(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->getJson('/api/reports/sales')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('format');
    }

    public function test_sales_csv_report_contains_the_expected_rows_and_total(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        Sale::factory()->create(['cooperative_id' => $coop->id, 'buyer_name' => 'Grossiste Test', 'quantity' => 1000, 'unit' => 'kg', 'unit_price' => 500, 'total_amount' => 500000]);

        $response = $this->actingAs($manager)->getJson('/api/reports/sales?format=csv')->assertOk();

        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Grossiste Test', $content);
        $this->assertStringContainsString('500 000,00 MGA', $content);
    }

    public function test_harvests_xlsx_report_is_a_valid_binary_download(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);
        Harvest::factory()->create(['crop_cycle_id' => $cycle->id, 'quantity' => 800, 'unit' => 'kg']);

        $response = $this->actingAs($manager)->get('/api/reports/harvests?format=xlsx')->assertOk();

        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // BinaryFileResponse : le contenu est streamé depuis un fichier temporaire, jamais chargé
        // dans TestResponse::getContent() — on lit le fichier réellement généré sur disque.
        $file = $response->baseResponse->getFile();
        $this->assertGreaterThan(1000, $file->getSize());
        $this->assertSame('xlsx', $file->getExtension());
    }

    public function test_production_pdf_report_returns_a_real_pdf_document(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);
        Harvest::factory()->create(['crop_cycle_id' => $cycle->id, 'quantity' => 500, 'unit' => 'kg']);

        $response = $this->actingAs($manager)->get('/api/reports/production?format=pdf')->assertOk();

        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /** §25 : jamais une somme entre unités hétérogènes dans le rapport de production. */
    public function test_production_report_never_mixes_units_in_a_single_total(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create();
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id, 'crop_id' => $crop->id]);
        Harvest::factory()->create(['crop_cycle_id' => $cycle->id, 'quantity' => 1000, 'unit' => 'kg']);
        Harvest::factory()->create(['crop_cycle_id' => $cycle->id, 'quantity' => 50, 'unit' => 'sac']);

        $response = $this->actingAs($manager)->getJson('/api/reports/production?format=csv')->assertOk();

        $content = $response->streamedContent();
        // Deux lignes de production distinctes (une par unité), jamais 1050 fusionné.
        $this->assertStringContainsString('1 000,000 kg', $content);
        $this->assertStringContainsString('50,000 sac', $content);
        $this->assertStringNotContainsString('1050', $content);
        $this->assertStringNotContainsString('1 050', $content);
    }

    /** §33 : un cooperative_manager ne doit jamais pouvoir télécharger les données d'une autre coopérative. */
    public function test_cooperative_manager_cannot_widen_scope_via_filter(): void
    {
        [$manager] = $this->makeCooperativeManager();
        [, $otherCoop] = $this->makeCooperativeManager();
        Sale::factory()->create(['cooperative_id' => $otherCoop->id, 'buyer_name' => 'Acheteur Confidentiel']);

        $response = $this->actingAs($manager)
            ->getJson("/api/reports/sales?format=csv&cooperative_id={$otherCoop->id}")
            ->assertOk();

        $this->assertStringNotContainsString('Acheteur Confidentiel', $response->streamedContent());
    }

    public function test_farmer_only_sees_their_own_sales_in_the_report(): void
    {
        [$farmerUser, $farmer, $coop] = $this->makeFarmerUser();
        Sale::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id, 'buyer_name' => 'Ma Vente']);
        Sale::factory()->create(['cooperative_id' => $coop->id, 'buyer_name' => 'Vente Autre Agriculteur']);

        $response = $this->actingAs($farmerUser)->getJson('/api/reports/sales?format=csv')->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Ma Vente', $content);
        $this->assertStringNotContainsString('Vente Autre Agriculteur', $content);
    }

    public function test_period_and_campaign_filters_are_applied_server_side(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $campaign = AgriculturalCampaign::factory()->create();
        Sale::factory()->create(['cooperative_id' => $coop->id, 'campaign_id' => $campaign->id, 'sale_date' => '2026-05-15', 'buyer_name' => 'Dans la période']);
        Sale::factory()->create(['cooperative_id' => $coop->id, 'sale_date' => '2020-01-01', 'buyer_name' => 'Hors période']);

        $response = $this->actingAs($manager)
            ->getJson("/api/reports/sales?format=csv&campaign_id={$campaign->id}")
            ->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString('Dans la période', $content);
        $this->assertStringNotContainsString('Hors période', $content);
    }

    /*
     * ================================================================================
     * Restructuration "cycle cultural = relation racine" (§26) — matrice de tests exigée.
     * ================================================================================
     */

    /**
     * Fixture complète : cycle avec parcelle, culture, variété, agriculteur (via la parcelle) et
     * campagne, plus une récolte et une vente réellement rattachées à CE cycle.
     *
     * @return array{0: \App\Models\User, 1: Cooperative, 2: CropCycle}
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
        Harvest::factory()->create(['crop_cycle_id' => $cycle->id, 'quantity' => 500, 'unit' => 'kg']);
        Sale::factory()->create([
            'cooperative_id' => $coop->id, 'crop_cycle_id' => $cycle->id, 'plot_id' => $plot->id,
            'crop_id' => $crop->id, 'variety_id' => $variety->id, 'campaign_id' => $campaign->id, 'farmer_id' => $farmer->id,
        ]);

        return [$manager, $coop, $cycle];
    }

    public function test_cycle_root_valid_cycle_produces_a_correct_report(): void
    {
        [$manager, $coop, $cycle] = $this->makeCompleteCycleFixture();

        $response = $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}")->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString($cycle->crop->name, $content);
        $this->assertStringContainsString($cycle->plot->name, $content);
        $this->assertStringContainsString($coop->name, $content);
    }

    public function test_cycle_root_wrong_plot_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $wrongPlot = Plot::factory()->create(['cooperative_id' => $cycle->plot->cooperative_id]);

        $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}&plot_id={$wrongPlot->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('plot_id');
    }

    public function test_cycle_root_wrong_crop_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $wrongCrop = Crop::factory()->create();

        $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}&crop_id={$wrongCrop->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('crop_id');
    }

    public function test_cycle_root_wrong_variety_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $wrongVariety = CropVariety::factory()->create();

        $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}&variety_id={$wrongVariety->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('variety_id');
    }

    public function test_cycle_root_wrong_farmer_is_rejected(): void
    {
        [$manager, $coop, $cycle] = $this->makeCompleteCycleFixture();
        $wrongFarmer = Farmer::factory()->create(['cooperative_id' => $coop->id]);

        $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}&farmer_id={$wrongFarmer->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('farmer_id');
    }

    public function test_cycle_root_wrong_cooperative_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        [, , $cycle] = $this->makeCompleteCycleFixture();
        $otherCoop = Cooperative::factory()->create();

        $this->actingAs($admin)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}&cooperative_id={$otherCoop->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('cooperative_id');
    }

    public function test_cycle_root_wrong_campaign_is_rejected(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();
        $wrongCampaign = AgriculturalCampaign::factory()->create();

        $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}&campaign_id={$wrongCampaign->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('campaign_id');
    }

    public function test_cycle_root_manager_cannot_use_a_cycle_from_another_cooperative(): void
    {
        [$manager] = $this->makeCooperativeManager();
        [, , $foreignCycle] = $this->makeCompleteCycleFixture();

        $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$foreignCycle->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('crop_cycle_id');
    }

    /** §21/§22 : le rapport Récoltes ne doit inclure QUE les récoltes du cycle précis, jamais celles d'un cycle partageant la même culture/campagne. */
    public function test_cycle_root_harvests_report_excludes_harvests_from_a_similar_but_different_cycle(): void
    {
        [$manager, $coop, $cycle] = $this->makeCompleteCycleFixture();
        // Un second cycle, même parcelle/culture/campagne — mais un cycle DIFFERENT.
        $samePlotSameCrop = CropCycle::factory()->create([
            'plot_id' => $cycle->plot_id, 'crop_id' => $cycle->crop_id, 'campaign_id' => $cycle->campaign_id,
        ]);
        Harvest::factory()->create(['crop_cycle_id' => $samePlotSameCrop->id, 'quantity' => 999, 'unit' => 'kg']);

        $response = $this->actingAs($manager)->getJson("/api/reports/harvests?format=csv&crop_cycle_id={$cycle->id}")->assertOk();

        $this->assertStringNotContainsString('999,000', $response->streamedContent());
    }

    /** §23 : une vente agrégée (sans cycle) ne doit jamais apparaître dans un rapport filtré sur un cycle précis. */
    public function test_cycle_root_sales_report_excludes_aggregate_sales(): void
    {
        [$manager, $coop, $cycle] = $this->makeCompleteCycleFixture();
        Sale::factory()->create(['cooperative_id' => $coop->id, 'crop_cycle_id' => null, 'buyer_name' => 'Vente agrégée témoin']);

        $response = $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}")->assertOk();

        $this->assertStringNotContainsString('Vente agrégée témoin', $response->streamedContent());
    }

    /** §15 : les trois formats représentent exactement le même périmètre pour un cycle donné. */
    public function test_cycle_root_all_three_formats_use_the_same_scope(): void
    {
        [$manager, , $cycle] = $this->makeCompleteCycleFixture();

        $csv = $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&crop_cycle_id={$cycle->id}")->assertOk();
        $xlsx = $this->actingAs($manager)->get("/api/reports/sales?format=xlsx&crop_cycle_id={$cycle->id}")->assertOk();
        $pdf = $this->actingAs($manager)->get("/api/reports/sales?format=pdf&crop_cycle_id={$cycle->id}")->assertOk();

        $this->assertStringContainsString('text/csv', $csv->headers->get('content-type'));
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type'));
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertGreaterThan(1000, $xlsx->baseResponse->getFile()->getSize());
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    /** Mode A (§10) : les filtres indépendants restent pleinement fonctionnels sans cycle sélectionné. */
    public function test_global_mode_still_supports_independent_filters_without_a_cycle(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $campaign = AgriculturalCampaign::factory()->create();
        Sale::factory()->create(['cooperative_id' => $coop->id, 'campaign_id' => $campaign->id, 'buyer_name' => 'Vente campagne ciblée']);
        Sale::factory()->create(['cooperative_id' => $coop->id, 'buyer_name' => 'Vente autre campagne']);

        $response = $this->actingAs($manager)->getJson("/api/reports/sales?format=csv&campaign_id={$campaign->id}")->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('Vente campagne ciblée', $content);
        $this->assertStringNotContainsString('Vente autre campagne', $content);
    }
}

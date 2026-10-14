<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Input;
use App\Models\InputUsage;
use App\Models\ModelVersion;
use App\Models\Plot;
use App\Models\ProductionPrediction;
use App\Models\WeatherObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

/**
 * Phase 10 — Intégration IA. Le service FastAPI réel n'est pas démarré pendant les tests :
 * Http::fake() simule ses réponses, comme documenté pour tester une intégration HTTP externe
 * sans dépendre de sa disponibilité réseau.
 */
class PredictionApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    private function fakeAiPredictResponse(string $modelId = 'rice-yield-linear_regression-20260829-081454'): array
    {
        return [
            'predicted_yield' => 2365.4,
            'unit' => 'kg/ha',
            'model_id' => $modelId,
            'algorithm' => 'linear_regression',
            'model_version' => '1.0.0',
            'metrics' => ['mae' => 138.954, 'rmse' => 167.005, 'r2' => 0.5381],
            'predicted_at' => '2026-08-29T08:16:05.650080Z',
            'is_experimental' => true,
            'feature_importances' => ['area_ha' => 0.42, 'fertilizer_kg_per_ha' => 0.31, 'rainfall_mm' => 0.18, 'planting_month' => 0.06, 'variety_class_amelioree' => 0.03],
        ];
    }

    private function fakeAiModelResponse(string $modelId): array
    {
        return [
            'id' => $modelId,
            'name' => 'Rendement du riz (kg/ha)',
            'algorithm' => 'linear_regression',
            'version' => '1.0.0',
            'trained_at' => '2026-08-29T08:14:54.106105Z',
            'metrics' => ['mae' => 138.954, 'rmse' => 167.005, 'r2' => 0.5381, 'dataset_size' => 600],
            'is_active' => true,
        ];
    }

    private function validFeatures(): array
    {
        return [
            'area_ha' => 1.5,
            'fertilizer_kg_per_ha' => 60,
            'rainfall_mm' => 1300,
            'planting_month' => 11,
            'variety_class' => 'amelioree',
        ];
    }

    public function test_suggested_inputs_uses_real_data_and_leaves_non_derivable_fields_null(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'area_ha' => 2.0]);
        $crop = Crop::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();
        $cycle = CropCycle::factory()->create([
            'plot_id' => $plot->id, 'crop_id' => $crop->id, 'campaign_id' => $campaign->id,
            'planting_date' => '2026-11-15',
        ]);
        $engrais = Input::factory()->create(['type' => 'engrais']);
        InputUsage::factory()->create(['input_id' => $engrais->id, 'plot_id' => $plot->id, 'campaign_id' => $campaign->id, 'quantity' => 100]);

        $response = $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/prediction-inputs?campaign_id={$campaign->id}")->assertOk();

        $response->assertJsonPath('data.planting_month', 11)
            ->assertJsonPath('data.crop_id', $crop->id)
            ->assertJsonPath('data.rainfall_mm', null)
            ->assertJsonPath('data.variety_class', null);
        // PHP sérialise un float "rond" en JSON sans partie décimale (cf. Phase 7) : comparaison
        // numérique plutôt que stricte.
        $this->assertEquals(2.0, $response->json('data.area_ha'));
        $this->assertEquals(50.0, $response->json('data.fertilizer_kg_per_ha')); // 100 / 2.0 ha

        $this->assertNotNull($cycle);
    }

    public function test_suggested_inputs_derives_rainfall_from_real_weather_observations(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();
        CropCycle::factory()->create([
            'plot_id' => $plot->id, 'crop_id' => $crop->id, 'campaign_id' => $campaign->id,
            'planting_date' => '2026-11-01', 'expected_harvest_date' => '2027-02-01',
        ]);

        // Dans la période du cycle : doit être compté.
        WeatherObservation::factory()->create(['plot_id' => $plot->id, 'observed_date' => '2026-11-15', 'precipitation' => 120]);
        WeatherObservation::factory()->create(['plot_id' => $plot->id, 'observed_date' => '2026-12-15', 'precipitation' => 80]);
        // Hors période : ne doit pas être compté.
        WeatherObservation::factory()->create(['plot_id' => $plot->id, 'observed_date' => '2026-01-01', 'precipitation' => 999]);

        $response = $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/prediction-inputs?campaign_id={$campaign->id}")->assertOk();

        $this->assertEquals(200.0, $response->json('data.rainfall_mm'));
    }

    public function test_suggested_inputs_leaves_rainfall_null_without_weather_data(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);

        $response = $this->actingAs($manager)->getJson("/api/plots/{$plot->id}/prediction-inputs")->assertOk();

        $response->assertJsonPath('data.rainfall_mm', null);
    }

    public function test_store_prediction_creates_model_version_and_persists_prediction(): void
    {
        Http::fake([
            '*/predict' => Http::response($this->fakeAiPredictResponse(), 200),
            '*/models/*' => Http::response($this->fakeAiModelResponse('rice-yield-linear_regression-20260829-081454'), 200),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create(['is_active' => true]);

        $response = $this->actingAs($manager)->postJson('/api/predictions', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'features' => $this->validFeatures(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.predicted_yield', 2365.4)
            ->assertJsonPath('data.unit', 'kg/ha')
            ->assertJsonPath('data.is_experimental', true)
            ->assertJsonPath('data.model.algorithm', 'linear_regression')
            ->assertJsonPath('data.feature_importances.area_ha', 0.42);

        $this->assertDatabaseCount('production_predictions', 1);
        $this->assertDatabaseHas('model_versions', ['external_id' => 'rice-yield-linear_regression-20260829-081454']);

        $prediction = ProductionPrediction::first();
        $this->assertSame($plot->id, $prediction->plot_id);
        $this->assertEquals(2365.4, (float) $prediction->predicted_yield);
    }

    public function test_second_prediction_with_same_model_reuses_existing_model_version(): void
    {
        Http::fake([
            '*/predict' => Http::response($this->fakeAiPredictResponse(), 200),
            '*/models/*' => Http::response($this->fakeAiModelResponse('rice-yield-linear_regression-20260829-081454'), 200),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create(['is_active' => true]);

        $this->actingAs($manager)->postJson('/api/predictions', ['plot_id' => $plot->id, 'crop_id' => $crop->id, 'features' => $this->validFeatures()])->assertCreated();
        $this->actingAs($manager)->postJson('/api/predictions', ['plot_id' => $plot->id, 'crop_id' => $crop->id, 'features' => $this->validFeatures()])->assertCreated();

        $this->assertDatabaseCount('production_predictions', 2);
        $this->assertSame(1, ModelVersion::count());
    }

    public function test_store_prediction_rejects_plot_from_another_cooperative(): void
    {
        [$manager] = $this->makeCooperativeManager();
        $foreignPlot = Plot::factory()->create(); // autre coopérative
        $crop = Crop::factory()->create(['is_active' => true]);

        $response = $this->actingAs($manager)->postJson('/api/predictions', [
            'plot_id' => $foreignPlot->id,
            'crop_id' => $crop->id,
            'features' => $this->validFeatures(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('plot_id');
        $this->assertDatabaseCount('production_predictions', 0);
    }

    public function test_store_prediction_returns_503_when_no_active_model_on_ai_service(): void
    {
        Http::fake([
            '*/predict' => Http::response(['detail' => "Aucun modèle entraîné n'est disponible."], 503),
        ]);

        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create(['is_active' => true]);

        $response = $this->actingAs($manager)->postJson('/api/predictions', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'features' => $this->validFeatures(),
        ]);

        $response->assertStatus(503);
        $this->assertDatabaseCount('production_predictions', 0);
    }

    public function test_store_validates_feature_bounds(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create(['is_active' => true]);

        $response = $this->actingAs($manager)->postJson('/api/predictions', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'features' => ['area_ha' => -1, 'fertilizer_kg_per_ha' => 10, 'rainfall_mm' => 1000, 'planting_month' => 13, 'variety_class' => 'inconnue'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'features.area_ha', 'features.planting_month', 'features.variety_class',
        ]);
    }

    /**
     * Cahier des charges §7/§40 : "aucune culture sélectionnée => pas de prédiction possible",
     * jamais un repli implicite sur le riz.
     */
    public function test_store_prediction_requires_a_crop(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);

        $response = $this->actingAs($manager)->postJson('/api/predictions', [
            'plot_id' => $plot->id,
            'features' => $this->validFeatures(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('crop_id');
        $this->assertDatabaseCount('production_predictions', 0);
    }

    /** Cahier des charges §20 : une culture désactivée (résidu de test) ne doit jamais pouvoir
     * être utilisée pour une prédiction, même en connaissant son id. */
    public function test_store_prediction_rejects_an_inactive_crop(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $inactiveCrop = Crop::factory()->create(['is_active' => false]);

        $response = $this->actingAs($manager)->postJson('/api/predictions', [
            'plot_id' => $plot->id,
            'crop_id' => $inactiveCrop->id,
            'features' => $this->validFeatures(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('crop_id');
        $this->assertDatabaseCount('production_predictions', 0);
    }

    public function test_farmer_cannot_request_a_prediction(): void
    {
        [$user, $farmer, $coop] = $this->makeFarmerUser();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id]);
        $crop = Crop::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->postJson('/api/predictions', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'features' => $this->validFeatures(),
        ]);

        $response->assertForbidden();
    }

    public function test_index_scopes_predictions_to_cooperative_manager(): void
    {
        Http::fake([
            '*/predict' => Http::response($this->fakeAiPredictResponse(), 200),
            '*/models/*' => Http::response($this->fakeAiModelResponse('rice-yield-linear_regression-20260829-081454'), 200),
        ]);

        $crop = Crop::factory()->create(['is_active' => true]);

        [$manager, $ownCoop] = $this->makeCooperativeManager();
        $ownPlot = Plot::factory()->create(['cooperative_id' => $ownCoop->id]);
        $this->actingAs($manager)->postJson('/api/predictions', ['plot_id' => $ownPlot->id, 'crop_id' => $crop->id, 'features' => $this->validFeatures()])->assertCreated();

        [$otherManager, $otherCoop] = $this->makeCooperativeManager();
        $otherPlot = Plot::factory()->create(['cooperative_id' => $otherCoop->id]);
        $this->actingAs($otherManager)->postJson('/api/predictions', ['plot_id' => $otherPlot->id, 'crop_id' => $crop->id, 'features' => $this->validFeatures()])->assertCreated();

        $response = $this->actingAs($manager)->getJson('/api/predictions')->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    // --- GET /predictions?plot_id=... (carte "Prévision IA" du Dashboard) -------------------

    public function test_index_without_plot_id_returns_predictions_from_every_plot(): void
    {
        $admin = $this->makeAdmin();
        $plotA = Plot::factory()->create();
        $plotB = Plot::factory()->create();
        ProductionPrediction::factory()->create(['plot_id' => $plotA->id, 'predicted_at' => now()->subDay()]);
        ProductionPrediction::factory()->create(['plot_id' => $plotB->id, 'predicted_at' => now()]);

        $response = $this->actingAs($admin)->getJson('/api/predictions')->assertOk();

        $response->assertJsonCount(2, 'data');
    }

    public function test_index_with_plot_id_returns_only_that_plots_predictions_most_recent_first(): void
    {
        $admin = $this->makeAdmin();
        $plotA = Plot::factory()->create();
        $plotB = Plot::factory()->create();
        ProductionPrediction::factory()->create(['plot_id' => $plotA->id, 'predicted_at' => now()->subDays(2)]);
        $newestForA = ProductionPrediction::factory()->create(['plot_id' => $plotA->id, 'predicted_at' => now()]);
        ProductionPrediction::factory()->create(['plot_id' => $plotB->id, 'predicted_at' => now()]);

        $response = $this->actingAs($admin)->getJson("/api/predictions?plot_id={$plotA->id}")->assertOk();

        $response->assertJsonCount(2, 'data');
        // La plus récente de la parcelle A doit arriver en premier (tri déjà appliqué par
        // l'index, réutilisé tel quel par le Dashboard — voir §7/§13 : prendre `data[0]`).
        $this->assertSame($newestForA->id, $response->json('data.0.id'));
        $this->assertSame($plotA->id, $response->json('data.0.plot_id'));
        $this->assertSame($plotA->id, $response->json('data.1.plot_id'));
    }

    public function test_index_with_plot_id_returns_an_empty_collection_when_the_plot_has_no_prediction(): void
    {
        $admin = $this->makeAdmin();
        $plotWithoutPrediction = Plot::factory()->create();
        $otherPlot = Plot::factory()->create();
        ProductionPrediction::factory()->create(['plot_id' => $otherPlot->id]);

        $response = $this->actingAs($admin)->getJson("/api/predictions?plot_id={$plotWithoutPrediction->id}")->assertOk();

        // Jamais un repli sur la prédiction globale la plus récente : une collection vide,
        // sans aucune trace de la prédiction d'une autre parcelle (§3/§16 — pas de fallback).
        $response->assertJsonCount(0, 'data');
    }

    public function test_index_with_plot_id_never_leaks_another_plots_prediction(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $ownPlot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        [, $otherCoop] = $this->makeCooperativeManager();
        $otherPlot = Plot::factory()->create(['cooperative_id' => $otherCoop->id]);
        ProductionPrediction::factory()->create(['plot_id' => $ownPlot->id]);
        $foreignPrediction = ProductionPrediction::factory()->create(['plot_id' => $otherPlot->id]);

        // Même en tant qu'admin (périmètre non restreint), filtrer par plot_id=ownPlot ne doit
        // jamais faire remonter la prédiction d'une AUTRE parcelle.
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->getJson("/api/predictions?plot_id={$ownPlot->id}")->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($foreignPrediction->id));
    }
}

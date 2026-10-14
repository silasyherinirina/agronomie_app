<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\CropVariety;
use App\Models\Plot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class CropCycleApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_store_rejects_variety_that_does_not_belong_to_crop(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create();
        $otherCrop = Crop::factory()->create();
        $foreignVariety = CropVariety::factory()->create(['crop_id' => $otherCrop->id]);
        $campaign = AgriculturalCampaign::factory()->create();

        $this->actingAs($manager)->postJson('/api/crop-cycles', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'variety_id' => $foreignVariety->id,
            'campaign_id' => $campaign->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('variety_id');
    }

    public function test_store_requires_plot_crop_and_campaign(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->postJson('/api/crop-cycles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plot_id', 'crop_id', 'campaign_id']);
    }

    public function test_expected_harvest_date_must_be_after_planting_date(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();

        $this->actingAs($manager)->postJson('/api/crop-cycles', [
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'campaign_id' => $campaign->id,
            'planting_date' => '2026-06-01',
            'expected_harvest_date' => '2026-01-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('expected_harvest_date');
    }

    public function test_index_filters_by_status(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        CropCycle::factory()->create(['plot_id' => $plot->id, 'status' => 'harvested']);
        CropCycle::factory()->create(['plot_id' => $plot->id, 'status' => 'in_progress']);

        $this->actingAs($manager)->getJson('/api/crop-cycles?status=harvested')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * Module M — Ventes agricoles : le formulaire « Nouvelle vente » filtre les cycles culturaux
     * par coopérative sélectionnée (un cycle n'a de cooperative_id que via sa parcelle).
     */
    public function test_index_filters_by_cooperative_id(): void
    {
        $admin = $this->makeAdmin();
        [, $coopA] = $this->makeCooperativeManager();
        [, $coopB] = $this->makeCooperativeManager();
        $plotA = Plot::factory()->create(['cooperative_id' => $coopA->id]);
        $plotB = Plot::factory()->create(['cooperative_id' => $coopB->id]);
        CropCycle::factory()->create(['plot_id' => $plotA->id]);
        CropCycle::factory()->create(['plot_id' => $plotB->id]);

        $response = $this->actingAs($admin)->getJson("/api/crop-cycles?cooperative_id={$coopA->id}")->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertSame($plotA->id, $response->json('data.0.plot_id'));
    }

    /**
     * Cas réel audité (coopérative « Démo Bongolava 01 », id=59 en production) : deux cycles
     * culturaux DISTINCTS partageant la même parcelle et la même culture, mais deux campagnes
     * différentes, ont paru être un "doublon" dans le formulaire Ventes — ce n'en est pas un.
     * L'API doit renvoyer les deux enregistrements réels, jamais les fusionner/dédupliquer.
     */
    public function test_index_never_collapses_two_distinct_cycles_sharing_the_same_plot_and_crop(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $crop = Crop::factory()->create();
        $campaignPast = AgriculturalCampaign::factory()->create(['year' => 2020]);
        $campaignCurrent = AgriculturalCampaign::factory()->create(['year' => 2026]);

        $past = CropCycle::factory()->create([
            'plot_id' => $plot->id, 'crop_id' => $crop->id, 'campaign_id' => $campaignPast->id, 'status' => 'harvested',
        ]);
        $current = CropCycle::factory()->create([
            'plot_id' => $plot->id, 'crop_id' => $crop->id, 'campaign_id' => $campaignCurrent->id, 'status' => 'in_progress',
        ]);

        $response = $this->actingAs($manager)->getJson("/api/crop-cycles?cooperative_id={$coop->id}")->assertOk();

        $response->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($past->id));
        $this->assertTrue($ids->contains($current->id));
        // Chaque enregistrement porte sa PROPRE campagne — c'est ce qui les distingue réellement.
        $campaignYears = collect($response->json('data'))->pluck('campaign.year')->sort()->values();
        $this->assertEquals([2020, 2026], $campaignYears->all());
    }
}

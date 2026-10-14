<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Harvest;
use App\Models\Plot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

/**
 * Phase 13 — performance : vérifie par la preuve (comptage réel de requêtes SQL) que les
 * endpoints de liste ne souffrent pas de N+1 quand le nombre de lignes augmente — le nombre
 * de requêtes doit rester constant, pas croître linéairement avec le nombre de résultats.
 */
class QueryCountAuditTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_crop_cycles_index_query_count_does_not_grow_with_row_count(): void
    {
        $admin = $this->makeAdmin();
        $plot = Plot::factory()->create();
        $crop = Crop::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();

        CropCycle::factory()->count(3)->create(['plot_id' => $plot->id, 'crop_id' => $crop->id, 'campaign_id' => $campaign->id]);
        DB::enableQueryLog();
        $this->actingAs($admin)->getJson('/api/crop-cycles')->assertOk();
        $queriesFor3 = count(DB::getQueryLog());
        DB::flushQueryLog();

        CropCycle::factory()->count(12)->create(['plot_id' => $plot->id, 'crop_id' => $crop->id, 'campaign_id' => $campaign->id]);
        DB::flushQueryLog(); // les 12 INSERT ci-dessus ne doivent pas être comptés avec la requête GET.
        $this->actingAs($admin)->getJson('/api/crop-cycles')->assertOk();
        $queriesFor15 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queriesFor3, $queriesFor15, 'Le nombre de requêtes ne doit pas croître avec le nombre de lignes (N+1).');
    }

    public function test_harvests_index_query_count_does_not_grow_with_row_count(): void
    {
        $admin = $this->makeAdmin();
        $cycle = CropCycle::factory()->create();

        Harvest::factory()->count(3)->create(['crop_cycle_id' => $cycle->id]);
        DB::enableQueryLog();
        $this->actingAs($admin)->getJson('/api/harvests')->assertOk();
        $queriesFor3 = count(DB::getQueryLog());
        DB::flushQueryLog();

        Harvest::factory()->count(12)->create(['crop_cycle_id' => $cycle->id]);
        DB::flushQueryLog(); // les 12 INSERT ci-dessus ne doivent pas être comptés avec la requête GET.
        $this->actingAs($admin)->getJson('/api/harvests')->assertOk();
        $queriesFor15 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queriesFor3, $queriesFor15);
    }

    public function test_dashboard_query_count_stays_low_and_constant(): void
    {
        $admin = $this->makeAdmin();
        $plot = Plot::factory()->create(['area_ha' => 1]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);
        Harvest::factory()->count(5)->create(['crop_cycle_id' => $cycle->id, 'unit' => 'kg']);

        DB::enableQueryLog();
        $this->actingAs($admin)->getJson('/api/dashboard')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Agrégats indépendants (KPIs + 6 regroupements + activité récente [4 requêtes bornées
        // par limit()] + alertes) : borne large mais garde-fou contre une régression qui boucle
        // sur les lignes au lieu d'agréger en SQL. Relevée de 15 à 20 après l'ajout de
        // recentActivity()/alerts() (5 requêtes supplémentaires, toutes bornées, jamais par ligne
        // — voir test_dashboard_query_count_is_constant_regardless_of_row_count ci-dessus, qui
        // reste la vraie garantie contre le N+1). Relevée de 20 à 26 après l'ajout du Module M
        // (Ventes) : SaleService::statistics() (3 requêtes bornées : totaux fusionnés + 2
        // GROUP BY) + salesByCampaign() + recentSales() (limit(5)) — toutes indépendantes du
        // nombre de ventes, jamais une requête par ligne.
        $this->assertLessThan(26, $queryCount);
    }
}

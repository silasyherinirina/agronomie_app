<?php

namespace Tests\Feature\Api;

use App\Models\Crop;
use App\Models\CropVariety;
use Database\Seeders\CropSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class CropReferenceDataApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    public function test_store_requires_unique_name(): void
    {
        $admin = $this->makeAdmin();
        Crop::factory()->create(['name' => 'Riz']);

        $this->actingAs($admin)->postJson('/api/crops', ['name' => 'Riz'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_any_authenticated_role_can_list_crops(): void
    {
        Crop::factory()->count(2)->create();
        $farmer = $this->makeUser('farmer');

        $this->actingAs($farmer)->getJson('/api/crops')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_variety_must_reference_existing_crop(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->postJson('/api/crop-varieties', ['crop_id' => 999999, 'name' => 'Variété X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('crop_id');
    }

    public function test_variety_name_unique_per_crop_not_globally(): void
    {
        $admin = $this->makeAdmin();
        $cropA = Crop::factory()->create();
        $cropB = Crop::factory()->create();
        CropVariety::factory()->create(['crop_id' => $cropA->id, 'name' => 'Rouge']);

        // Même nom de variété mais culture différente : autorisé.
        $this->actingAs($admin)->postJson('/api/crop-varieties', ['crop_id' => $cropB->id, 'name' => 'Rouge'])
            ->assertCreated();

        // Même nom, même culture : refusé.
        $this->actingAs($admin)->postJson('/api/crop-varieties', ['crop_id' => $cropA->id, 'name' => 'Rouge'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    /**
     * Cahier des charges §24, tests 1-3 : normalisation stable et indépendante des accents,
     * pas dépendante du nom affiché par le frontend.
     */
    #[DataProvider('cropKeyProvider')]
    public function test_creating_a_crop_normalizes_its_code(string $name, string $expectedCode): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->postJson('/api/crops', ['name' => $name])->assertCreated();

        $response->assertJsonPath('data.code', $expectedCode);
        $this->assertDatabaseHas('crops', ['name' => $name, 'code' => $expectedCode]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cropKeyProvider(): array
    {
        return [
            'Patate douce' => ['Patate douce', 'patate_douce'],
            'Pomme de terre' => ['Pomme de terre', 'pomme_de_terre'],
            'Niébé' => ['Niébé', 'niebe'],
        ];
    }

    /**
     * Régression du bug racine (§27/§31 étape 2) : sur une base fraîchement seedée, les 20
     * cultures de référence doivent toutes porter un `code` exploitable — c'est l'absence de ce
     * `code` pour "Patate douce" qui provoquait "Aucun modèle entraîné n'est disponible..." alors
     * que la culture existait bien en base.
     */
    public function test_crop_seeder_assigns_a_stable_code_to_every_reference_crop(): void
    {
        $this->seed(CropSeeder::class);

        $this->assertDatabaseHas('crops', ['name' => 'Patate douce', 'code' => 'patate_douce', 'is_active' => true]);
        $this->assertDatabaseHas('crops', ['name' => 'Pomme de terre', 'code' => 'pomme_de_terre', 'is_active' => true]);
        $this->assertDatabaseHas('crops', ['name' => 'Niébé', 'code' => 'niebe', 'is_active' => true]);
        $this->assertDatabaseHas('crops', ['name' => 'Blé', 'code' => 'ble', 'is_active' => true]);

        $this->assertSame(0, Crop::query()->whereIn('name', [
            'Riz', 'Maïs', 'Blé', 'Triticale', 'Sorgho', 'Manioc', 'Patate douce', 'Pomme de terre',
            'Taro', 'Gingembre', 'Haricot', 'Arachide', 'Niébé', 'Pois du Cap', 'Voandzou', 'Soja',
            'Sésame', 'Café', 'Vanille', 'Coton',
        ])->whereNull('code')->count());
    }

    public function test_model_status_reports_availability_per_crop_from_the_ai_service(): void
    {
        $this->seed(CropSeeder::class);
        $admin = $this->makeAdmin();

        Http::fake([
            '*/models/status' => Http::response(['data' => [
                ['crop_key' => 'riz', 'crop_label' => 'riz', 'available' => true, 'status' => 'ready', 'algorithm' => 'linear_regression', 'version' => '1.0.0', 'is_experimental' => true],
                ['crop_key' => 'patate_douce', 'crop_label' => 'patate douce', 'available' => false, 'status' => 'training_required'],
            ]], 200),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/crops/model-status')->assertOk();

        $byKey = collect($response->json('data'))->keyBy('crop_key');
        $this->assertTrue($byKey['riz']['available']);
        $this->assertSame('ready', $byKey['riz']['status']);
        $this->assertFalse($byKey['patate_douce']['available']);
        $this->assertSame('training_required', $byKey['patate_douce']['status']);
    }

    public function test_model_status_never_fabricates_availability_when_ai_service_is_unreachable(): void
    {
        $this->seed(CropSeeder::class);
        $admin = $this->makeAdmin();

        Http::fake(['*/models/status' => Http::response(['message' => 'Internal Server Error'], 500)]);

        $response = $this->actingAs($admin)->getJson('/api/crops/model-status')->assertOk();

        $this->assertTrue(collect($response->json('data'))->every(fn ($crop) => $crop['available'] === false));
    }

    public function test_index_filters_varieties_by_crop_id(): void
    {
        $admin = $this->makeAdmin();
        $crop = Crop::factory()->create();
        CropVariety::factory()->count(2)->create(['crop_id' => $crop->id]);
        CropVariety::factory()->count(3)->create();

        $this->actingAs($admin)->getJson("/api/crop-varieties?crop_id={$crop->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}

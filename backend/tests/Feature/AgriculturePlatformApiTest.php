<?php

namespace Tests\Feature;

use App\Models\AgriculturalCampaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\CropVariety;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Input;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

/**
 * Test bout-en-bout du backend Phase 3 : authentification Sanctum, CRUD des ressources
 * principales, autorisation par rôle, calcul PostGIS de superficie, stratégie de rendement.
 *
 * Les tests plus détaillés par ressource (pagination, validation, 404, soft-delete) sont
 * dans tests/Feature/Api/ (Phase 4) ; ce fichier reste focalisé sur les parcours transverses.
 */
class AgriculturePlatformApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    private function plotGeoJson(): array
    {
        // Rectangle ~1km x 1km près d'Antananarivo (données synthétiques de test, non réelles).
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [47.50, -18.88],
                [47.51, -18.88],
                [47.51, -18.87],
                [47.50, -18.87],
                [47.50, -18.88],
            ]],
        ];
    }

    public function test_login_succeeds_with_valid_credentials_and_fails_otherwise(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        // EnsureFrontendRequestsAreStateful (Sanctum) n'active la session que pour une requête
        // portant un Origin/Referer reconnu comme "frontend" (SANCTUM_STATEFUL_DOMAINS) — on
        // reproduit ici l'en-tête que le navigateur enverrait réellement depuis le SPA React.
        // La vérification CSRF elle-même (jeton XSRF-TOKEN <-> en-tête X-XSRF-TOKEN) suppose un
        // vrai navigateur ; elle est désactivée via le toggle officiel de config Sanctum pour ce
        // test de logique métier (Auth::attempt), pas pour re-tester le mécanisme CSRF lui-même.
        config(['sanctum.middleware.validate_csrf_token' => null]);
        $frontend = ['Referer' => 'http://localhost:5173/'];

        $this->withHeaders($frontend)
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withHeaders($frontend)
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    public function test_admin_can_create_cooperative_but_cooperative_manager_cannot(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->postJson('/api/cooperatives', [
            'name' => 'Coopérative Test',
            'region' => 'Analamanga',
            'geometry' => ['type' => 'Point', 'coordinates' => [47.5079, -18.8792]],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Coopérative Test')
            ->assertJsonPath('data.geometry.coordinates.0', 47.5079);

        $manager = $this->makeUser('cooperative_manager');

        $this->actingAs($manager)->postJson('/api/cooperatives', [
            'name' => 'Autre coopérative',
            'region' => 'Analamanga',
        ])->assertForbidden();
    }

    public function test_cooperative_manager_can_update_own_cooperative_but_not_another(): void
    {
        $manager = $this->makeUser('cooperative_manager');
        $ownCoop = Cooperative::factory()->create(['manager_user_id' => $manager->id]);
        $otherCoop = Cooperative::factory()->create();

        $this->actingAs($manager)->putJson("/api/cooperatives/{$ownCoop->id}", ['name' => 'Nom modifié'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nom modifié');

        $this->actingAs($manager)->putJson("/api/cooperatives/{$otherCoop->id}", ['name' => 'Intrusion'])
            ->assertForbidden();
    }

    public function test_cooperative_manager_creates_farmer_scoped_to_own_cooperative(): void
    {
        $manager = $this->makeUser('cooperative_manager');
        $ownCoop = Cooperative::factory()->create(['manager_user_id' => $manager->id]);
        $otherCoop = Cooperative::factory()->create();

        // Tentative d'injection d'une autre coopérative : doit être ignorée et forcée à la sienne.
        $response = $this->actingAs($manager)->postJson('/api/farmers', [
            'cooperative_id' => $otherCoop->id,
            'first_name' => 'Rina',
            'last_name' => 'Andria',
        ])->assertCreated();

        $response->assertJsonPath('data.cooperative_id', $ownCoop->id);
    }

    public function test_coordinates_outside_madagascar_are_rejected(): void
    {
        $admin = $this->makeUser('admin');

        // Paris (48.85, 2.35) — coordonnée plausible ailleurs dans le monde, mais hors des bornes
        // Madagascar retenues : doit être rejetée, jamais acceptée telle quelle (règle §24).
        $this->actingAs($admin)->postJson('/api/cooperatives', [
            'name' => 'Coopérative Hors Zone',
            'region' => 'Analamanga',
            'geometry' => ['type' => 'Point', 'coordinates' => [2.35, 48.85]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('geometry.coordinates');
    }

    public function test_plot_creation_computes_real_postgis_area(): void
    {
        $manager = $this->makeUser('cooperative_manager');
        $coop = Cooperative::factory()->create(['manager_user_id' => $manager->id]);

        $response = $this->actingAs($manager)->postJson('/api/plots', [
            'name' => 'Parcelle A',
            'geometry' => $this->plotGeoJson(),
        ])->assertCreated();

        $areaHa = $response->json('data.area_ha');

        // ~1km x 1km, tolérance large : la valeur doit être réelle (calculée par PostGIS),
        // jamais nulle ni arbitraire.
        $this->assertNotNull($areaHa);
        $this->assertGreaterThan(50, $areaHa);
        $this->assertLessThan(200, $areaHa);
    }

    public function test_plot_creation_rejects_farmer_from_another_cooperative(): void
    {
        $manager = $this->makeUser('cooperative_manager');
        $ownCoop = Cooperative::factory()->create(['manager_user_id' => $manager->id]);
        $otherCoop = Cooperative::factory()->create();
        $foreignFarmer = Farmer::factory()->create(['cooperative_id' => $otherCoop->id]);

        $this->actingAs($manager)->postJson('/api/plots', [
            'farmer_id' => $foreignFarmer->id,
            'name' => 'Parcelle suspecte',
            'geometry' => $this->plotGeoJson(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('farmer_id');
    }

    public function test_farmer_role_can_only_view_own_plot(): void
    {
        $coop = Cooperative::factory()->create();
        $farmerUser = $this->makeUser('farmer');
        $farmer = Farmer::factory()->create(['cooperative_id' => $coop->id, 'user_id' => $farmerUser->id]);
        $otherFarmer = Farmer::factory()->create(['cooperative_id' => $coop->id]);

        $ownPlot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id]);
        $otherPlot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $otherFarmer->id]);

        $this->actingAs($farmerUser)->getJson("/api/plots/{$ownPlot->id}")->assertOk();
        $this->actingAs($farmerUser)->getJson("/api/plots/{$otherPlot->id}")->assertForbidden();
    }

    public function test_only_admin_can_manage_reference_data_like_crops(): void
    {
        $admin = $this->makeUser('admin');
        $analyst = $this->makeUser('analyst');

        $this->actingAs($admin)->postJson('/api/crops', [
            'name' => 'Riz',
            'category' => 'céréale',
            'cycle_duration_days' => 120,
        ])->assertCreated();

        $this->actingAs($analyst)->postJson('/api/crops', ['name' => 'Maïs'])
            ->assertForbidden();

        // Lecture ouverte à tous les rôles authentifiés.
        $this->actingAs($analyst)->getJson('/api/crops')->assertOk();
    }

    public function test_harvest_yield_is_calculated_from_plot_area_when_source_is_calculated(): void
    {
        $manager = $this->makeUser('cooperative_manager');
        $coop = Cooperative::factory()->create(['manager_user_id' => $manager->id]);
        $plot = Plot::factory()->create([
            'cooperative_id' => $coop->id,
            'geom' => Polygon::fromArray($this->plotGeoJson()),
            'area_ha' => 10.0, // valeur de test déterministe pour vérifier le calcul
        ]);
        $crop = Crop::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();
        $cropCycle = CropCycle::factory()->create([
            'plot_id' => $plot->id,
            'crop_id' => $crop->id,
            'campaign_id' => $campaign->id,
        ]);

        $response = $this->actingAs($manager)->postJson('/api/harvests', [
            'crop_cycle_id' => $cropCycle->id,
            'harvest_date' => now()->toDateString(),
            'quantity' => 25000, // kg
            'unit' => 'kg',
            'yield_source' => 'calculated',
        ])->assertCreated();

        // 25000 kg / 10 ha = 2500 kg/ha
        // (json_encode d'un float sans partie décimale produit "2500", décodé en int par PHP :
        // comparaison numérique volontairement souple ici, ce n'est pas un bug applicatif)
        $this->assertEquals(2500.0, $response->json('data.yield_value'));
    }

    public function test_harvest_deletion_is_restricted_to_admin_for_traceability(): void
    {
        $manager = $this->makeUser('cooperative_manager');
        $coop = Cooperative::factory()->create(['manager_user_id' => $manager->id]);
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'area_ha' => 5]);
        $cropCycle = CropCycle::factory()->create(['plot_id' => $plot->id]);
        $harvest = Harvest::factory()->create(['crop_cycle_id' => $cropCycle->id]);

        $this->actingAs($manager)->deleteJson("/api/harvests/{$harvest->id}")->assertForbidden();

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->deleteJson("/api/harvests/{$harvest->id}")->assertNoContent();
    }

    public function test_deleting_referenced_crop_returns_conflict_instead_of_sql_error(): void
    {
        $admin = $this->makeUser('admin');
        $crop = Crop::factory()->create();
        CropVariety::factory()->create(['crop_id' => $crop->id]);
        $plot = Plot::factory()->create();
        CropCycle::factory()->create(['plot_id' => $plot->id, 'crop_id' => $crop->id]);

        $this->actingAs($admin)->deleteJson("/api/crops/{$crop->id}")
            ->assertStatus(409);
    }

    public function test_input_usage_requires_cooperative_manager_and_scopes_by_cooperative(): void
    {
        $manager = $this->makeUser('cooperative_manager');
        $coop = Cooperative::factory()->create(['manager_user_id' => $manager->id]);
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id]);
        $input = Input::factory()->create();
        $campaign = AgriculturalCampaign::factory()->create();

        $this->actingAs($manager)->postJson('/api/input-usages', [
            'input_id' => $input->id,
            'plot_id' => $plot->id,
            'campaign_id' => $campaign->id,
            'quantity' => 50,
            'date' => now()->toDateString(),
        ])->assertCreated();

        $farmerUser = $this->makeUser('farmer');
        $this->actingAs($farmerUser)->postJson('/api/input-usages', [
            'input_id' => $input->id,
            'plot_id' => $plot->id,
            'campaign_id' => $campaign->id,
            'quantity' => 50,
            'date' => now()->toDateString(),
        ])->assertForbidden();
    }
}

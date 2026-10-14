<?php

namespace Tests\Feature\Api;

use App\Models\AgriculturalCampaign;
use App\Models\CropCycle;
use App\Models\Plot;
use App\Models\User;
use App\Notifications\CampaignActivatedNotification;
use App\Notifications\HarvestRecordedNotification;
use App\Notifications\SaleRecordedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestActors;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use CreatesTestActors;
    use RefreshDatabase;

    /**
     * Insère directement une notification "brute" pour un utilisateur donné — les tests
     * d'isolation/pagination/lecture n'ont besoin d'aucune entité métier réelle, seulement
     * d'une ligne `notifications` respectant le schéma standard Laravel.
     *
     * @param  array<string, mixed>  $data
     */
    private function makeNotification(User $user, bool $read = false, array $data = []): DatabaseNotification
    {
        $notification = new DatabaseNotification();
        $notification->id = (string) Str::uuid();
        $notification->type = HarvestRecordedNotification::class;
        $notification->notifiable_type = User::class;
        $notification->notifiable_id = $user->id;
        $notification->data = array_merge([
            'title' => 'Récolte enregistrée',
            'message' => 'La récolte de riz de la parcelle « Parcelle test » a été enregistrée.',
            'type' => 'harvest',
            'severity' => 'info',
            'icon' => 'wheat',
            'route' => '/harvests',
            'entity_type' => 'harvest',
            'entity_id' => 1,
            'metadata' => [],
        ], $data);
        $notification->read_at = $read ? now() : null;
        $notification->save();

        return $notification;
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    public function test_user_can_list_their_own_notifications_most_recent_first(): void
    {
        $user = $this->makeUser();
        $older = $this->makeNotification($user);
        $older->created_at = now()->subDay();
        $older->save();
        $newer = $this->makeNotification($user);

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    public function test_unread_count_reflects_only_unread_notifications(): void
    {
        $user = $this->makeUser();
        $this->makeNotification($user, read: true);
        $this->makeNotification($user, read: false);
        $this->makeNotification($user, read: false);

        $this->actingAs($user)->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_marking_a_notification_as_read_sets_read_at(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $response = $this->actingAs($user)->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertTrue($response->json('data.is_read'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_clears_unread_count(): void
    {
        $user = $this->makeUser();
        $this->makeNotification($user);
        $this->makeNotification($user);

        $this->actingAs($user)->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJson(['count' => 0, 'updated' => 2]);

        $this->actingAs($user)->getJson('/api/notifications/unread-count')
            ->assertJson(['count' => 0]);
    }

    public function test_user_cannot_view_or_modify_another_users_notification(): void
    {
        $owner = $this->makeUser();
        $intruder = $this->makeUser();
        $notification = $this->makeNotification($owner);

        $this->actingAs($intruder)->getJson("/api/notifications/{$notification->id}")->assertNotFound();
        $this->actingAs($intruder)->patchJson("/api/notifications/{$notification->id}/read")->assertNotFound();

        // La notification de "owner" ne doit jamais avoir été affectée par la tentative de "intruder".
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notifications_are_paginated(): void
    {
        $user = $this->makeUser();
        for ($i = 0; $i < 12; $i++) {
            $this->makeNotification($user);
        }

        $response = $this->actingAs($user)->getJson('/api/notifications?per_page=10')->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(12, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    public function test_notification_without_route_is_returned_safely(): void
    {
        $user = $this->makeUser();
        $this->makeNotification($user, data: ['route' => null]);

        $response = $this->actingAs($user)->getJson('/api/notifications')->assertOk();

        $this->assertNull($response->json('data.0.route'));
    }

    public function test_status_filter_returns_only_unread_or_read(): void
    {
        $user = $this->makeUser();
        $this->makeNotification($user, read: true);
        $this->makeNotification($user, read: false);

        $unread = $this->actingAs($user)->getJson('/api/notifications?status=unread')->assertOk();
        $this->assertCount(1, $unread->json('data'));
        $this->assertFalse($unread->json('data.0.is_read'));

        $read = $this->actingAs($user)->getJson('/api/notifications?status=read')->assertOk();
        $this->assertCount(1, $read->json('data'));
        $this->assertTrue($read->json('data.0.is_read'));
    }

    // --- Événements métier réels ---------------------------------------------------------

    public function test_recording_a_harvest_notifies_the_plot_farmer(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        [$farmerUser, $farmer] = $this->makeFarmerUser($coop);
        $plot = Plot::factory()->create(['cooperative_id' => $coop->id, 'farmer_id' => $farmer->id, 'area_ha' => 2]);
        $cycle = CropCycle::factory()->create(['plot_id' => $plot->id]);

        $this->actingAs($manager)->postJson('/api/harvests', [
            'crop_cycle_id' => $cycle->id,
            'harvest_date' => now()->toDateString(),
            'quantity' => 1000,
            'unit' => 'kg',
        ])->assertCreated();

        $this->assertSame(1, $farmerUser->notifications()->count());
        $this->assertSame(
            HarvestRecordedNotification::class,
            $farmerUser->notifications()->first()->type
        );
    }

    public function test_recording_a_sale_notifies_the_farmer_when_linked(): void
    {
        [$manager, $coop] = $this->makeCooperativeManager();
        [$farmerUser, $farmer] = $this->makeFarmerUser($coop);

        $this->actingAs($manager)->postJson('/api/sales', [
            'farmer_id' => $farmer->id,
            'sale_date' => now()->toDateString(),
            'quantity' => 500,
            'unit' => 'kg',
            'unit_price' => 1000,
        ])->assertCreated();

        $this->assertSame(1, $farmerUser->notifications()->count());
        $this->assertSame(
            SaleRecordedNotification::class,
            $farmerUser->notifications()->first()->type
        );
    }

    public function test_sale_without_farmer_notifies_nobody(): void
    {
        [$manager] = $this->makeCooperativeManager();

        $this->actingAs($manager)->postJson('/api/sales', [
            'sale_date' => now()->toDateString(),
            'quantity' => 500,
            'unit' => 'kg',
            'unit_price' => 1000,
        ])->assertCreated();

        $this->assertSame(0, DatabaseNotification::query()->where('type', SaleRecordedNotification::class)->count());
    }

    public function test_activating_a_campaign_notifies_all_users(): void
    {
        $admin = $this->makeAdmin();
        $farmer = $this->makeUser('farmer');
        $campaign = AgriculturalCampaign::factory()->create(['status' => 'closed']);

        $this->actingAs($admin)->putJson("/api/agricultural-campaigns/{$campaign->id}", [
            'status' => 'active',
        ])->assertOk();

        $this->assertSame(
            CampaignActivatedNotification::class,
            $farmer->notifications()->first()?->type
        );
    }

    public function test_reactivating_an_already_active_campaign_does_not_notify_again(): void
    {
        $admin = $this->makeAdmin();
        $farmer = $this->makeUser('farmer');
        $campaign = AgriculturalCampaign::factory()->create(['status' => 'active']);

        $this->actingAs($admin)->putJson("/api/agricultural-campaigns/{$campaign->id}", [
            'name' => $campaign->name,
            'status' => 'active',
        ])->assertOk();

        $this->assertSame(0, $farmer->notifications()->count());
    }
}

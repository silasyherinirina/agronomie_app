<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_own_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Avant', 'email' => 'avant@example.com']);

        $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'Après',
            'email' => 'apres@example.com',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Après')
            ->assertJsonPath('data.email', 'apres@example.com');
    }

    public function test_role_cannot_be_changed_via_profile_update(): void
    {
        $user = User::factory()->create(['role' => 'farmer']);

        $this->actingAs($user)->putJson('/api/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
        ])->assertOk();

        $this->assertSame('farmer', $user->fresh()->role);
    }

    public function test_changing_email_resets_verification_and_sends_a_new_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ancien@example.com']);
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($user)->putJson('/api/profile', [
            'name' => $user->name,
            'email' => 'nouveau@example.com',
        ])->assertOk();

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    }

    public function test_keeping_the_same_email_does_not_reset_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'stable@example.com']);

        $this->actingAs($user)->putJson('/api/profile', [
            'name' => 'Nouveau nom',
            'email' => 'stable@example.com',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_email_must_stay_unique_among_other_users(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'deja-pris@example.com']);

        $this->actingAs($user)->putJson('/api/profile', [
            'name' => $user->name,
            'email' => 'deja-pris@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_user_can_change_their_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('ancien-mdp-123')]);

        $this->actingAs($user)->putJson('/api/profile/password', [
            'current_password' => 'ancien-mdp-123',
            'password' => 'nouveau-mdp-456',
            'password_confirmation' => 'nouveau-mdp-456',
        ])->assertOk();

        $this->assertTrue(Hash::check('nouveau-mdp-456', $user->fresh()->password));
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('ancien-mdp-123')]);

        $this->actingAs($user)->putJson('/api/profile/password', [
            'current_password' => 'mauvais-mot-de-passe',
            'password' => 'nouveau-mdp-456',
            'password_confirmation' => 'nouveau-mdp-456',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        $this->putJson('/api/profile', ['name' => 'X', 'email' => 'x@example.com'])->assertUnauthorized();
        $this->putJson('/api/profile/password', [])->assertUnauthorized();
    }
}

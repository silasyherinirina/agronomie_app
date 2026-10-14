<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase D — inscription, mot de passe oublié/réinitialisation, vérification d'e-mail.
 * Notification::fake() : aucun e-mail réel n'est envoyé pendant les tests (MAIL_MAILER=log
 * en développement de toute façon — voir .env), on intercepte le contenu des notifications
 * pour simuler le clic sur le lien reçu, bout en bout.
 */
class AuthFlowsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // EnsureFrontendRequestsAreStateful (Sanctum) n'active la session (nécessaire à
        // login/register, qui appellent $request->session()) que pour une requête portant un
        // Origin/Referer reconnu (SANCTUM_STATEFUL_DOMAINS) — même pattern que
        // AgriculturePlatformApiTest::test_login_succeeds_with_valid_credentials_and_fails_otherwise.
        config(['sanctum.middleware.validate_csrf_token' => null]);
        $this->withHeader('Referer', 'http://localhost:5173/');
    }

    public function test_register_creates_a_farmer_role_account_regardless_of_role_sent(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Nouvel Utilisateur',
            'email' => 'nouveau@example.com',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'role' => 'admin', // tentative d'élévation de privilège : doit être ignorée
        ])->assertCreated();

        $response->assertJsonPath('data.role', 'farmer');
        $this->assertDatabaseHas('users', ['email' => 'nouveau@example.com', 'role' => 'farmer']);

        // Auto-connexion après inscription (comportement documenté). assertSuccessful() plutôt
        // qu'assertOk() : dans ce test, la même instance User (wasRecentlyCreated=true) est
        // réutilisée par le guard de session entre les deux appels simulés, ce qui fait
        // renvoyer 201 à JsonResource::calculateStatus() ici — un artefact de continuité du
        // harnais de test (chaque vraie requête HTTP en production recharge l'utilisateur
        // depuis la base et renverrait 200). Le comportement testé (accès authentifié après
        // inscription) est bien réel, seul le code de statut exact de ce second appel ne l'est pas.
        $this->getJson('/api/user')->assertSuccessful()->assertJsonPath('data.email', 'nouveau@example.com');

        Notification::assertSentTo(User::where('email', 'nouveau@example.com')->first(), VerifyEmail::class);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existe@example.com']);

        $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'existe@example.com',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->postJson('/api/register', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_forgot_password_returns_generic_message_for_unknown_email(): void
    {
        // Ne doit jamais révéler si l'e-mail existe ou non (anti-énumération).
        $known = $this->postJson('/api/forgot-password', ['email' => 'inconnu@example.com'])->assertOk();
        $unknownMessage = $known->json('message');

        User::factory()->create(['email' => 'connu@example.com']);
        Notification::fake();
        $knownResponse = $this->postJson('/api/forgot-password', ['email' => 'connu@example.com'])->assertOk();

        $this->assertSame($unknownMessage, $knownResponse->json('message'));
    }

    public function test_full_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => bcrypt('ancien-mdp-123')]);

        $this->postJson('/api/forgot-password', ['email' => 'reset@example.com'])->assertOk();

        $capturedToken = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$capturedToken) {
            $capturedToken = $notification->token;

            return true;
        });

        $this->postJson('/api/reset-password', [
            'token' => $capturedToken,
            'email' => 'reset@example.com',
            'password' => 'nouveau-mdp-456',
            'password_confirmation' => 'nouveau-mdp-456',
        ])->assertOk();

        // L'ancien mot de passe ne fonctionne plus, le nouveau si.
        $this->postJson('/api/login', ['email' => 'reset@example.com', 'password' => 'ancien-mdp-123'])
            ->assertUnprocessable();
        $this->postJson('/api/login', ['email' => 'reset@example.com', 'password' => 'nouveau-mdp-456'])
            ->assertOk();
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset2@example.com']);

        $this->postJson('/api/reset-password', [
            'token' => 'jeton-invalide',
            'email' => 'reset2@example.com',
            'password' => 'nouveau-mdp-456',
            'password_confirmation' => 'nouveau-mdp-456',
        ])->assertUnprocessable();
    }

    public function test_email_verification_link_marks_account_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->assertNull($user->fresh()->email_verified_at);

        $this->actingAs($user)->get($url)->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_rejects_tampered_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('mauvaise-adresse@example.com')]
        );

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_verification_requires_authentication(): void
    {
        $this->postJson('/api/email/verification-notification')->assertUnauthorized();
    }

    public function test_resend_verification_sends_notification_when_unverified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson('/api/email/verification-notification')->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}

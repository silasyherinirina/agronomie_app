<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Phase 13 — sécurité : POST /login est limité à 5 tentatives/minute par couple (email, IP)
 * pour se protéger du bourrage d'identifiants (voir AppServiceProvider::boot()).
 */
class LoginThrottleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    public function test_sixth_attempt_within_a_minute_is_throttled(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $frontend = ['Referer' => 'http://localhost:5173/'];
        config(['sanctum.middleware.validate_csrf_token' => null]);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders($frontend)
                ->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertUnprocessable(); // mauvais mot de passe, mais pas encore throttled
        }

        $this->withHeaders($frontend)
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429);

        // Un couple (email, IP) différent n'est pas affecté par le blocage ci-dessus.
        $other = User::factory()->create(['password' => bcrypt('secret123')]);
        $this->withHeaders($frontend)
            ->postJson('/api/login', ['email' => $other->email, 'password' => 'secret123'])
            ->assertOk();
    }
}

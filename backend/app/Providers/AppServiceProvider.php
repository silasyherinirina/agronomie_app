<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Politique de mot de passe par défaut (inscription + réinitialisation, Phase D) :
        // 8 caractères minimum, au moins une lettre et un chiffre. Appliqué partout où le
        // code utilise Password::defaults() plutôt que dupliqué dans chaque Form Request.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Phase 13 — sécurité : protection contre le bourrage d'identifiants (brute force).
        // Clé = IP + email tenté, pour limiter un attaquant sans pénaliser tout un cybercafé/
        // réseau partagé qui tenterait des comptes différents (pattern recommandé Laravel).
        RateLimiter::for('login', function ($request) {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Phase D — même logique pour limiter la création de comptes et les demandes de
        // réinitialisation de mot de passe (abus/énumération d'e-mails).
        RateLimiter::for('register', fn ($request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('password-reset', function ($request) {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(3)->by($key);
        });

        // SPA : le lien de réinitialisation doit pointer vers une page React (qui recueille
        // le nouveau mot de passe et appelle POST /api/reset-password), jamais vers une route
        // Blade côté Laravel — cahier des charges §19 ("ne jamais mettre de clé/route interne
        // exposée au mauvais endroit") appliqué par analogie à l'architecture SPA.
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

            return "{$frontendUrl}/reset-password?token={$token}&email=".urlencode($user->getEmailForPasswordReset());
        });
    }
}

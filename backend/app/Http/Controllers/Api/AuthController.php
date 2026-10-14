<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Authentification SPA via Laravel Sanctum (cookie httpOnly + CSRF).
 * Flux : GET /sanctum/csrf-cookie -> POST /api/login -> GET /api/user.
 * Phase D : inscription, mot de passe oublié/réinitialisation, vérification d'e-mail.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        $request->session()->regenerate();

        return new UserResource(Auth::guard('web')->user()->load(['farmer', 'managedCooperative']));
    }

    /**
     * Inscription publique. Rôle toujours forcé à 'farmer' (le moins privilégié) — jamais
     * lu depuis la requête, quelle que soit la valeur envoyée (§17 — mass assignment).
     * Un compte auto-inscrit n'est rattaché à aucune fiche Agriculteur/coopérative tant
     * qu'un responsable ne l'a pas explicitement lié (voir FarmerController, champ user_id).
     */
    public function register(RegisterRequest $request): UserResource
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => 'farmer',
        ]);

        event(new Registered($user));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['farmer', 'managedCooperative']));
    }

    /**
     * Toujours la même réponse générique, que l'e-mail existe ou non (§17 — ne jamais
     * permettre l'énumération de comptes via un message d'erreur différent).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return response()->json([
            'message' => "Si un compte existe avec cette adresse, un lien de réinitialisation vient d'être envoyé.",
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }

    /**
     * Lien signé reçu par e-mail (Illuminate\Auth\Notifications\VerifyEmail). Ne nécessite
     * pas de session active : la signature cryptographique de l'URL (id + hash + expiration)
     * suffit à authentifier la demande — un utilisateur peut cliquer depuis un autre appareil.
     * Redirige vers le frontend (SPA) plutôt que de renvoyer du JSON, car l'utilisateur arrive
     * ici directement depuis son client de messagerie.
     */
    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return redirect(rtrim((string) config('app.frontend_url'), '/').'/email-verified');
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Adresse e-mail déjà vérifiée.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'E-mail de vérification renvoyé.']);
    }
}

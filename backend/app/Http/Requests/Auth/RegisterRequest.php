<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Inscription publique (Phase D). Le rôle n'est JAMAIS accepté ici : un compte auto-inscrit
 * reçoit systématiquement le rôle le moins privilégié ('farmer'), forcé côté serveur dans
 * AuthController::register() — jamais depuis les données du formulaire (cahier des charges
 * §17 — protection contre le mass assignment / l'élévation de privilèges).
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification du profil de l'utilisateur connecté (Phase D). `role` n'apparaît volontairement
 * jamais dans ces règles : un utilisateur ne peut jamais changer son propre rôle via ce
 * formulaire, quelle que soit la valeur envoyée (cahier des charges §17 — mass assignment).
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // toujours sur son propre compte (voir ProfileController)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
        ];
    }
}

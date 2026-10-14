<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * Gestion du profil du compte connecté (Phase D, §9). Toujours restreint à
 * `$request->user()` — jamais un id fourni par le client (auth:sanctum suffit ici, pas de
 * Policy nécessaire : un utilisateur ne peut agir que sur son propre compte).
 */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $emailChanged = $request->validated('email') !== $user->email;

        $user->fill($request->validated());

        if ($emailChanged) {
            // Un changement d'adresse invalide la vérification précédente : on ne peut pas
            // garantir que la nouvelle adresse appartient réellement à l'utilisateur tant
            // qu'elle n'a pas, elle aussi, été vérifiée (§17).
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return new UserResource($user->load(['farmer', 'managedCooperative']));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return response()->json(['message' => 'Mot de passe modifié avec succès.']);
    }
}

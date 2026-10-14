<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

/**
 * Convertit une violation de contrainte de clé étrangère (ex. suppression d'une donnée de
 * référence encore utilisée) en réponse JSON 409 propre, plutôt que de laisser fuiter
 * l'exception SQL brute (règle §17 — gestion cohérente des erreurs).
 */
trait HandlesReferentialIntegrity
{
    protected function deleteOrConflict(Closure $delete, string $message): JsonResponse
    {
        try {
            $delete();
        } catch (QueryException $exception) {
            // PostgreSQL : 23503 = foreign_key_violation (NO ACTION), 23001 = restrict_violation
            // (ON DELETE RESTRICT, utilisé sur nos FK de référentiel). Vérifié empiriquement
            // sur PostgreSQL 18 — ne pas supposer un seul code sans le confirmer.
            if (in_array((string) $exception->getCode(), ['23503', '23001'], true)) {
                return response()->json(['message' => $message], 409);
            }

            throw $exception;
        }

        return response()->json(null, 204);
    }
}

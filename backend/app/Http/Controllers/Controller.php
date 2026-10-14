<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Taille de page bornée (Phase 13 — sécurité/performance) : un client ne peut pas demander
     * un per_page arbitrairement grand et forcer une requête coûteuse sur toute la table.
     */
    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $requested = (int) $request->query('per_page', $default);

        return max(1, min($requested, $max));
    }
}

<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // SPA React (Vite) : origine explicite obligatoire, incompatible avec '*' dès que
    // supports_credentials = true (contrainte des navigateurs, pas de Laravel).
    'allowed_origins' => explode(',', env('FRONTEND_URLS', 'http://localhost:5173,http://127.0.0.1:5173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Obligatoire pour Sanctum SPA (cookie de session httpOnly cross-origin :5173 → :8000) —
    // audit du 2026-08-29 : ce fichier avait régressé à `false`, ce qui aurait empêché toute
    // connexion réelle en navigateur malgré des tests PHPUnit verts (actingAs() contourne CORS,
    // qui est un mécanisme appliqué par le navigateur, jamais exercé par les tests HTTP internes).
    'supports_credentials' => true,

];

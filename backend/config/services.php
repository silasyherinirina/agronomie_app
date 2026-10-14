<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Service IA indépendant (FastAPI + scikit-learn) — Phase 10.
    'ai' => [
        'base_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 10),
    ],

    /*
    | Module L — Météo (Phase 11/H). Open-Meteo (https://open-meteo.com) : API publique, sans
    | clé, données réelles. Fournisseur volontairement configurable (URL de base + TTL de
    | cache) pour permettre de changer de fournisseur plus tard sans modifier le code appelant —
    | cahier des charges §19 : "le fournisseur doit être configurable".
    */
    'weather' => [
        'base_url' => env('WEATHER_PROVIDER_URL', 'https://api.open-meteo.com/v1/forecast'),
        // Endpoint distinct d'Open-Meteo dédié aux données historiques (réanalyse ERA5), utilisé
        // uniquement pour le conseil agronomique quand la date demandée est dans le passé — le
        // point ci-dessus ('base_url', prévisions + conditions actuelles) reste inchangé et
        // continue d'alimenter la page Météo existante (WeatherForecastController).
        'archive_url' => env('WEATHER_ARCHIVE_URL', 'https://archive-api.open-meteo.com/v1/archive'),
        'timeout' => (int) env('WEATHER_PROVIDER_TIMEOUT', 8),
        // Délai propre aux appels du conseil agronomique (archive + fenêtres élargies) :
        // observé empiriquement plus lent que le simple /forecast (jusqu'à ~6s de latence
        // réseau constatée) — distinct de 'timeout' ci-dessus pour ne rien changer au
        // comportement de la page Météo existante (WeatherForecastController::forecast()).
        'advice_timeout' => (int) env('WEATHER_ADVICE_TIMEOUT', 15),
        'cache_ttl' => (int) env('WEATHER_CACHE_TTL', 1800), // 30 min : évite d'interroger le fournisseur à chaque affichage.
    ],

];

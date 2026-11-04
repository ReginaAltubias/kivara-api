<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Origens permitidas por ambiente: CORS_ALLOWED_ORIGINS (lista separada por virgulas) ou,
    // por omissao, FRONTEND_URL + origens de desenvolvimento/app movel (Capacitor).
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        implode(',', [
            env('FRONTEND_URL', 'http://localhost:8080'),
            'http://localhost:8080',
            'http://127.0.0.1:8080',
            'https://kivara-psi.vercel.app',
            'https://localhost',
            'capacitor://localhost',
        ])
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

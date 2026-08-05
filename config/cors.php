<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'public/api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('CORS_ALLOWED_ORIGINS')
        ? array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS')))))
        : ['http://localhost:5173', 'http://127.0.0.1:5173'],

    'allowed_origins_patterns' => env('CORS_ALLOWED_ORIGINS_PATTERNS')
        ? array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS')))
        : [],


    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
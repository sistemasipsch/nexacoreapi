<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'public/api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))),

    'allowed_origins_patterns' => env('CORS_ALLOWED_ORIGINS_PATTERNS')
        ? array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS')))
        : (in_array('*', array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '')))) ? ['.*'] : []),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
<?php

return [
    'paths' => [
        'api/*',
        'docs',
        'api/documentation',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('CORS_ALLOWED_ORIGIN', 'https://manager.befu.ro'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
<?php

return [

    'paths' => [
        'api/*',
        'auth/*',
        'oauth/*',
        'broadcasting/auth',
        ''
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_unique([
        env('FRONTEND_URL'),
        'http://localhost:5173',
        'http://localhost:5174',
        'https://zbc.maktechlaravel.cloud',
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
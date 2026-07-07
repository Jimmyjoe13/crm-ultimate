<?php

return [
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/owl-alpha'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 30),
    ],

    'emelia' => [
        'key' => env('EMELIA_API_KEY'),
        'webhook_secret' => env('EMELIA_WEBHOOK_SECRET'),
        'base_url' => env('EMELIA_BASE_URL', 'https://api.emelia.io'),
        'timeout' => (int) env('EMELIA_TIMEOUT', 15),
    ],

    // Tracking d'ouverture des cold emails de Juliette (pixel first-party hébergé sur le CRM).
    // Secret PARTAGÉ avec le mailer de la flotte (config/acquisition.env → TRACKING_SECRET).
    'tracking' => [
        'secret' => env('TRACKING_SECRET'),
    ],
];

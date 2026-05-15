<?php

return [
    'secret' => env('JWT_SECRET'),
    'ttl_minutes' => (int) env('JWT_TTL_MINUTES', 60),
    'issuer' => env('APP_URL', 'http://localhost'),
];

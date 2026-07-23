<?php

return [
    /*
    | Stripe stays opt-in. MoroRide can run cash/local demos without keys, and
    | live mode must only be enabled for a legally supported Stripe business.
    */
    'enabled' => (bool) env('STRIPE_ENABLED', false),
    'mode' => env('STRIPE_MODE', 'test'),
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
    'currency' => strtolower(env('STRIPE_CURRENCY', 'mad')),

    'points' => [
        'min_topup' => (float) env('STRIPE_POINTS_MIN_TOPUP', 20),
        'max_topup' => (float) env('STRIPE_POINTS_MAX_TOPUP', 5000),
        'points_per_currency_unit' => (float) env('STRIPE_POINTS_PER_CURRENCY_UNIT', 1),
    ],

    'connect' => [
        'enabled' => (bool) env('STRIPE_CONNECT_ENABLED', false),
        'country' => strtoupper(env('STRIPE_CONNECT_COUNTRY', '')),
        'refresh_url' => env('STRIPE_CONNECT_REFRESH_URL', 'http://localhost:8081/payments/connect/refresh'),
        'return_url' => env('STRIPE_CONNECT_RETURN_URL', 'http://localhost:8081/payments/connect/return'),
    ],
];

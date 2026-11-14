<?php

return [
    'gmail' => [
        'enabled' => env('ENABLE_EMAIL_NOTIFICATIONS', true),
        'user' => env('GMAIL_USER', 'meramotbd.official@gmail.com'),
        'from' => env('GMAIL_FROM', 'Meramot Official <meramotbd.official@gmail.com>'),
        'client_id' => env('GMAIL_CLIENT_ID', ''),
        'client_secret' => env('GMAIL_CLIENT_SECRET', ''),
        'refresh_token' => env('GMAIL_REFRESH_TOKEN', ''),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', env('GMAIL_CLIENT_ID', '')),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', env('GMAIL_CLIENT_SECRET', '')),
        'redirect' => env('GOOGLE_REDIRECT_URI', ''),
    ],

    'bulksms' => [
        'api_key' => env('BULKSMS_API_KEY', ''),
        'sender_id' => env('BULKSMS_SENDER_ID', '8809648908088'),
        'simulate' => env('BULKSMS_SIMULATE', false),
    ],

    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID', 'testbox'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', 'testbox_live'),
        'is_sandbox' => env('SSLCOMMERZ_IS_SANDBOX', true),
        'live_redirect' => env('SSLCOMMERZ_LIVE_REDIRECT', false),
    ],

    'ai_microservice' => [
        'url' => env('AI_MICROSERVICE_URL', 'http://127.0.0.1:5000'),
        'timeout' => (float) env('AI_MICROSERVICE_TIMEOUT', 4.0),
    ],
];

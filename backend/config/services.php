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
    ],
];

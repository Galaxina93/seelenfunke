<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'model'  => App\Models\System\SystemUser::class, // Lass es drin, schadet nicht, falls du später Cashier nutzt
        'key'    => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook' => [  // <--- Verschachteltes Array
            'secret' => env('STRIPE_WEBHOOK_SECRET'),
            'tolerance' => 300,
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),

        'places_key' => env('GOOGLE_PLACES_API_KEY'),
        'place_id' => env('GOOGLE_PLACE_ID'),
    ],

    'mittwald' => [
        'url' => env('MITTWALD_AI_URL', 'https://llm.aihosting.mittwald.de/v1'),
        'key' => env('MITTWALD_AI_API_KEY'),
    ],

    'gemini' => [
        'url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/openai/'),
        'key' => env('GEMINI_API_KEY'),
        'proxy_ws_url' => env('GEMINI_PROXY_WS_URL'),
    ],

    'ai' => [
        'python_binary' => env('PYTHON_BINARY', 'python3'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID', 'funkiapp-55960'),
        'credentials_file' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-service-account.json')),
    ],

    'newsapi' => [
        'key' => env('NEWS_API_KEY'),
    ],

    'mapbox' => [
        'token' => env('MAPBOX_TOKEN'),
    ],

    'dhl' => [
        'api_key' => env('DHL_API_KEY'),
        'api_user' => env('DHL_API_USER'),
        'api_signature' => env('DHL_API_SIGNATURE'),
        'ekp' => env('DHL_EKP', '3333333333'),
        'sandbox' => env('DHL_SANDBOX', true),
        'tracking_api_key' => env('DHL_TRACKING_API_KEY', env('DHL_API_KEY')),
    ],

    'finapi' => [
        'client_id' => env('FINAPI_CLIENT_ID'),
        'client_secret' => env('FINAPI_CLIENT_SECRET'),
        'env' => env('FINAPI_ENV', 'live'),
    ],

    'eric' => [
        'test_mode' => env('ERIC_TEST_MODE', true),
        'use_native_binary' => env('ERIC_USE_NATIVE_BINARY'),
        'api_endpoint' => env('ERIC_API_ENDPOINT', 'https://api.elster-sandbox.local/v1/transmit'),
        'api_username' => env('ERIC_API_USERNAME', ''),
        'api_password' => env('ERIC_API_PASSWORD', ''),
        'tresor_path' => env('ERIC_TRESOR_PATH', 'storage/app/buchhaltung/erictresor'),
    ],

    'scraperapi' => [
        'key' => env('SCRAPER_API_KEY'),
    ],

    'system' => [
        'cron_secret' => env('SYSTEM_CRON_SECRET'),
        'cache_secret' => env('SYSTEM_CACHE_SECRET'),
    ],

];



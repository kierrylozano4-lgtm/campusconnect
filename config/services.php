<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://127.0.0.1:8000').'/auth/google/callback'),
    ],

    'gcash' => [
        'simulation_enabled' => filter_var(env('GCASH_SIMULATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'environment' => env('GCASH_ENVIRONMENT', 'sandbox'),
        'currency' => env('GCASH_CURRENCY', 'PHP'),
        'callback_url' => env('GCASH_CALLBACK_URL'),
    ],

    'maya' => [
        'enabled' => filter_var(env('MAYA_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'environment' => env('MAYA_ENVIRONMENT', 'sandbox'),
        'public_key' => env('MAYA_PUBLIC_KEY'),
        'secret_key' => env('MAYA_SECRET_KEY'),
        'checkout_endpoint' => env('MAYA_CHECKOUT_ENDPOINT'),
        'base_url' => env('MAYA_BASE_URL'),
        'webhook_public_key_1' => env('MAYA_WEBHOOK_PUBLIC_KEY_1'),
        'webhook_public_key_2' => env('MAYA_WEBHOOK_PUBLIC_KEY_2'),
        'currency' => env('MAYA_CURRENCY', 'PHP'),
        'callback_url' => env('MAYA_CALLBACK_URL'),
    ],

    'weather' => [
        'api_key' => env('OPENWEATHER_API_KEY'),
        'city' => env('OPENWEATHER_CITY', 'Manila'),
        'geocoding_endpoint' => 'https://api.openweathermap.org/geo/1.0/direct',
        'current_endpoint' => 'https://api.openweathermap.org/data/2.5/weather',
        'verify_ssl' => true,
        'connect_timeout' => 10,
        'timeout' => 15,
    ],

];

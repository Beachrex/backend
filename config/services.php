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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'openweather' => [
        'api_key' => env('OPENWEATHER_API_KEY'),
    ],

    // Sea surface temperature. Without a key the free endpoint is used (non-commercial use only).
    'open_meteo' => [
        'api_key' => env('OPEN_METEO_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // Cloudflare Turnstile on the AI chat (owner 2026-10-29). No secret = verification off (local, tests, until
    // the owner adds the widget). required_for: 'web' = only browser requests (Origin / Sec-Fetch-* headers; the
    // mobile app sends neither), 'all' = every client (breaks API clients that cannot run Turnstile).
    'turnstile' => [
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'required_for' => env('TURNSTILE_REQUIRED_FOR', 'web'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'timeout' => 5, // seconds
    ],

];

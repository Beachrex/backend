<?php

/*
| Mobile app settings served by GET /api/v1/app-config (B-M1, TASK-005). The app checks its own version against
| min_supported_version on start and shows a "please update" screen below it; latest_version drives a soft
| "update available" hint. Values come from the server env so a release needs no backend deploy.
*/

return [
    'ios' => [
        'min_supported_version' => env('MOBILE_IOS_MIN_VERSION', '1.0.0'),
        'latest_version' => env('MOBILE_IOS_LATEST_VERSION', '1.0.0'),
        'store_url' => env('MOBILE_IOS_STORE_URL'),
    ],
    'android' => [
        'min_supported_version' => env('MOBILE_ANDROID_MIN_VERSION', '1.0.0'),
        'latest_version' => env('MOBILE_ANDROID_LATEST_VERSION', '1.0.0'),
        'store_url' => env('MOBILE_ANDROID_STORE_URL'),
    ],
    // Most beaches one account can save (GET/POST /api/v1/me/favorites).
    'favorites_limit' => (int) env('FAVORITES_LIMIT', 500),

    // Feature flags the app reads before showing a feature (owner decisions, MOBILE-TASKS M4.x).
    'features' => [
        'ai_chat' => (bool) env('MOBILE_FEATURE_AI_CHAT', false),
        'itinerary' => (bool) env('MOBILE_FEATURE_ITINERARY', false),
        'favorites' => (bool) env('MOBILE_FEATURE_FAVORITES', false),
        'native_sign_in' => (bool) env('MOBILE_FEATURE_NATIVE_SIGN_IN', false),
    ],
];

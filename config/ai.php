<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Beachrex AI Agent service.
    | Based on docs/ai/architecture/api-contracts.md
    |
    */

    /*
    |--------------------------------------------------------------------------
    | LLM Provider Settings
    |--------------------------------------------------------------------------
    */

    'provider' => env('AI_PROVIDER', 'openai'), // 'openai' or 'anthropic'

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION', null),
            'default_model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
            'timeout' => env('OPENAI_TIMEOUT', 30), // seconds
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'default_model' => env('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 30), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tier Configuration
    |--------------------------------------------------------------------------
    |
    | Plan tiers and their limits.
    | Source: docs/ai/architecture/api-contracts.md Section 3.2
    |
    */

    'tiers' => [
        'free' => [
            'name' => 'Free Tier',
            'rate_limit' => [
                'requests_per_hour' => 100, // Per IP for anonymous
                'requests_per_day' => 500,
            ],
            'token_budget' => [
                'max_tokens_per_request' => 4000,
                'max_tokens_per_session' => 16000,
            ],
            'features' => [
                'beach_search' => true,
                'beach_comparison' => true,
                'beach_comparison_max' => 3, // Max 3 beaches
                'current_weather' => true,
                'general_questions' => true,
                'single_day_suggestions' => true,
                // Paid features (blocked for free tier)
                'multi_day_itineraries' => false,
                'accessibility_reports' => false,
                'activity_guides' => false,
                'multi_day_weather' => false,
            ],
        ],
        'basic' => [
            'name' => 'Basic Tier',
            'rate_limit' => [
                'requests_per_hour' => 500, // Per user
                'requests_per_day' => 2000,
            ],
            'token_budget' => [
                'max_tokens_per_request' => 5,
                'max_tokens_per_session' => 64000,
            ],
            'features' => [
                'beach_search' => true,
                'beach_comparison' => true,
                'beach_comparison_max' => 10,
                'current_weather' => true,
                'general_questions' => true,
                'single_day_suggestions' => true,
                'multi_day_itineraries' => true,
                'accessibility_reports' => true,
                'activity_guides' => true,
                'multi_day_weather' => true,
            ],
        ],
        'premium' => [
            'name' => 'Premium Tier',
            'rate_limit' => [
                'requests_per_hour' => 1000,
                'requests_per_day' => 5000,
            ],
            'token_budget' => [
                'max_tokens_per_request' => 32000,
                'max_tokens_per_session' => 128000,
            ],
            'features' => [
                'beach_search' => true,
                'beach_comparison' => true,
                'beach_comparison_max' => 20,
                'current_weather' => true,
                'general_questions' => true,
                'single_day_suggestions' => true,
                'multi_day_itineraries' => true,
                'accessibility_reports' => true,
                'activity_guides' => true,
                'multi_day_weather' => true,
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise Tier',
            'rate_limit' => [
                'requests_per_hour' => 5000,
                'requests_per_day' => 20000,
            ],
            'token_budget' => [
                'max_tokens_per_request' => 64000,
                'max_tokens_per_session' => 256000,
            ],
            'features' => [
                'beach_search' => true,
                'beach_comparison' => true,
                'beach_comparison_max' => 50,
                'current_weather' => true,
                'general_questions' => true,
                'single_day_suggestions' => true,
                'multi_day_itineraries' => true,
                'accessibility_reports' => true,
                'activity_guides' => true,
                'multi_day_weather' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breakers
    |--------------------------------------------------------------------------
    |
    | Cost-based circuit breakers to prevent runaway costs.
    | Source: docs/ai/architecture/api-contracts.md Section 3.4
    |
    */

    'circuit_breakers' => [
        'free_tier_daily_limit' => env('AI_FREE_TIER_DAILY_LIMIT', 500.00), // USD
        'emergency_shutdown_limit' => env('AI_EMERGENCY_SHUTDOWN_LIMIT', 5000.00), // USD
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */

    'session' => [
        'ttl' => 30, // minutes
        'max_messages' => 50, // Max messages per session
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Configuration
    |--------------------------------------------------------------------------
    |
    | Supported locales for AI responses.
    | Source: docs/ai/architecture/api-contracts.md Section 4.1
    |
    */

    'locales' => [
        'en', 'hr', 'sl', 'de', 'it', 'fr', 'es', 'pt', 'nl', 'pl', 'cs', 'sk', 'hu', 'ro',
    ],

    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Pricing for paid features (used in 402 responses).
    | Source: docs/ai/ai-monetization.md
    |
    */

    'pricing' => [
        'itinerary' => [
            3 => 19.00,  // 3-day itinerary: $19
            7 => 39.00,  // 7-day itinerary: $39
            14 => 59.00, // 14-day itinerary: $59
        ],
        'accessibility_report' => 15.00,
        'activity_guide' => 25.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */

    'features' => [
        'enabled' => env('AI_ENABLED', true),
        'free_tier_enabled' => env('AI_FREE_TIER_ENABLED', true),
        'streaming' => env('AI_STREAMING_ENABLED', false), // Not implemented in MVP
    ],

];


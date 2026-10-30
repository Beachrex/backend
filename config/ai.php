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
            'default_model' => env('OPENAI_MODEL', 'gpt-4o-mini'), // ADR 2026-10-07
            'timeout' => env('OPENAI_TIMEOUT', 30), // seconds
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            // Used only when AI_PROVIDER=anthropic (claude-3-haiku, the old default, is retired).
            'default_model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 30), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data quality pass (`php artisan content:ai`, owner 2026-10-09)
    |--------------------------------------------------------------------------
    | Beach names without the English "Beach", region and description translations and
    | generated descriptions, sent as Message Batches. Needs ANTHROPIC_API_KEY.
    */
    'content' => [
        'model' => env('AI_CONTENT_MODEL', 'claude-opus-5-5'),
        // Translation needs little thinking; raise only if the preview shows weak results.
        'effort' => env('AI_CONTENT_EFFORT', 'low'),
        // TASK-007: the most viewed beaches get a longer intro, more area context and five FAQs.
        'featured' => (int) env('AI_CONTENT_FEATURED', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Website chat engine (AI-2, owner 2026-10-09)
    |--------------------------------------------------------------------------
    | 'pipeline' = TASK-003 understanding + one answer call (default until the evaluation is done);
    | 'agent'    = the model calls the beach tools like the MCP server (App\Services\AI\Agent\ChatAgent).
    | Provider 'openai' or 'anthropic' (needs ANTHROPIC_API_KEY). Compare with `php artisan ai:chat-eval`.
    */
    'chat' => [
        'engine' => env('AI_CHAT_ENGINE', 'pipeline'),
        'provider' => env('AI_CHAT_PROVIDER', 'openai'),
        'openai_model' => env('AI_CHAT_OPENAI_MODEL', env('OPENAI_MODEL', 'gpt-4o-mini')),
        'anthropic_model' => env('AI_CHAT_ANTHROPIC_MODEL', 'claude-opus-5-5'),
        // Claude effort (low/medium/high): chat answers rarely gain from more thinking; measure before raising.
        'effort' => env('AI_CHAT_EFFORT', 'low'),
        'max_tool_rounds' => (int) env('AI_CHAT_MAX_TOOL_ROUNDS', 4),
        // Owner 2026-10-29 ("hramba chatov ok"): conversations are deleted this many days after their last activity
        // by the daily `ai:prune-conversations` (routes/console.php). 0 = the scheduled job does not run.
        'retention_days' => (int) env('AI_CHAT_RETENTION_DAYS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trip planner narration model (owner 2026-10-30)
    |--------------------------------------------------------------------------
    | Used when nothing is picked in admin (AI → AI models). Must be an AiModelCatalog id; if its provider has no
    | API key in this environment, the planner falls back to AI_PROVIDER / OPENAI_MODEL.
    */
    'itinerary' => [
        'model' => env('AI_ITINERARY_MODEL', 'claude-opus-5-5'),
        // One narration call with Claude or a reasoning OpenAI model (they think first; a 14-day plan can take over a
        // minute). Runs in the queue worker: job timeout 240 s, two attempts, so keep this under 115.
        'timeout' => (int) env('AI_ITINERARY_TIMEOUT', 110), // seconds
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
                'max_tokens_per_request' => 16000,
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

    // Owner 2026-10-29: free chat $5/day, then off until the next day (ADR "AI chat daily cost cap and Turnstile").
    'circuit_breakers' => [
        // Free AI chat (all usage except the paid trip planner). Back on automatically at local midnight.
        'free_tier_daily_limit' => (float) env('AI_FREE_TIER_DAILY_LIMIT', 5.00), // USD
        // Everything together, including the paid trip planner (emergency stop for all AI).
        'emergency_shutdown_limit' => (float) env('AI_EMERGENCY_SHUTDOWN_LIMIT', 20.00), // USD
        // Whose calendar day the limits count: the owner's (Slovenia), so the chat comes back at local midnight.
        'timezone' => env('AI_DAILY_LIMIT_TIMEZONE', 'Europe/Ljubljana'),
        // One e-mail per day when a limit trips; empty = log only.
        'alert_email' => env('AI_ALERT_EMAIL'),
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
        'en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq',
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
    | Stripe Configuration
    |--------------------------------------------------------------------------
    |
    | Stripe product and price mapping for itinerary purchases.
    | Price IDs are stored in environment variables for security.
    |
    */

    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // AI-4: version of the terms of sale the buyer accepts at checkout (stored on each Stripe session).
        'terms_version' => env('STRIPE_TERMS_VERSION', '2026-10-09'),
        // VAT by the buyer's country via Stripe Tax; needs Stripe Tax enabled and prices with a tax behaviour.
        'tax_enabled' => (bool) env('STRIPE_TAX_ENABLED', false),
        // Stripe issues and e-mails an invoice for each payment (Stripe invoicing fee applies).
        'invoices_enabled' => (bool) env('STRIPE_INVOICES_ENABLED', false),

        'products' => [
            'itinerary_3day' => [
                'price_id' => env('STRIPE_PRICE_ITINERARY_3DAY', env('STRIPE_PRODUCT_ITINERARY_3DAY_PRICE_ID')),
                'amount' => 19.00,
                'currency' => 'USD',
                'credits' => 1,
                'name' => '3-Day Beach Itinerary',
            ],
            'itinerary_7day' => [
                'price_id' => env('STRIPE_PRICE_ITINERARY_7DAY', env('STRIPE_PRODUCT_ITINERARY_7DAY_PRICE_ID')),
                'amount' => 39.00,
                'currency' => 'USD',
                'credits' => 1,
                'name' => '7-Day Beach Itinerary',
            ],
            'itinerary_14day' => [
                'price_id' => env('STRIPE_PRICE_ITINERARY_14DAY', env('STRIPE_PRODUCT_ITINERARY_14DAY_PRICE_ID')),
                'amount' => 59.00,
                'currency' => 'USD',
                'credits' => 1,
                'name' => '14-Day Beach Itinerary',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */

    'payments_enabled' => env('AI_PAYMENTS_ENABLED', false),

    'features' => [
        'enabled' => env('AI_ENABLED', true),
        'free_tier_enabled' => env('AI_FREE_TIER_ENABLED', true),
        'streaming' => env('AI_STREAMING_ENABLED', false), // Not implemented in MVP
    ],

];


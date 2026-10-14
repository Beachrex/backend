<?php

/*
 * TASK-006 Windy webcams. Every matching weight and threshold lives here (ADR 2026-10-14).
 * Category ids are Windy's fixed list: airport beach building city coast forest indoor lake landscape
 * meteo mountain observatory port river sportArea square traffic village (docs/technical/windy-webcams-api.md).
 */
return [

    'api' => [
        'base_url' => env('WINDY_API_BASE_URL', 'https://api.windy.com/webcams/api/v3'),
        'key' => env('WINDY_API_KEY'),
        'daily_request_budget' => (int) env('WINDY_DAILY_REQUEST_BUDGET', 500),
        'timeout' => 20,
    ],

    'import' => [
        // Windy categories fetched by webcams:import (categoryOperation=or). Pools/spas will need
        // indoor/sportArea once such places are in the DB.
        'categories' => ['beach', 'coast', 'lake', 'river', 'port'],
        // Rate for the public player check (webcams:embed-check), not the API.
        'embed_check_delay_ms' => 1000,
        'embed_check_stale_days' => 30,
    ],

    'player_base_url' => 'https://webcams.windy.com/webcams/public/embed/player',
    'public_url' => 'https://www.windy.com/webcams/{id}',

    'matching' => [
        // Owner, 2026-10-14: 3 km (proposed 5 km).
        'max_radius_m' => (int) env('WEBCAMS_MAX_RADIUS_M', 3000),

        // Distance bands, checked in order: first band whose limit is >= distance wins.
        'distance_points' => [
            300 => 40,
            1000 => 25,
            3000 => 10,
        ],

        'name_match_points' => 30,
        'name_fuzzy_ratio' => 0.85,
        'town_match_points' => 5,

        'auto_approve_min' => 70,
        'auto_approve_margin' => 15,
        'pending_min' => 40,

        // A webcam must render in the public player before it is matched or shown.
        'require_embed_ok' => true,
    ],

    // Place-type profiles. `waterbody_ids` picks the profile (waterbody: 1 river, 2 pool, 3 sea, 4 lake);
    // `default` is used when nothing else matches. Category points: the highest positive and the
    // lowest negative apply once each (no stacking).
    'profiles' => [
        'beach' => [
            'default' => true,
            'waterbody_ids' => [1, 3, 4],
            'category_points' => [
                'beach' => 25,
                'coast' => 10, 'lake' => 10, 'river' => 10, 'port' => 10,
                'city' => -20, 'traffic' => -20, 'mountain' => -20, 'airport' => -20,
                'indoor' => -20, 'building' => -20, 'square' => -20,
            ],
            'stopwords' => [
                'plaza', 'plaz', 'beach', 'beaches', 'plage', 'spiaggia', 'strand', 'playa', 'praia', 'platja',
                'paralia', 'plazh', 'plaja', 'lido', 'bagno', 'bagni', 'uvala', 'cove', 'bay', 'baia',
                'the', 'la', 'le', 'les', 'il', 'el', 'de', 'del', 'della', 'di', 'da', 'do', 'des', 'du', 'na', 'u', 'v', 'pri',
            ],
        ],
        'pool_spa' => [
            'waterbody_ids' => [2],
            'category_points' => [
                'indoor' => 10, 'sportArea' => 10, 'building' => 5,
                'traffic' => -20, 'mountain' => -20, 'airport' => -20,
            ],
            'stopwords' => [
                'kopalisce', 'kopaliste', 'bazen', 'bazeni', 'terme', 'toplice', 'therme', 'thermal', 'spa', 'wellness',
                'aquapark', 'aqualand', 'piscina', 'piscine', 'freibad', 'hallenbad', 'schwimmbad', 'bad', 'pool', 'pools',
                'the', 'la', 'le', 'il', 'de', 'di', 'na', 'pri', 'v',
            ],
        ],
    ],
];

<?php

/*
 * Operations: performance report and alert (php artisan ops:perf-report; runbook docs/runbooks/performance-and-bots.md).
 */
return [
    // PHP-FPM pool status (JSON) through web_<env>:8080, reachable only inside the Docker network
    // (deploy/nginx/api.<env>.conf). Set per environment in deploy/docker-compose.vps.yml.
    'fpm_status_url' => env('OPS_FPM_STATUS_URL'),
    'fpm_health_status_url' => env('OPS_FPM_HEALTH_STATUS_URL'),

    // Where `ops:perf-report --alert` (scheduled every 10 minutes) sends a warning; empty = log only.
    'alert_email' => env('OPS_ALERT_EMAIL'),

    'alert' => [
        // PHP-FPM requests waiting for a free worker at the moment of the check.
        'fpm_queue' => (int) env('OPS_ALERT_FPM_QUEUE', 5),
        // Redis keys evicted since the last check while Redis is at >= 95 % of maxmemory.
        'redis_evictions' => (int) env('OPS_ALERT_REDIS_EVICTIONS', 5000),
    ],
];

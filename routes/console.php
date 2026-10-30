<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily database backup to the private backups bucket (see config/backup.php).
// Runs in the scheduler_<env> container (`php artisan schedule:work`).
Schedule::command('db:backup')
    ->dailyAt(config('backup.time'))
    ->withoutOverlapping()
    ->when(fn () => (bool) config('backup.enabled'));

// AI-3: background itineraries the worker never finished fail and give their credit back.
Schedule::call(fn () => app(\App\Services\AI\Itinerary\ItineraryPlanService::class)->failStale())
    ->name('itinerary-plans:fail-stale')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// TASK-006: Windy webcam refresh. Daily, but it only re-imports every 30 days or resumes a run that hit the
// request budget. Off until WEBCAMS_REFRESH_ENABLED=true (after the owner's first manual import).
Schedule::command('webcams:refresh')
    ->dailyAt('04:20')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('webcams.refresh.enabled') && (bool) config('webcams.api.key'));

// TASK-006: Windy owners can switch embedding off at any time ("not publicly available" in the player), so the
// webcams shown on our pages are re-checked daily in the public player (no API key, ~1 request/s).
Schedule::command('webcams:embed-check --shown')
    ->dailyAt('05:10')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('webcams.import.embed_recheck_enabled'));

// 360° tours (owner 2026-10-29): record which beaches have a generated krpano tour, so the API can send the panorama
// to the website's own viewer instead of a missing tour. New beaches daily; everyone once a week.
Schedule::command('media:check-vtours')
    ->dailyAt('04:40')
    ->withoutOverlapping();
Schedule::command('media:check-vtours --all')
    ->weeklyOn(0, '04:50')
    ->withoutOverlapping();

// AI chat retention (owner 2026-10-29, "hramba chatov ok"): conversations whose last activity is older than
// AI_CHAT_RETENTION_DAYS (180) are deleted with their messages; ai_usage keeps the cost without the session link.
// Night in Slovenia, before the backup (02:30 UTC) and the webcam/media jobs.
Schedule::command('ai:prune-conversations')
    ->dailyAt('01:40')
    ->timezone('Europe/Ljubljana')
    ->withoutOverlapping()
    ->when(fn () => (int) config('ai.chat.retention_days') > 0);

// Performance alert (ADR 2026-10-30 "API performance", runbook docs/runbooks/performance-and-bots.md): PHP-FPM queue
// or workers running out, Redis full and evicting → e-mail OPS_ALERT_EMAIL (at most one per hour). Off without it.
Schedule::command('ops:perf-report --alert --sample=0')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->when(fn () => (bool) config('ops.alert_email'));

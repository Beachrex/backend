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

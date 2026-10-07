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

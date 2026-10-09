<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database backups (php artisan db:backup)
    |--------------------------------------------------------------------------
    |
    | Daily pg_dump of this environment's database, uploaded to the private
    | "backups" disk (a PRIVATE bucket, never the public media bucket).
    | Scheduled by routes/console.php; run by the scheduler_<env> container.
    |
    */

    'enabled' => (bool) env('BACKUP_ENABLED', false),

    'disk' => env('BACKUP_DISK', 'backups'),

    // Folder inside the bucket. Defaults to the database name (beachrex_uat, beachrex_prod, ...).
    'prefix' => env('BACKUP_PREFIX', env('DB_DATABASE', 'beachrex')),

    // Backups older than this are deleted after a successful upload.
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // Daily run time (server time, UTC).
    'time' => env('BACKUP_TIME', '02:30'),

    // pg_dump timeout in seconds.
    'timeout' => (int) env('BACKUP_TIMEOUT', 900),

    // File holding the ISO timestamp of the last successful backup (shown by /api/v1/health).
    // Lives on the storage volume shared by api_<env> and scheduler_<env>; unlike the
    // cache it survives the `cache:clear` that every deploy runs.
    'status_file' => storage_path('app/backup-last-success.txt'),

];

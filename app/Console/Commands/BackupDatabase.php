<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Dump this environment's PostgreSQL database and upload it to the private
 * backups disk, then delete backups older than the retention period.
 *
 * Restore: docs/runbooks/restore-backup.md
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--keep-days= : Override retention (days) from config/backup.php}
        {--no-prune : Upload only, do not delete old backups}';

    protected $description = 'pg_dump the database and upload it to the private backups disk';

    public function handle(): int
    {
        $connection = config('database.connections.' . config('database.default'));

        if (($connection['driver'] ?? null) !== 'pgsql') {
            $this->error('db:backup only supports pgsql connections.');
            return self::FAILURE;
        }

        $disk = Storage::disk(config('backup.disk'));
        $prefix = trim((string) config('backup.prefix'), '/');
        $database = $connection['database'];
        $remotePath = sprintf('%s/%s-%s.dump', $prefix, $database, now()->utc()->format('Ymd-His'));
        $localPath = tempnam(sys_get_temp_dir(), 'dbbackup_');

        try {
            $this->dump($connection, $localPath);

            $size = filesize($localPath);
            $handle = fopen($localPath, 'rb');
            if ($size === 0 || fread($handle, 5) !== 'PGDMP') {
                throw new \RuntimeException('pg_dump produced an empty or invalid file.');
            }
            rewind($handle);

            $disk->writeStream($remotePath, $handle);
            fclose($handle);

            if (!$disk->exists($remotePath) || $disk->size($remotePath) !== $size) {
                throw new \RuntimeException("Upload verification failed for {$remotePath}.");
            }
        } catch (Throwable $e) {
            Log::error('db:backup failed', ['database' => $database, 'error' => $e->getMessage()]);
            $this->error('Backup failed: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            if (is_file($localPath)) {
                unlink($localPath);
            }
        }

        file_put_contents(config('backup.status_file'), now()->utc()->toIso8601String());
        Log::info('db:backup uploaded', ['path' => $remotePath, 'bytes' => $size]);
        $this->info("Uploaded {$remotePath} ({$size} bytes).");

        if (!$this->option('no-prune')) {
            $this->prune($disk, $prefix, $remotePath);
        }

        return self::SUCCESS;
    }

    private function dump(array $connection, string $localPath): void
    {
        $process = new Process(
            [
                'pg_dump',
                '--format=custom',
                '--no-owner',
                '--no-privileges',
                '--host=' . $connection['host'],
                '--port=' . $connection['port'],
                '--username=' . $connection['username'],
                '--file=' . $localPath,
                $connection['database'],
            ],
            null,
            // Password via env, never on the command line (visible in `ps`).
            ['PGPASSWORD' => (string) $connection['password']],
            null,
            (float) config('backup.timeout'),
        );

        $process->mustRun();
    }

    /**
     * Delete backups in $prefix older than the retention period. Only files
     * matching this command's naming pattern are touched.
     */
    private function prune($disk, string $prefix, string $justUploaded): void
    {
        $keepDays = (int) ($this->option('keep-days') ?? config('backup.keep_days'));
        if ($keepDays < 1) {
            $this->warn('Retention < 1 day; skipping prune.');
            return;
        }

        $cutoff = now()->utc()->subDays($keepDays);
        $deleted = 0;

        foreach ($disk->files($prefix) as $file) {
            if ($file === $justUploaded || !preg_match('/-(\d{8}-\d{6})\.dump$/', $file, $m)) {
                continue;
            }

            if (Carbon::createFromFormat('Ymd-His', $m[1], 'UTC')->lt($cutoff)) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Pruned {$deleted} backup(s) older than {$keepDays} days.");
    }
}

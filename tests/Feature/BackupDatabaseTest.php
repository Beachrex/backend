<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Runs the real pg_dump against the test database (no mocks): if pg_dump is
 * missing or cannot connect, these tests fail.
 */
class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $statusFile;

    protected function tearDown(): void
    {
        @unlink($this->statusFile);
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
        config(['backup.prefix' => 'beachrex_test', 'backup.keep_days' => 14]);
        config(['backup.status_file' => $this->statusFile = sys_get_temp_dir() . '/backup-status-' . uniqid() . '.txt']);
    }

    #[Test]
    public function it_uploads_a_valid_custom_format_dump(): void
    {
        $this->artisan('db:backup', ['--no-prune' => true])->assertSuccessful();

        $files = Storage::disk('backups')->files('beachrex_test');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('#^beachrex_test/beachrex_test-\d{8}-\d{6}\.dump$#', $files[0]);

        $contents = Storage::disk('backups')->get($files[0]);
        $this->assertStringStartsWith('PGDMP', $contents);
        $this->assertGreaterThan(10_000, strlen($contents), 'dump should contain the schema');

        $this->assertFileExists($this->statusFile);
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_ATOM, file_get_contents($this->statusFile)));
    }

    #[Test]
    public function it_prunes_only_its_own_backups_older_than_retention(): void
    {
        $disk = Storage::disk('backups');
        $old = 'beachrex_test/beachrex_test-' . now()->utc()->subDays(15)->format('Ymd-His') . '.dump';
        $recent = 'beachrex_test/beachrex_test-' . now()->utc()->subDays(13)->format('Ymd-His') . '.dump';
        $unrelated = 'beachrex_test/manual-export.sql';
        $otherEnv = 'beachrex_uat/beachrex_uat-' . now()->utc()->subDays(30)->format('Ymd-His') . '.dump';
        foreach ([$old, $recent, $unrelated, $otherEnv] as $file) {
            $disk->put($file, 'x');
        }

        $this->artisan('db:backup')->assertSuccessful();

        $this->assertFalse($disk->exists($old), 'backup older than retention must be deleted');
        $this->assertTrue($disk->exists($recent), 'backup inside retention must be kept');
        $this->assertTrue($disk->exists($unrelated), 'files not matching the backup pattern must be kept');
        $this->assertTrue($disk->exists($otherEnv), 'other environments\' backups must be kept');
        $this->assertCount(3, $disk->files('beachrex_test'));
    }

    #[Test]
    public function it_fails_loudly_and_uploads_nothing_when_pg_dump_fails(): void
    {
        $connection = config('database.default');
        config(["database.connections.{$connection}.password" => 'wrong-password']);

        $this->artisan('db:backup')->assertFailed();

        $this->assertSame([], Storage::disk('backups')->allFiles());
        $this->assertFileDoesNotExist($this->statusFile);
    }

    #[Test]
    public function it_is_scheduled_daily_only_when_enabled(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'db:backup'));

        $this->assertNotNull($event, 'db:backup must be scheduled');
        $this->assertSame('30 2 * * *', $event->expression);

        config(['backup.enabled' => false]);
        $this->assertFalse($event->filtersPass($this->app));

        config(['backup.enabled' => true]);
        $this->assertTrue($event->filtersPass($this->app));
    }
}

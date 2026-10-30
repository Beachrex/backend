<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    #[Test]
    public function it_reports_ok_with_revision_and_backup_status(): void
    {
        config(['backup.enabled' => true]);
        $statusFile = sys_get_temp_dir() . '/backup-status-' . uniqid() . '.txt';
        file_put_contents($statusFile, '2026-10-07T02:30:05+00:00');
        config(['backup.status_file' => $statusFile]);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.redis', 'ok')
            ->assertJsonPath('backup.enabled', true)
            ->assertJsonPath('backup.last_success_at', '2026-10-07T02:30:05+00:00')
            ->assertJsonStructure(['revision']);

        unlink($statusFile);
    }

    #[Test]
    public function it_returns_503_when_the_database_is_down(): void
    {
        // Health checks the database through its own persistent connection `pgsql_health` (same database).
        foreach ([config('database.default'), 'pgsql_health'] as $connection) {
            config(["database.connections.{$connection}.host" => '127.0.0.2', "database.connections.{$connection}.port" => 1]);
            DB::purge($connection);
        }

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('checks.database', 'fail');
    }

    #[Test]
    public function it_uses_the_persistent_health_connection_on_postgresql(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertTrue(config('database.connections.pgsql_health.options')[\PDO::ATTR_PERSISTENT] ?? false);
        $this->assertSame(config('database.connections.pgsql.database'), config('database.connections.pgsql_health.database'));

        DB::purge('pgsql_health');
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('checks.database', 'ok');
        $this->assertArrayHasKey('pgsql_health', DB::getConnections());
    }
}

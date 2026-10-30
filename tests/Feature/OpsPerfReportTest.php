<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** `php artisan ops:perf-report` (runbook docs/runbooks/performance-and-bots.md). */
class OpsPerfReportTest extends TestCase
{
    private function fpmStatus(int $queue, int $maxReached, int $start = 1793382890): array
    {
        return [
            'pool' => 'www', 'start time' => $start, 'accepted conn' => 1000, 'listen queue' => $queue,
            'max listen queue' => $queue, 'idle processes' => 0, 'active processes' => 16, 'total processes' => 16,
            'max active processes' => 16, 'max children reached' => $maxReached, 'slow requests' => 0,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'ops.fpm_status_url' => 'http://web_test:8080/fpm-status?json',
            'ops.fpm_health_status_url' => 'http://web_test:8080/fpm-status-health?json',
        ]);
    }

    #[Test]
    public function the_report_shows_cache_pool_and_database_numbers_as_json(): void
    {
        Http::fake(['web_test:8080/*' => Http::response($this->fpmStatus(0, 0))]);
        $this->getJson('/api/v1/continents');
        $this->getJson('/api/v1/continents');

        $this->artisan('ops:perf-report', ['--json' => true])->assertSuccessful()
            ->expectsOutputToContain('"api_cache":{"date":"'.gmdate('Ymd').'","hit":1,"miss":1,"hit_rate":"50%"},"redis":{"error":"cache store is array"},"fpm":{"pool":"www","start time":1793382890,"accepted conn":1000,"listen queue":0');
    }

    #[Test]
    public function an_unreachable_pool_or_a_non_redis_cache_is_reported_not_fatal(): void
    {
        Http::fake(['web_test:8080/*' => Http::response('down', 502)]);

        $this->artisan('ops:perf-report')->assertSuccessful()
            ->expectsOutputToContain('PHP-FPM www: RequestException')
            ->expectsOutputToContain('Redis: cache store is array');
    }

    #[Test]
    public function an_alert_is_mailed_when_php_fpm_queues_and_only_once_per_hour(): void
    {
        config(['mail.default' => 'array', 'ops.alert_email' => 'ops@example.test', 'ops.alert.fpm_queue' => 5]);

        $status = $this->fpmStatus(4, 0);
        Http::fake(['web_test:8080/*' => function () use (&$status) {
            return Http::response($status);
        }]);
        $this->artisan('ops:perf-report', ['--alert' => true])->expectsOutputToContain('No thresholds crossed.');

        $status = $this->fpmStatus(5, 3);
        $this->artisan('ops:perf-report', ['--alert' => true])
            ->expectsOutputToContain("PHP-FPM queue 5 requests waiting (active 16/16)\nPHP-FPM ran out of workers 3 more time(s) since the last check");
        $this->artisan('ops:perf-report', ['--alert' => true]);

        $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent, 'one e-mail, the second alert within the hour is only logged');
        $this->assertStringContainsString('performance alert', $sent[0]->getOriginalMessage()->getSubject());
        $this->assertSame('ops@example.test', $sent[0]->getOriginalMessage()->getTo()[0]->getAddress());
    }
}

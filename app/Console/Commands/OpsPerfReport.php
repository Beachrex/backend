<?php

namespace App\Console\Commands;

use App\Support\ApiResponseCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Performance numbers in one place (runbook docs/runbooks/performance-and-bots.md, ADR 2026-10-30 "API performance"):
 * API response cache hit rate, Redis memory/evictions and the biggest key groups of this environment, PHP-FPM pool
 * status (via web_<env>:8080, internal only), PostgreSQL activity.
 *
 *   php artisan ops:perf-report            # table for people
 *   php artisan ops:perf-report --json     # one JSON line (deploy annotation)
 *   php artisan ops:perf-report --alert    # scheduled: e-mail OPS_ALERT_EMAIL when a threshold is crossed
 *
 * Read-only except the alert bookkeeping keys. Never prints secrets or key contents.
 */
class OpsPerfReport extends Command
{
    protected $signature = 'ops:perf-report {--json : One JSON line} {--alert : E-mail OPS_ALERT_EMAIL when a threshold is crossed} {--sample=2000 : Redis keys to sample for the key groups}';

    protected $description = 'Cache hit rate, Redis memory, PHP-FPM pool and database activity (read-only)';

    private const ALERT_STATE_KEY = 'ops:alert:last';

    public function handle(): int
    {
        $report = [
            'api_cache' => $this->apiCache(),
            'redis' => $this->redis((int) $this->option('sample')),
            'fpm' => $this->fpm(config('ops.fpm_status_url')),
            'fpm_health' => $this->fpm(config('ops.fpm_health_status_url')),
            'database' => $this->database(),
        ];

        if ($this->option('alert')) {
            return $this->checkThresholds($report);
        }
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $c = $report['api_cache'];
        $this->info("API response cache (today, UTC): {$c['hit']} hits, {$c['miss']} misses, hit rate {$c['hit_rate']}");
        $r = $report['redis'];
        if (isset($r['error'])) {
            $this->warn('Redis: '.$r['error']);
        } else {
            $this->info("Redis: {$r['used_memory_human']} of {$r['maxmemory_human']} ({$r['maxmemory_policy']}), evicted {$r['evicted_keys']}, hit rate {$r['hit_rate']}, keys in this DB {$r['db_keys']}");
            $this->table(['key group (sample)', 'keys', 'bytes (sampled)'], array_map(
                fn ($g) => [$g['group'], $g['keys'], $g['bytes']],
                $r['groups'],
            ));
        }
        foreach (['fpm' => 'PHP-FPM www', 'fpm_health' => 'PHP-FPM health'] as $k => $label) {
            $f = $report[$k];
            isset($f['error'])
                ? $this->warn("{$label}: {$f['error']}")
                : $this->info("{$label}: active {$f['active processes']}/{$f['total processes']}, listen queue {$f['listen queue']} (max {$f['max listen queue']}), max children reached {$f['max children reached']}, accepted {$f['accepted conn']}");
        }
        $d = $report['database'];
        isset($d['error'])
            ? $this->warn('Database: '.$d['error'])
            : $this->info("Database: {$d['active']} active, {$d['total']} connections, longest running {$d['longest_running_s']} s");
        foreach ($d['top_statements'] ?? [] as $s) {
            $this->line("  {$s['calls']} calls, {$s['mean_ms']} ms mean, {$s['total_s']} s total: {$s['query']}");
        }

        return self::SUCCESS;
    }

    private function apiCache(): array
    {
        $s = ApiResponseCache::stats();
        $total = $s['hit'] + $s['miss'];

        return $s + ['hit_rate' => $total ? round(100 * $s['hit'] / $total, 1).'%' : '-'];
    }

    private function redis(int $sample): array
    {
        if (config('cache.default') !== 'redis') {
            return ['error' => 'cache store is '.config('cache.default')];
        }
        try {
            $conn = Redis::connection(config('cache.stores.redis.connection', 'cache'));
            $info = $conn->command('info');
            $flat = [];
            foreach ((array) $info as $k => $v) {
                is_array($v) ? $flat += $v : $flat[$k] = $v;
            }
            $hits = (int) ($flat['keyspace_hits'] ?? 0);
            $misses = (int) ($flat['keyspace_misses'] ?? 0);

            $groups = [];
            $prefix = (string) config('database.redis.options.prefix', '');
            // phpredis: a null iterator starts the scan, it is 0 again when the scan is complete.
            $client = $conn->client();
            $iterator = null;
            $seen = 0;
            while ($seen < $sample && ($keys = $client->scan($iterator, '*', 500)) !== false) {
                foreach ($keys as $key) {
                    if ($seen++ >= $sample) {
                        break;
                    }
                    $name = str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key;
                    $name = preg_replace('/^'.preg_quote((string) config('cache.prefix'), '/').'/', '', $name);
                    $group = implode(':', array_slice(explode(':', $name), 0, str_starts_with($name, 'api:') ? 3 : 2));
                    $group = preg_replace('/\d{10,}/', '<gen>', $group);
                    $bytes = 0;
                    try {
                        $bytes = (int) $client->rawCommand('MEMORY', 'USAGE', $key);
                    } catch (Throwable) {
                    }
                    $groups[$group]['keys'] = ($groups[$group]['keys'] ?? 0) + 1;
                    $groups[$group]['bytes'] = ($groups[$group]['bytes'] ?? 0) + $bytes;
                }
                if ($iterator === 0) {
                    break;
                }
            }
            uasort($groups, fn ($a, $b) => $b['bytes'] <=> $a['bytes']);
            $groups = array_slice(array_map(fn ($g, $k) => ['group' => $k] + $g, $groups, array_keys($groups)), 0, 15);

            return [
                'used_memory_human' => $flat['used_memory_human'] ?? '?',
                'used_memory' => (int) ($flat['used_memory'] ?? 0),
                'maxmemory_human' => $flat['maxmemory_human'] ?? '?',
                'maxmemory' => (int) ($flat['maxmemory'] ?? 0),
                'maxmemory_policy' => $flat['maxmemory_policy'] ?? '?',
                'evicted_keys' => (int) ($flat['evicted_keys'] ?? 0),
                'hit_rate' => $hits + $misses ? round(100 * $hits / ($hits + $misses), 1).'%' : '-',
                'db_keys' => (int) $conn->command('dbsize'),
                'groups' => $groups,
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function fpm(?string $url): array
    {
        if (! $url) {
            return ['error' => 'no status URL (OPS_FPM_STATUS_URL)'];
        }
        try {
            $json = Http::timeout(3)->get($url)->throw()->json();

            return is_array($json) ? $json : ['error' => 'not JSON'];
        } catch (Throwable $e) {
            return ['error' => class_basename($e).': '.$e->getMessage()];
        }
    }

    private function database(): array
    {
        try {
            $row = DB::selectOne("select count(*) filter (where state = 'active') as active, count(*) as total,
                coalesce(extract(epoch from max(now() - query_start) filter (where state = 'active')), 0) as longest
                from pg_stat_activity where datname = current_database() and pid <> pg_backend_pid()");
            $out = ['active' => (int) $row->active, 'total' => (int) $row->total, 'longest_running_s' => round((float) $row->longest, 1)];
            try {
                $out['top_statements'] = array_map(fn ($s) => [
                    'calls' => (int) $s->calls,
                    'mean_ms' => round((float) $s->mean_exec_time, 1),
                    'total_s' => round((float) $s->total_exec_time / 1000, 1),
                    'query' => mb_substr(preg_replace('/\s+/', ' ', $s->query), 0, 140),
                ], DB::select('select calls, mean_exec_time, total_exec_time, query from pg_stat_statements
                    where dbid = (select oid from pg_database where datname = current_database())
                    order by total_exec_time desc limit 5'));
            } catch (Throwable) {
                // pg_stat_statements is not installed: fine.
            }

            return $out;
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** Thresholds: PHP-FPM queue or "max children reached" growing, Redis full and evicting, low hit rate. */
    private function checkThresholds(array $report): int
    {
        $last = Cache::get(self::ALERT_STATE_KEY, []);
        $problems = [];

        $fpm = $report['fpm'];
        if (! isset($fpm['error'])) {
            if ((int) $fpm['listen queue'] >= (int) config('ops.alert.fpm_queue')) {
                $problems[] = "PHP-FPM queue {$fpm['listen queue']} requests waiting (active {$fpm['active processes']}/{$fpm['total processes']})";
            }
            $reached = (int) $fpm['max children reached'];
            if (isset($last['fpm_max_children_reached'], $last['fpm_start']) && $last['fpm_start'] === ($fpm['start time'] ?? null)
                && $reached > $last['fpm_max_children_reached']) {
                $problems[] = 'PHP-FPM ran out of workers '.($reached - $last['fpm_max_children_reached']).' more time(s) since the last check';
            }
        }
        $redis = $report['redis'];
        if (! isset($redis['error']) && $redis['maxmemory'] > 0) {
            $evicted = $redis['evicted_keys'] - (int) ($last['redis_evicted'] ?? $redis['evicted_keys']);
            if ($redis['used_memory'] >= 0.95 * $redis['maxmemory'] && $evicted >= (int) config('ops.alert.redis_evictions')) {
                $problems[] = "Redis full ({$redis['used_memory_human']} of {$redis['maxmemory_human']}), {$evicted} keys evicted since the last check";
            }
        }

        Cache::forever(self::ALERT_STATE_KEY, [
            'fpm_max_children_reached' => isset($fpm['error']) ? null : (int) $fpm['max children reached'],
            'fpm_start' => $fpm['start time'] ?? null,
            'redis_evicted' => $redis['evicted_keys'] ?? null,
        ]);

        if ($problems === []) {
            $this->info('No thresholds crossed.');

            return self::SUCCESS;
        }

        $text = "Beachrex ".config('app.env')." (".config('app.url')."):\n- ".implode("\n- ", $problems)
            ."\n\nRunbook: docs/runbooks/performance-and-bots.md\n\n".json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Log::warning('ops:perf-report alert: '.implode('; ', $problems));
        $this->warn(implode("\n", $problems));

        $to = config('ops.alert_email');
        // At most one e-mail per hour.
        if ($to && Cache::add('ops:alert:mailed', true, 3600)) {
            try {
                Mail::raw($text, fn ($m) => $m->to($to)->subject('Beachrex '.config('app.env').': performance alert'));
            } catch (Throwable $e) {
                Log::error('ops:perf-report could not send the alert: '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

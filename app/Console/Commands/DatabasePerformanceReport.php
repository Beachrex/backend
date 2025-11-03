<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabasePerformanceReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:performance-report {--detailed : Show detailed analysis}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a database performance report with index usage, cache hit ratio, and optimization recommendations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Database Performance Report ===');
        $this->newLine();

        // 1. Database Overview
        $this->showDatabaseOverview();

        // 2. Index Usage Statistics
        $this->showIndexUsage();

        // 3. Cache Hit Ratio
        $this->showCacheHitRatio();

        // 4. Table Sizes
        $this->showTableSizes();

        // 5. Sequential vs Index Scans
        $this->showScanRatio();

        // 6. Spatial Index Performance
        $this->showSpatialIndexes();

        if ($this->option('detailed')) {
            // 7. Unused Indexes
            $this->showUnusedIndexes();

            // 8. Tables Needing Vacuum
            $this->showVacuumNeeds();
        }

        // 9. Beach Statistics
        $this->showBeachStatistics();

        $this->newLine();
        $this->info('✅ Report complete!');
        $this->info('For detailed monitoring queries, see: database/sql/performance_monitoring.sql');

        return Command::SUCCESS;
    }

    protected function showDatabaseOverview()
    {
        $this->info('📊 Database Overview');
        $this->line('─────────────────────────────────────');

        $stats = DB::selectOne("
            SELECT
                COUNT(*) as table_count,
                pg_size_pretty(SUM(pg_total_relation_size(schemaname||'.'||tablename))) as total_size
            FROM pg_tables
            WHERE schemaname = 'public'
        ");

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tables', $stats->table_count],
                ['Total Size', $stats->total_size],
            ]
        );
        $this->newLine();
    }

    protected function showIndexUsage()
    {
        $this->info('📈 Top 10 Most Used Indexes');
        $this->line('─────────────────────────────────────');

        $indexes = DB::select("
            SELECT
                schemaname,
                relname as tablename,
                indexrelname as indexname,
                idx_scan as scans,
                pg_size_pretty(pg_relation_size(indexrelid)) as size
            FROM pg_stat_user_indexes
            WHERE schemaname = 'public'
            ORDER BY idx_scan DESC
            LIMIT 10
        ");

        $this->table(
            ['Table', 'Index', 'Scans', 'Size'],
            array_map(fn($idx) => [
                $idx->tablename,
                $idx->indexname,
                number_format($idx->scans),
                $idx->size
            ], $indexes)
        );
        $this->newLine();
    }

    protected function showCacheHitRatio()
    {
        $this->info('💾 Cache Hit Ratio');
        $this->line('─────────────────────────────────────');

        $cache = DB::selectOne("
            SELECT
                sum(heap_blks_read) as heap_read,
                sum(heap_blks_hit) as heap_hit,
                ROUND(100.0 * sum(heap_blks_hit) / NULLIF(sum(heap_blks_hit) + sum(heap_blks_read), 0), 2) as ratio
            FROM pg_statio_user_tables
        ");

        $ratio = $cache->ratio ?? 0;
        $status = $ratio >= 99 ? '✅' : ($ratio >= 95 ? '⚠️' : '❌');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Disk Reads', number_format($cache->heap_read)],
                ['Cache Hits', number_format($cache->heap_hit)],
                ['Hit Ratio', $status . ' ' . $ratio . '%'],
            ]
        );

        if ($ratio < 99) {
            $this->warn('⚠️  Cache hit ratio is below 99%. Consider increasing shared_buffers.');
        } else {
            $this->info('✅ Excellent cache hit ratio!');
        }
        $this->newLine();
    }

    protected function showTableSizes()
    {
        $this->info('📦 Top 10 Largest Tables');
        $this->line('─────────────────────────────────────');

        $tables = DB::select("
            SELECT
                relname as tablename,
                pg_size_pretty(pg_total_relation_size(schemaname||'.'||relname)) AS size,
                n_live_tup as rows
            FROM pg_stat_user_tables
            WHERE schemaname = 'public'
            ORDER BY pg_total_relation_size(schemaname||'.'||relname) DESC
            LIMIT 10
        ");

        $this->table(
            ['Table', 'Size', 'Rows'],
            array_map(fn($t) => [
                $t->tablename,
                $t->size,
                number_format($t->rows)
            ], $tables)
        );
        $this->newLine();
    }

    protected function showScanRatio()
    {
        $this->info('🔍 Sequential vs Index Scans (Top 10)');
        $this->line('─────────────────────────────────────');

        $scans = DB::select("
            SELECT
                relname as tablename,
                seq_scan,
                idx_scan,
                n_live_tup as rows,
                CASE
                    WHEN seq_scan + idx_scan > 0
                    THEN ROUND(100.0 * seq_scan / (seq_scan + idx_scan), 2)
                    ELSE 0
                END as seq_percent
            FROM pg_stat_user_tables
            WHERE schemaname = 'public'
              AND n_live_tup > 100
            ORDER BY seq_scan DESC
            LIMIT 10
        ");

        $this->table(
            ['Table', 'Seq Scans', 'Index Scans', 'Rows', 'Seq %'],
            array_map(fn($s) => [
                $s->tablename,
                number_format($s->seq_scan),
                number_format($s->idx_scan),
                number_format($s->rows),
                $s->seq_percent . '%'
            ], $scans)
        );
        $this->newLine();
    }

    protected function showSpatialIndexes()
    {
        $this->info('🗺️  Spatial Index Performance');
        $this->line('─────────────────────────────────────');

        $spatial = DB::select("
            SELECT
                relname as tablename,
                indexrelname as indexname,
                idx_scan as scans,
                pg_size_pretty(pg_relation_size(indexrelid)) as size
            FROM pg_stat_user_indexes
            WHERE indexrelname LIKE '%gist%'
              AND schemaname = 'public'
            ORDER BY idx_scan DESC
        ");

        if (empty($spatial)) {
            $this->warn('No spatial indexes found.');
        } else {
            $this->table(
                ['Table', 'Index', 'Scans', 'Size'],
                array_map(fn($s) => [
                    $s->tablename,
                    $s->indexname,
                    number_format($s->scans),
                    $s->size
                ], $spatial)
            );
        }
        $this->newLine();
    }

    protected function showUnusedIndexes()
    {
        $this->info('🚫 Unused Indexes (Candidates for Removal)');
        $this->line('─────────────────────────────────────');

        $unused = DB::select("
            SELECT
                relname as tablename,
                indexrelname as indexname,
                pg_size_pretty(pg_relation_size(indexrelid)) as size
            FROM pg_stat_user_indexes
            WHERE idx_scan = 0
              AND indexrelname NOT LIKE '%_pkey'
              AND indexrelname NOT LIKE '%_unique'
              AND schemaname = 'public'
            ORDER BY pg_relation_size(indexrelid) DESC
            LIMIT 10
        ");

        if (empty($unused)) {
            $this->info('✅ No unused indexes found!');
        } else {
            $this->table(
                ['Table', 'Index', 'Size'],
                array_map(fn($u) => [
                    $u->tablename,
                    $u->indexname,
                    $u->size
                ], $unused)
            );
            $this->warn('⚠️  Consider removing unused indexes to save space and improve write performance.');
        }
        $this->newLine();
    }

    protected function showVacuumNeeds()
    {
        $this->info('🧹 Tables Needing VACUUM');
        $this->line('─────────────────────────────────────');

        $vacuum = DB::select("
            SELECT
                relname as tablename,
                n_live_tup as live_rows,
                n_dead_tup as dead_rows,
                ROUND(100.0 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) as dead_percent
            FROM pg_stat_user_tables
            WHERE schemaname = 'public'
              AND n_dead_tup > 1000
            ORDER BY dead_percent DESC
            LIMIT 10
        ");

        if (empty($vacuum)) {
            $this->info('✅ No tables need immediate vacuuming!');
        } else {
            $this->table(
                ['Table', 'Live Rows', 'Dead Rows', 'Dead %'],
                array_map(fn($v) => [
                    $v->tablename,
                    number_format($v->live_rows),
                    number_format($v->dead_rows),
                    $v->dead_percent . '%'
                ], $vacuum)
            );
            $this->warn('⚠️  Run VACUUM ANALYZE on tables with high dead row percentage.');
        }
        $this->newLine();
    }

    protected function showBeachStatistics()
    {
        $this->info('🏖️  Beach Statistics');
        $this->line('─────────────────────────────────────');

        $stats = DB::select("
            SELECT
                'Total Beaches' as metric,
                COUNT(*)::text as value
            FROM beach
            UNION ALL
            SELECT
                'Active Beaches',
                COUNT(*)::text
            FROM beach
            WHERE status_id = 1
            UNION ALL
            SELECT
                'Beaches with Location',
                COUNT(*)::text
            FROM beach
            WHERE location IS NOT NULL
            UNION ALL
            SELECT
                'Beaches with Media',
                COUNT(DISTINCT mediable_id)::text
            FROM media
            WHERE mediable_type = 'App\\Models\\Beach'
              AND status_id = 1
        ");

        $this->table(
            ['Metric', 'Value'],
            array_map(fn($s) => [
                $s->metric,
                number_format((int)$s->value)
            ], $stats)
        );
        $this->newLine();
    }
}


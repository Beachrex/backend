-- ============================================================================
-- Database Performance Monitoring Queries
-- ============================================================================
-- Purpose: Monitor database performance, index usage, and identify bottlenecks
-- Usage: Run these queries periodically to assess database health
-- ============================================================================

-- ============================================================================
-- 1. INDEX USAGE STATISTICS
-- ============================================================================

-- Check which indexes are being used most frequently
SELECT 
    schemaname,
    tablename,
    indexname,
    idx_scan as index_scans,
    idx_tup_read as tuples_read,
    idx_tup_fetch as tuples_fetched,
    pg_size_pretty(pg_relation_size(indexrelid)) as index_size
FROM pg_stat_user_indexes
WHERE schemaname = 'public'
ORDER BY idx_scan DESC
LIMIT 50;

-- ============================================================================
-- 2. UNUSED INDEXES
-- ============================================================================

-- Find indexes that are never used (candidates for removal)
SELECT 
    schemaname,
    tablename,
    indexname,
    pg_size_pretty(pg_relation_size(indexrelid)) as index_size
FROM pg_stat_user_indexes
WHERE idx_scan = 0
  AND indexname NOT LIKE '%_pkey'  -- Exclude primary keys
  AND indexname NOT LIKE '%_unique'  -- Exclude unique constraints
  AND schemaname = 'public'
ORDER BY pg_relation_size(indexrelid) DESC;

-- ============================================================================
-- 3. TABLE SIZE AND ROW COUNT
-- ============================================================================

-- Show table sizes with row counts
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total_size,
    pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) AS table_size,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename) - pg_relation_size(schemaname||'.'||tablename)) AS indexes_size,
    n_live_tup as row_count,
    n_dead_tup as dead_rows,
    ROUND(100.0 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) as dead_row_percent
FROM pg_stat_user_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 30;

-- ============================================================================
-- 4. TABLES NEEDING VACUUM
-- ============================================================================

-- Identify tables with high dead tuple percentage (need VACUUM)
SELECT 
    schemaname,
    tablename,
    n_live_tup as live_rows,
    n_dead_tup as dead_rows,
    ROUND(100.0 * n_dead_tup / NULLIF(n_live_tup + n_dead_tup, 0), 2) as dead_row_percent,
    last_vacuum,
    last_autovacuum
FROM pg_stat_user_tables
WHERE schemaname = 'public'
  AND n_dead_tup > 1000  -- Only show tables with significant dead rows
ORDER BY dead_row_percent DESC;

-- ============================================================================
-- 5. SEQUENTIAL SCANS vs INDEX SCANS
-- ============================================================================

-- Tables with high sequential scan ratio (might need indexes)
SELECT 
    schemaname,
    tablename,
    seq_scan as sequential_scans,
    idx_scan as index_scans,
    n_live_tup as row_count,
    CASE 
        WHEN seq_scan + idx_scan > 0 
        THEN ROUND(100.0 * seq_scan / (seq_scan + idx_scan), 2)
        ELSE 0 
    END as seq_scan_percent
FROM pg_stat_user_tables
WHERE schemaname = 'public'
  AND n_live_tup > 100  -- Only tables with significant rows
ORDER BY seq_scan DESC
LIMIT 20;

-- ============================================================================
-- 6. CACHE HIT RATIO
-- ============================================================================

-- Database-wide cache hit ratio (should be > 99%)
SELECT 
    sum(heap_blks_read) as heap_read,
    sum(heap_blks_hit) as heap_hit,
    ROUND(100.0 * sum(heap_blks_hit) / NULLIF(sum(heap_blks_hit) + sum(heap_blks_read), 0), 2) as cache_hit_ratio
FROM pg_statio_user_tables;

-- Per-table cache hit ratio
SELECT 
    schemaname,
    tablename,
    heap_blks_read as disk_reads,
    heap_blks_hit as cache_hits,
    ROUND(100.0 * heap_blks_hit / NULLIF(heap_blks_hit + heap_blks_read, 0), 2) as cache_hit_ratio
FROM pg_statio_user_tables
WHERE schemaname = 'public'
  AND (heap_blks_hit + heap_blks_read) > 0
ORDER BY heap_blks_read DESC
LIMIT 20;

-- ============================================================================
-- 7. SPATIAL INDEX PERFORMANCE
-- ============================================================================

-- Check PostGIS spatial index usage
SELECT 
    schemaname,
    tablename,
    indexname,
    idx_scan as spatial_index_scans,
    pg_size_pretty(pg_relation_size(indexrelid)) as index_size
FROM pg_stat_user_indexes
WHERE indexname LIKE '%gist%'
  AND schemaname = 'public'
ORDER BY idx_scan DESC;

-- ============================================================================
-- 8. FOREIGN KEY CONSTRAINT CHECKS
-- ============================================================================

-- List all foreign keys and their indexes
SELECT
    tc.table_name,
    kcu.column_name,
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS foreign_column_name,
    CASE 
        WHEN i.indexname IS NOT NULL THEN 'YES'
        ELSE 'NO'
    END as has_index
FROM information_schema.table_constraints AS tc
JOIN information_schema.key_column_usage AS kcu
    ON tc.constraint_name = kcu.constraint_name
    AND tc.table_schema = kcu.table_schema
JOIN information_schema.constraint_column_usage AS ccu
    ON ccu.constraint_name = tc.constraint_name
    AND ccu.table_schema = tc.table_schema
LEFT JOIN pg_indexes i 
    ON i.tablename = tc.table_name 
    AND i.indexdef LIKE '%' || kcu.column_name || '%'
WHERE tc.constraint_type = 'FOREIGN KEY'
  AND tc.table_schema = 'public'
ORDER BY tc.table_name, kcu.column_name;

-- ============================================================================
-- 9. DUPLICATE INDEXES
-- ============================================================================

-- Find potentially duplicate indexes (same columns, different names)
SELECT 
    pg_size_pretty(SUM(pg_relation_size(idx))::BIGINT) AS size,
    (array_agg(idx))[1] AS idx1, 
    (array_agg(idx))[2] AS idx2,
    (array_agg(idx))[3] AS idx3,
    (array_agg(idx))[4] AS idx4
FROM (
    SELECT 
        indexrelid::regclass AS idx, 
        (indrelid::text ||E'\n'|| indclass::text ||E'\n'|| indkey::text ||E'\n'||
         COALESCE(indexprs::text,'')||E'\n' || COALESCE(indpred::text,'')) AS key
    FROM pg_index
) sub
GROUP BY key 
HAVING COUNT(*) > 1
ORDER BY SUM(pg_relation_size(idx)) DESC;

-- ============================================================================
-- 10. LONG RUNNING QUERIES
-- ============================================================================

-- Show currently running queries (useful for debugging)
SELECT 
    pid,
    now() - pg_stat_activity.query_start AS duration,
    query,
    state
FROM pg_stat_activity
WHERE (now() - pg_stat_activity.query_start) > interval '5 seconds'
  AND state != 'idle'
ORDER BY duration DESC;

-- ============================================================================
-- 11. BEACH-SPECIFIC PERFORMANCE METRICS
-- ============================================================================

-- Beach table statistics
SELECT 
    'Total Beaches' as metric,
    COUNT(*) as value
FROM beach
UNION ALL
SELECT 
    'Active Beaches',
    COUNT(*)
FROM beach
WHERE status_id = 1
UNION ALL
SELECT 
    'Beaches with Location',
    COUNT(*)
FROM beach
WHERE location IS NOT NULL
UNION ALL
SELECT 
    'Beaches with Media',
    COUNT(DISTINCT mediable_id)
FROM media
WHERE mediable_type = 'App\Models\Beach'
  AND status_id = 1;

-- ============================================================================
-- 12. PIVOT TABLE STATISTICS
-- ============================================================================

-- Show relationship counts for pivot tables
SELECT 
    'accommodation_around_beach' as pivot_table,
    COUNT(*) as total_relationships,
    COUNT(DISTINCT beach_id) as beaches_with_feature,
    ROUND(AVG(cnt), 2) as avg_per_beach
FROM (
    SELECT beach_id, COUNT(*) as cnt
    FROM accommodation_around_beach
    WHERE status_id = 1
    GROUP BY beach_id
) sub
UNION ALL
SELECT 
    'culinary_offer_beach',
    COUNT(*),
    COUNT(DISTINCT beach_id),
    ROUND(AVG(cnt), 2)
FROM (
    SELECT beach_id, COUNT(*) as cnt
    FROM culinary_offer_beach
    WHERE status_id = 1
    GROUP BY beach_id
) sub
UNION ALL
SELECT 
    'location_infrastructure_beach',
    COUNT(*),
    COUNT(DISTINCT beach_id),
    ROUND(AVG(cnt), 2)
FROM (
    SELECT beach_id, COUNT(*) as cnt
    FROM location_infrastructure_beach
    WHERE status_id = 1
    GROUP BY beach_id
) sub
UNION ALL
SELECT 
    'sporting_activities_beach',
    COUNT(*),
    COUNT(DISTINCT beach_id),
    ROUND(AVG(cnt), 2)
FROM (
    SELECT beach_id, COUNT(*) as cnt
    FROM sporting_activities_beach
    WHERE status_id = 1
    GROUP BY beach_id
) sub;

-- ============================================================================
-- 13. TRANSLATION TABLE COVERAGE
-- ============================================================================

-- Check translation coverage by locale
SELECT 
    locale,
    COUNT(*) as translation_count,
    COUNT(DISTINCT beach_id) as beaches_translated
FROM beach_translation
GROUP BY locale
ORDER BY translation_count DESC;

-- ============================================================================
-- 14. MEDIA DISTRIBUTION
-- ============================================================================

-- Media statistics by type and collection
SELECT 
    mt.code as media_type,
    mc.code as collection,
    COUNT(*) as media_count,
    COUNT(DISTINCT mediable_id) as entities_with_media,
    pg_size_pretty(SUM(size)) as total_size
FROM media m
JOIN media_types mt ON m.media_type_id = mt.id
LEFT JOIN media_collections mc ON m.media_collection_id = mc.id
WHERE m.status_id = 1
GROUP BY mt.code, mc.code
ORDER BY media_count DESC;

-- ============================================================================
-- 15. SPATIAL QUERY PERFORMANCE TEST
-- ============================================================================

-- Test spatial index performance (should use GIST index)
EXPLAIN ANALYZE
SELECT COUNT(*)
FROM beach
WHERE location IS NOT NULL
  AND ST_DWithin(
    ST_Transform(location, 3857),
    ST_Transform(ST_SetSRID(ST_MakePoint(14.5, 45.5), 4326), 3857),
    50000  -- 50km radius
  );

-- ============================================================================
-- END OF PERFORMANCE MONITORING QUERIES
-- ============================================================================


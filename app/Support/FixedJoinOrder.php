<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Runs a hand-written query with many LEFT JOINs in the join order it is written in (ADR 2026-10-30 "API performance").
 *
 * The beach/place queries join ~15 lookup tables (translation + English fallback per place level). PostgreSQL then
 * spends 10–25 ms PLANNING each of them for < 1 ms of execution (measured with EXPLAIN ANALYZE on a copy-like
 * database). They are written driving from `beach` (or `city`) and join everything else by key, so the written order
 * is already the right one: `join_collapse_limit = 1` / `from_collapse_limit = 1` cut planning to ~3 ms with the
 * same plans' execution time. The settings are `SET LOCAL`, i.e. they end with this transaction.
 */
final class FixedJoinOrder
{
    /** @return array<int, object> */
    public static function select(string $sql, array $bindings = []): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return DB::select($sql, $bindings);
        }

        return DB::transaction(function () use ($sql, $bindings) {
            DB::statement('SET LOCAL join_collapse_limit = 1');
            DB::statement('SET LOCAL from_collapse_limit = 1');

            return DB::select($sql, $bindings);
        });
    }

    public static function selectOne(string $sql, array $bindings = []): ?object
    {
        return self::select($sql, $bindings)[0] ?? null;
    }
}

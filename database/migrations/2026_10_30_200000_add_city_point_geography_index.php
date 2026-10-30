<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nearest city to a point (within-radius "location" meta, PostGISService::reverseGeocodePoint) computed the
 * spheroid distance to every city and sorted them (ADR 2026-10-30 "API performance"). This expression index on the
 * city point lets the query use ST_DWithin + KNN (`<->`) instead. The expression must stay identical to
 * PostGISService::CITY_POINT_SQL. Idempotent; a small table, so the build is quick.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('CREATE INDEX IF NOT EXISTS city_point_geography_idx ON city USING GIST '
            .'((ST_SetSRID(ST_MakePoint(longitude::double precision, latitude::double precision), 4326)::geography))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS city_point_geography_idx');
    }
};

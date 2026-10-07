<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Distance queries measure on the spheroid (`location::geography`) instead of EPSG:3857, which
 * overstates distances by ~1/cos(latitude) (~37% in Croatia). This expression index lets
 * ST_DWithin(…::geography, location::geography, metres) use an index.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS beach_location_geography_idx ON beach USING GIST ((location::geography))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS beach_location_geography_idx');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if location column already exists
        $hasLocationColumn = DB::select("
            SELECT EXISTS(
                SELECT 1 FROM information_schema.columns
                WHERE table_name = 'beach' AND column_name = 'location'
            ) as exists
        ")[0]->exists ?? false;

        if (!$hasLocationColumn) {
            // Add PostGIS spatial column for beach locations
            DB::statement('ALTER TABLE beach ADD COLUMN location GEOMETRY(POINT, 4326)');
        }

        // Create spatial index for performance (if it doesn't exist)
        $hasIndex = DB::select("
            SELECT EXISTS(
                SELECT 1 FROM pg_indexes
                WHERE tablename = 'beach' AND indexname = 'beach_location_gist_idx'
            ) as exists
        ")[0]->exists ?? false;

        if (!$hasIndex) {
            DB::statement('CREATE INDEX beach_location_gist_idx ON beach USING GIST (location)');
        }

        // Update existing records to populate the spatial column
        DB::statement('
            UPDATE beach
            SET location = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        ');

        // Add constraint to ensure location is always set when lat/lng are provided
        DB::statement('
            CREATE OR REPLACE FUNCTION update_beach_location()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
                    NEW.location = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326);
                ELSE
                    NEW.location = NULL;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Check if trigger already exists
        $hasTrigger = DB::select("
            SELECT EXISTS(
                SELECT 1 FROM pg_trigger
                WHERE tgname = 'beach_location_trigger'
            ) as exists
        ")[0]->exists ?? false;

        if (!$hasTrigger) {
            DB::statement('
                CREATE TRIGGER beach_location_trigger
                BEFORE INSERT OR UPDATE ON beach
                FOR EACH ROW
                EXECUTE FUNCTION update_beach_location();
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS beach_location_trigger ON beach');
        DB::statement('DROP FUNCTION IF EXISTS update_beach_location()');

        // Drop spatial index and column
        DB::statement('DROP INDEX IF EXISTS beach_location_gist_idx');
        DB::statement('ALTER TABLE beach DROP COLUMN IF EXISTS location');
    }
};

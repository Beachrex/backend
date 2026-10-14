<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-006: local copy of Windy webcam metadata (never image URLs) and webcam ↔ place assignments.
 * Expand-only: `beach.webcam` is not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('windy_webcams', function (Blueprint $table) {
            $table->unsignedBigInteger('windy_id')->primary();
            $table->string('title', 500)->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('region_code', 20)->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->jsonb('categories')->default('[]');
            $table->string('status', 20)->default('active')->index();
            $table->string('embed_status', 20)->default('unknown')->comment('ok | not_public | unavailable | unknown (public player check)');
            $table->timestamp('embed_checked_at')->nullable();
            $table->timestamp('last_updated_on')->nullable()->comment('Windy lastUpdatedOn');
            $table->jsonb('raw')->nullable()->comment('API object without images');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('missing_since')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE windy_webcams ADD COLUMN location geography(Point, 4326)');
        DB::statement('CREATE INDEX windy_webcams_location_idx ON windy_webcams USING GIST (location)');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION windy_webcams_set_location() RETURNS trigger AS $$
            BEGIN
                NEW.location := ST_SetSRID(ST_MakePoint(NEW.lon, NEW.lat), 4326)::geography;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER windy_webcams_location_trg BEFORE INSERT OR UPDATE OF lat, lon ON windy_webcams FOR EACH ROW EXECUTE FUNCTION windy_webcams_set_location()');

        Schema::create('place_webcams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('beach')->cascadeOnDelete();
            $table->unsignedBigInteger('windy_id');
            $table->foreign('windy_id')->references('windy_id')->on('windy_webcams')->cascadeOnDelete();
            $table->unsignedInteger('distance_m')->nullable();
            $table->integer('match_score')->default(0);
            $table->jsonb('score_breakdown')->nullable();
            $table->string('source', 10)->comment('legacy | matcher | manual');
            $table->string('review_status', 15)->comment('auto_approved | pending | approved | rejected');
            $table->boolean('is_primary')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['place_id', 'windy_id']);
            $table->index(['review_status', 'match_score']);
        });

        // At most one primary webcam per place; a shown webcam belongs to one place only.
        DB::statement('CREATE UNIQUE INDEX place_webcams_one_primary ON place_webcams (place_id) WHERE is_primary');
        DB::statement("CREATE UNIQUE INDEX place_webcams_one_place_per_webcam ON place_webcams (windy_id) WHERE review_status IN ('auto_approved', 'approved')");
    }

    public function down(): void
    {
        Schema::dropIfExists('place_webcams');
        Schema::dropIfExists('windy_webcams');
        DB::statement('DROP FUNCTION IF EXISTS windy_webcams_set_location()');
    }
};

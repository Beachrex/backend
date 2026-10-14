<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** TASK-006 step 2: resumable import checkpoints and the daily Windy request budget. */
return new class extends Migration
{
    public function up(): void
    {
        // One row per import slice (a country, or a region of a big country) per run.
        Schema::create('windy_import_slices', function (Blueprint $table) {
            $table->id();
            $table->string('run_key', 40)->comment('one id per import run, shared by its slices');
            $table->char('country_code', 2);
            $table->string('region_code', 20)->nullable();
            $table->unsignedInteger('next_offset')->default(0);
            $table->unsignedInteger('total')->nullable()->comment('Windy `total` for this slice');
            $table->unsignedInteger('fetched')->default(0);
            $table->string('status', 15)->default('pending')->comment('pending | running | split | done | truncated');
            $table->timestamps();

            $table->index(['run_key', 'status']);
        });
        // NULL region = the whole country; COALESCE so the country slice is unique too (NULLs never collide).
        DB::statement("CREATE UNIQUE INDEX windy_import_slices_unique ON windy_import_slices (run_key, country_code, COALESCE(region_code, ''))");

        Schema::create('windy_api_usage', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->unsignedInteger('requests')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('windy_api_usage');
        Schema::dropIfExists('windy_import_slices');
    }
};

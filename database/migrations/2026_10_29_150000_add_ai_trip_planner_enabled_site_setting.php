<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TASK-009 (owner 2026-10-29): a separate admin switch for the paid AI trip planner, so the chat can stay on while the
 * planner is off. Default on; inserts only when the key is missing (idempotent, runs on every environment's deploy).
 * A missing key also reads as on (SiteSetting::aiTripPlannerEnabled), so the cached switch list needs no reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('site_settings')->where('key', 'ai_trip_planner_enabled')->exists();
        if (!$exists) {
            DB::table('site_settings')->insert([
                'key' => 'ai_trip_planner_enabled',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->where('key', 'ai_trip_planner_enabled')->delete();
    }
};

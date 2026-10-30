<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owner 2026-10-30: pick the AI chat model and the AI trip planner model in admin (AI → Models). Adds both keys with
 * value NULL = "use the env/config default", so nothing changes on deploy. Inserts only missing keys (idempotent,
 * keeps a model already picked in admin).
 */
return new class extends Migration
{
    private const KEYS = ['ai_chat_model', 'ai_itinerary_model'];

    public function up(): void
    {
        foreach (self::KEYS as $key) {
            if (! DB::table('site_settings')->where('key', $key)->exists()) {
                DB::table('site_settings')->insert(['key' => $key, 'value' => null, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Cache::forget('site_settings:all');
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', self::KEYS)->delete();
        Cache::forget('site_settings:all');
    }
};

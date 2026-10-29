<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owner 2026-10-29: "izklopi mi Google ads na produkciji … da bi jih lahko prek admina vklapljali ali izklapljali".
 * Admin switch for Google ads (AdSense) on the website. Default OFF, so ads stop on prod with this deploy; the owner
 * switches them on in System → Site settings. Inserts only when the key is missing (idempotent, keeps a value set in
 * admin). A missing key also reads as off (SiteSetting::adsEnabled).
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('site_settings')->where('key', 'ads_enabled')->exists();
        if (!$exists) {
            DB::table('site_settings')->insert([
                'key' => 'ads_enabled',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Cache::forget('site_settings:all');
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->where('key', 'ads_enabled')->delete();
        Cache::forget('site_settings:all');
    }
};

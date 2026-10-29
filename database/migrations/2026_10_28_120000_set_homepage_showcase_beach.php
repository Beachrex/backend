<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TASK-008: the homepage section `beach_views` ("View beaches from all angles") shows one real beach as an
 * example. Which beach is `config.showcase_beach_id`, editable in admin (First Page Content → beach_views →
 * Additional Configuration). Default: Svetilnik, Izola (id 989) — gallery, video, 360° tour and a Windy webcam
 * that looks at the beach itself (checked on dev 2026-10-28).
 *
 * Idempotent and never overwrites an admin edit: sets the key only when it is missing and the beach exists.
 */
return new class extends Migration
{
    public const SECTION_CODE = 'beach_views';

    public const DEFAULT_BEACH_ID = 989;

    public function up(): void
    {
        $section = DB::table('first_page_content')->where('code', self::SECTION_CODE)->first();
        if (!$section) {
            return;
        }
        if (!DB::table('beach')->where('id', self::DEFAULT_BEACH_ID)->exists()) {
            return;
        }

        $config = is_string($section->config) ? json_decode($section->config, true) : (array) ($section->config ?? []);
        $config = is_array($config) ? $config : [];
        if (array_key_exists('showcase_beach_id', $config) && $config['showcase_beach_id'] !== '' && $config['showcase_beach_id'] !== null) {
            return;
        }

        // Admin's KeyValue field stores strings, so the default is a string too.
        $config['showcase_beach_id'] = (string) self::DEFAULT_BEACH_ID;

        DB::table('first_page_content')->where('id', $section->id)->update(['config' => json_encode($config)]);
    }

    public function down(): void
    {
        $section = DB::table('first_page_content')->where('code', self::SECTION_CODE)->first();
        if (!$section) {
            return;
        }
        $config = is_string($section->config) ? json_decode($section->config, true) : null;
        if (!is_array($config) || ($config['showcase_beach_id'] ?? null) !== (string) self::DEFAULT_BEACH_ID) {
            return;
        }
        unset($config['showcase_beach_id']);
        DB::table('first_page_content')->where('id', $section->id)
            ->update(['config' => $config === [] ? null : json_encode($config)]);
    }
};

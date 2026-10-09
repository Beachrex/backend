<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lookup indexes missing for hot public endpoints (scalability audit 2026-10-09):
 * - beach/city/region pages and the slug resolver look up `*_translation.slug` (country and island
 *   translations already have a slug index);
 * - lists, filters and most-visited filter beaches by `country_code` / `continent_code`
 *   (PostgreSQL does not index foreign-key columns automatically).
 * Additive only; IF NOT EXISTS keeps it safe to re-run.
 */
return new class extends Migration
{
    private const INDEXES = [
        'beach_translation_slug_index' => 'beach_translation (slug)',
        'city_translation_slug_index' => 'city_translation (slug)',
        'region_translation_slug_index' => 'region_translation (slug)',
        'beach_country_code_index' => 'beach (country_code)',
        'beach_continent_code_index' => 'beach (continent_code)',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $target) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$target}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};

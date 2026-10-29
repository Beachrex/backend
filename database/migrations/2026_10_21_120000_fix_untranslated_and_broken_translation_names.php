<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owner report 2026-10-21: untranslated and broken names in the map filters and beach cards.
 * Found by an audit of GET /beaches/filters in all 19 languages on dev (see DEV_HANDOFF.md, 2026-10-21).
 *
 * Every update matches the exact current value, so the migration is idempotent and leaves rows that
 * someone already corrected in admin untouched. Slugs are not changed (they are used in URLs).
 */
return new class extends Migration
{
    /** [table, locale, current name, new name] — option names that were English or wrong. */
    public const OPTION_FIXES = [
        ['accommodation_around_translation', 'el', 'Hostel', 'Ξενώνας'],
        ['culinary_offer_translation', 'el', 'Beach bar', 'Μπαρ παραλίας'],
        ['location_infrastructure_translation', 'el', 'WiFi hotspot', 'Σημείο πρόσβασης WiFi'],
        ['sporting_activities_translation', 'el', 'Kitesurfing', 'Κάιτσερφ'],
        ['sporting_activities_translation', 'sl', 'Smučarski Vlečnica / Wakeboard', 'Vlečnica za wakeboard'],
        ['sporting_activities_translation', 'hr', 'Ski Lift / Wakeboard', 'Vučnica za wakeboard'],
        ['beach_inclination_translation', 'sl', 'Povečanje', 'Naraščajoč naklon'],
    ];

    /** [city_id, locale, current name, new name] — Cyrillic names with Latin letters mixed in. */
    public const CITY_FIXES = [
        [1514, 'ua', 'Міддelкерке', 'Мідделкерке'],
        [515, 'ua', 'Розіньяно-Марittimo', 'Розіньяно-Мариттімо'],
        [78, 'ua', 'Стариград Пакleniця', 'Стариград Пакленіця'],
        [496, 'ua', 'Вілanova-і-ла-Жельтру', 'Віланова-і-ла-Жельтру'],
        [408, 'bg', 'Червo', 'Черво'],
        [1000, 'bg', 'Монт-рoч дел Камп', 'Монт-роч дел Камп'],
        [1306, 'bg', 'Цинowиц', 'Циновиц'],
    ];

    /** [locale, current name, new name] — beach names. */
    public const BEACH_FIXES = [
        ['ua', 'Ražanac → Ражанац', 'Ражанац'],
    ];

    /** Tables whose names were stored with an escaped apostrophe (`L\'Escala`). */
    public const UNESCAPE_TABLES = ['city_translation', 'beach_translation'];

    public function up(): void
    {
        $changed = 0;

        foreach (self::OPTION_FIXES as [$table, $locale, $old, $new]) {
            $changed += DB::table($table)->where('locale', $locale)->where('name', $old)->update(['name' => $new]);
        }

        foreach (self::CITY_FIXES as [$cityId, $locale, $old, $new]) {
            $changed += DB::table('city_translation')
                ->where('city_id', $cityId)->where('locale', $locale)->where('name', $old)
                ->update(['name' => $new]);
        }

        foreach (self::BEACH_FIXES as [$locale, $old, $new]) {
            $changed += DB::table('beach_translation')->where('locale', $locale)->where('name', $old)->update(['name' => $new]);
        }

        // `L\'Escala` → `L'Escala`. POSITION instead of LIKE: backslash is LIKE's escape character.
        foreach (self::UNESCAPE_TABLES as $table) {
            $changed += DB::update(
                "UPDATE {$table} SET name = REPLACE(name, ?, ?) WHERE POSITION(? IN name) > 0",
                ["\\'", "'", "\\'"]
            );
        }

        Log::info('fix_untranslated_and_broken_translation_names: rows changed', ['count' => $changed]);
    }

    public function down(): void
    {
        foreach (self::OPTION_FIXES as [$table, $locale, $old, $new]) {
            DB::table($table)->where('locale', $locale)->where('name', $new)->update(['name' => $old]);
        }
        foreach (self::CITY_FIXES as [$cityId, $locale, $old, $new]) {
            DB::table('city_translation')
                ->where('city_id', $cityId)->where('locale', $locale)->where('name', $new)
                ->update(['name' => $old]);
        }
        foreach (self::BEACH_FIXES as [$locale, $old, $new]) {
            DB::table('beach_translation')->where('locale', $locale)->where('name', $new)->update(['name' => $old]);
        }
        // The un-escaping is not reverted: the backslash was never part of the name.
    }
};

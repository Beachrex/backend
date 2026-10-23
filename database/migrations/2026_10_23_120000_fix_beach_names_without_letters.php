<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner 2026-10-23: the Russian page of Tratica (Pašman) was titled "335". A scan of all 2876 beaches in
 * all 19 languages on dev found only this one name without a single letter.
 *
 * Every beach name with no letter is replaced by the same beach's name in the nearest language that has
 * one (Cyrillic and Greek first try their neighbours, then English). Idempotent; rows with a real name
 * are never touched. The deploy clears the cache and re-indexes search after migrating.
 */
return new class extends Migration
{
    /** Fallback order per locale; English last for everyone. */
    public const NEAREST = [
        'ru' => ['bg', 'ua'],
        'ua' => ['ru', 'bg'],
        'bg' => ['ru', 'ua'],
        'el' => [],
    ];

    public function up(): void
    {
        $broken = DB::table('beach_translation')
            ->select('id', 'beach_id', 'locale', 'name')
            ->get()
            ->reject(fn ($row) => preg_match('/\p{L}/u', (string) $row->name));

        foreach ($broken as $row) {
            $names = DB::table('beach_translation')->where('beach_id', $row->beach_id)->pluck('name', 'locale');
            foreach ([...(self::NEAREST[$row->locale] ?? []), 'en'] as $locale) {
                $name = trim((string) ($names[$locale] ?? ''));
                if ($name !== '' && preg_match('/\p{L}/u', $name)) {
                    DB::table('beach_translation')->where('id', $row->id)->update(['name' => $name, 'updated_at' => now()]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        // Data fix: a number is not a name, nothing to restore.
    }
};

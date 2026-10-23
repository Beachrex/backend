<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pre-launch check 2026-10-23: the home page hero title was "All worries are smaller on the beach! DEV" (an
 * environment marker typed in the admin on dev, carried to uat by the DB promotion). da, pt, nl, el and sq
 * have no row and showed the English title, marker included.
 *
 * Idempotent: the English title changes only while it still has the marker; a missing language gets a row
 * (with the English description and metadata, so nothing else changes on the page); existing rows stay.
 */
return new class extends Migration
{
    public const TITLE = 'All worries are smaller on the beach!';

    /** Titles for languages that had no row. */
    public const MISSING = [
        'da' => 'Alle bekymringer er mindre på stranden!',
        'pt' => 'Todas as preocupações são menores na praia!',
        'nl' => 'Alle zorgen zijn kleiner op het strand!',
        'el' => 'Όλες οι έγνοιες μικραίνουν στην παραλία!',
        'sq' => 'Të gjitha shqetësimet janë më të vogla në plazh!',
    ];

    public function up(): void
    {
        $hero = DB::table('first_page_content')->where('code', 'hero_banner')->value('id');
        if (! $hero) {
            return;
        }

        DB::table('first_page_content_translation')
            ->where('first_page_content_id', $hero)
            ->where('locale', 'en')
            ->where('title', self::TITLE.' DEV')
            ->update(['title' => self::TITLE, 'updated_at' => now()]);

        $en = DB::table('first_page_content_translation')->where('first_page_content_id', $hero)->where('locale', 'en')->first();
        foreach (self::MISSING as $locale => $title) {
            $exists = DB::table('first_page_content_translation')
                ->where('first_page_content_id', $hero)->where('locale', $locale)->exists();
            if ($exists) {
                continue;
            }
            DB::table('first_page_content_translation')->insert([
                'first_page_content_id' => $hero,
                'locale' => $locale,
                'title' => $title,
                'description' => $en?->description,
                'metadata' => $en?->metadata,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data fix: nothing to undo (the old title was a test marker).
    }
};

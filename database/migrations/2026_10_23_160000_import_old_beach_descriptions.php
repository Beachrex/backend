<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner 2026-10-23: the old database (`database/sql/beachrex_old.sql`, table `beach_description`) held descriptions
 * for 793 beaches in 8 languages (en, de, it, fr, es, sl, hr, ru: 2282 texts). The import kept only some English
 * ones: on dev 338 active beaches had no English description and no other language had any.
 *
 * `database/legacy/old-beach-descriptions.json` holds those texts, extracted once from the dump (beach id =>
 * locale => text; the old HTML turned into plain text with line breaks, entities decoded), so this migration
 * never reads the dump with its user table.
 *
 * Idempotent and conservative: a text is written only where the beach's description in that language is empty,
 * or (other languages) is still a copy of the English one. Descriptions someone wrote or generated stay.
 * A missing language row is created from the English row (name and slug, so URLs do not change).
 */
return new class extends Migration
{
    public const FILE = 'database/legacy/old-beach-descriptions.json';

    public function up(): void
    {
        $path = base_path(self::FILE);
        if (! is_file($path)) {
            return;
        }
        $texts = json_decode((string) file_get_contents($path), true) ?: [];
        $columns = DB::getSchemaBuilder()->getColumnListing('beach_translation');

        foreach (array_chunk(array_keys($texts), 200) as $chunk) {
            $existing = DB::table('beach')->whereIn('id', $chunk)->pluck('id')->all();
            $rows = DB::table('beach_translation')->whereIn('beach_id', $existing)->get()->groupBy('beach_id');

            foreach ($existing as $beachId) {
                $translations = $rows->get($beachId, collect());
                $en = $translations->firstWhere('locale', 'en');
                $enDescription = trim((string) ($en->description ?? ''));

                // English first, so the "copy of English" check below compares against the current English text.
                $byLocale = $texts[(string) $beachId];
                uksort($byLocale, fn ($a, $b) => ($b === 'en') <=> ($a === 'en'));

                foreach ($byLocale as $locale => $text) {
                    $text = trim((string) $text);
                    if ($text === '') {
                        continue;
                    }
                    $row = $translations->firstWhere('locale', $locale);
                    if ($row) {
                        $current = trim((string) $row->description);
                        $replaceable = $current === '' || ($locale !== 'en' && $current === $enDescription);
                        if ($replaceable && $current !== $text) {
                            DB::table('beach_translation')->where('id', $row->id)->update(['description' => $text, 'updated_at' => now()]);
                        }
                    } elseif ($en) {
                        $insert = (array) $en;
                        unset($insert['id']);
                        foreach (['meta_description', 'faq'] as $column) {
                            if (in_array($column, $columns, true)) {
                                $insert[$column] = null;
                            }
                        }
                        $insert['locale'] = $locale;
                        $insert['description'] = $text;
                        $insert['created_at'] = now();
                        $insert['updated_at'] = now();
                        DB::table('beach_translation')->insert($insert);
                    }
                    if ($locale === 'en' && ($row === null || trim((string) $row->description) === '')) {
                        $enDescription = $text;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Content import: nothing to undo.
    }
};

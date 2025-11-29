<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataCleanupSeeder extends Seeder
{
    /**
     * Run data cleanup operations.
     * 
     * This seeder performs data quality fixes that need to run AFTER
     * the main data has been seeded. It includes:
     * 1. Removing leading slashes from icon paths
     * 2. Fixing escaped quotes in beach translations
     * 3. Removing double spaces from beach translations
     */
    public function run(): void
    {
        $this->removeLeadingSlashesFromIconPaths();
        $this->fixEscapedQuotesInBeachTranslations();
        $this->removeDoubleSpacesFromBeachTranslations();
    }

    /**
     * Remove leading slashes from icon paths in reference tables.
     * This ensures all paths are relative (e.g., 'icons/filters/waterbody.svg')
     * instead of absolute (e.g., '/icons/filters/waterbody.svg').
     */
    private function removeLeadingSlashesFromIconPaths(): void
    {
        // Fix waterbody icon paths
        DB::statement("
            UPDATE waterbody
            SET icon = SUBSTRING(icon FROM 2)
            WHERE icon LIKE '/%'
        ");

        // Fix beach_type icon paths
        DB::statement("
            UPDATE beach_type
            SET icon = SUBSTRING(icon FROM 2)
            WHERE icon LIKE '/%'
        ");

        // Fix user_type icon paths
        DB::statement("
            UPDATE user_type
            SET icon = SUBSTRING(icon FROM 2)
            WHERE icon LIKE '/%'
        ");
    }

    /**
     * Fix incorrectly escaped quotes in beach_translation table.
     * The database contains literal backslash characters (e.g., "Beach d\'en Perris")
     * when it should just contain apostrophes (e.g., "Beach d'en Perris").
     */
    private function fixEscapedQuotesInBeachTranslations(): void
    {
        // Fix name field - replace \' with '
        // Using CHR(92) for backslash and CHR(39) for apostrophe to avoid escaping issues
        DB::statement("
            UPDATE beach_translation
            SET name = REPLACE(name, CHR(92) || CHR(39), CHR(39))
            WHERE POSITION(CHR(92) IN name) > 0
        ");

        // Fix description field - replace \' with '
        DB::statement("
            UPDATE beach_translation
            SET description = REPLACE(description, CHR(92) || CHR(39), CHR(39))
            WHERE POSITION(CHR(92) IN description) > 0
        ");

        // Fix slug field - replace \' with '
        DB::statement("
            UPDATE beach_translation
            SET slug = REPLACE(slug, CHR(92) || CHR(39), CHR(39))
            WHERE POSITION(CHR(92) IN slug) > 0
        ");
    }

    /**
     * Remove multiple consecutive spaces from beach_translation table.
     * Replace any sequence of 2 or more spaces with a single space.
     */
    private function removeDoubleSpacesFromBeachTranslations(): void
    {
        // Fix name field - replace multiple spaces with single space
        // Using REGEXP_REPLACE to replace 2+ spaces with 1 space
        DB::statement("
            UPDATE beach_translation
            SET name = REGEXP_REPLACE(name, ' {2,}', ' ', 'g')
            WHERE name ~ ' {2,}'
        ");

        // Fix description field - replace multiple spaces with single space
        DB::statement("
            UPDATE beach_translation
            SET description = REGEXP_REPLACE(description, ' {2,}', ' ', 'g')
            WHERE description ~ ' {2,}'
        ");

        // Fix slug field - replace multiple spaces with single space
        // (though slugs shouldn't have spaces, we'll check anyway)
        DB::statement("
            UPDATE beach_translation
            SET slug = REGEXP_REPLACE(slug, ' {2,}', ' ', 'g')
            WHERE slug ~ ' {2,}'
        ");
    }
}


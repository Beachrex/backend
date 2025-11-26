<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix incorrectly escaped quotes in beach_translation table.
     * The database contains literal backslash characters (e.g., "Beach d\'en Perris")
     * when it should just contain apostrophes (e.g., "Beach d'en Perris").
     */
    public function up(): void
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
     * Reverse the migrations.
     *
     * Note: This rollback adds the backslashes back, but this is not recommended
     * as the original data was incorrectly escaped. This is only for rollback purposes.
     */
    public function down(): void
    {
        // Add backslashes back to name field (for rollback only - not recommended)
        DB::statement("
            UPDATE beach_translation
            SET name = REPLACE(name, CHR(39), CHR(92) || CHR(39))
            WHERE POSITION(CHR(39) IN name) > 0
        ");

        // Add backslashes back to description field
        DB::statement("
            UPDATE beach_translation
            SET description = REPLACE(description, CHR(39), CHR(92) || CHR(39))
            WHERE POSITION(CHR(39) IN description) > 0
        ");

        // Add backslashes back to slug field
        DB::statement("
            UPDATE beach_translation
            SET slug = REPLACE(slug, CHR(39), CHR(92) || CHR(39))
            WHERE POSITION(CHR(39) IN slug) > 0
        ");
    }
};


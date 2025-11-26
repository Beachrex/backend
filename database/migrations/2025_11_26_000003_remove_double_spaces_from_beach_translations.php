<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove multiple consecutive spaces from beach_translation table.
     * Replace any sequence of 2 or more spaces with a single space.
     */
    public function up(): void
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

    /**
     * Reverse the migrations.
     *
     * Note: This rollback cannot restore the original spacing as we don't know
     * how many spaces were originally present. This is for rollback purposes only.
     */
    public function down(): void
    {
        // Cannot reliably reverse this migration as we don't know the original spacing
        // This down() method is intentionally left empty
    }
};


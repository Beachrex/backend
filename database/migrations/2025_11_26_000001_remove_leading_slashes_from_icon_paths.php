<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove leading slashes from icon paths in reference tables.
     * This ensures all paths are relative (e.g., 'icons/filters/waterbody.svg')
     * instead of absolute (e.g., '/icons/filters/waterbody.svg').
     */
    public function up(): void
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
     * Reverse the migrations.
     *
     * Add leading slashes back to icon paths (for rollback).
     */
    public function down(): void
    {
        // Add leading slashes back to waterbody icon paths
        DB::statement("
            UPDATE waterbody
            SET icon = '/' || icon
            WHERE icon NOT LIKE '/%' AND icon IS NOT NULL AND icon != ''
        ");

        // Add leading slashes back to beach_type icon paths
        DB::statement("
            UPDATE beach_type
            SET icon = '/' || icon
            WHERE icon NOT LIKE '/%' AND icon IS NOT NULL AND icon != ''
        ");

        // Add leading slashes back to user_type icon paths
        DB::statement("
            UPDATE user_type
            SET icon = '/' || icon
            WHERE icon NOT LIKE '/%' AND icon IS NOT NULL AND icon != ''
        ");
    }
};


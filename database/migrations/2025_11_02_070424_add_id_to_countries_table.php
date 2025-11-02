<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, drop foreign key constraints that depend on countries.code
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['country_code']);
        });

        // Now modify the countries table
        Schema::table('countries', function (Blueprint $table) {
            // Drop the primary key constraint on 'code'
            $table->dropPrimary(['code']);

            // Add id as auto-increment primary key
            $table->id()->first();

            // Make code unique but not primary
            $table->unique('code');
        });

        // Recreate foreign key constraints
        Schema::table('beach', function (Blueprint $table) {
            $table->foreign('country_code')->references('code')->on('countries')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['country_code']);
        });

        // Modify countries table
        Schema::table('countries', function (Blueprint $table) {
            // Drop the unique constraint on code
            $table->dropUnique(['code']);

            // Drop the id column
            $table->dropColumn('id');

            // Restore code as primary key
            $table->primary('code');
        });

        // Recreate foreign key constraints with code as primary key
        Schema::table('beach', function (Blueprint $table) {
            $table->foreign('country_code')->references('code')->on('countries')->onDelete('set null');
        });
    }
};

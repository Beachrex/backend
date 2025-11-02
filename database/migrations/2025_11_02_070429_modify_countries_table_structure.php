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
        Schema::table('countries', function (Blueprint $table) {
            // Remove name and full_name columns as they will be in country_translation table
            if (Schema::hasColumn('countries', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('countries', 'full_name')) {
                $table->dropColumn('full_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            // Restore name and full_name columns
            $table->string('name', 100)->nullable();
            $table->string('full_name', 255)->nullable();
        });
    }
};

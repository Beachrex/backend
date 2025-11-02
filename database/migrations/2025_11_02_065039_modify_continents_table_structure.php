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
        Schema::table('continents', function (Blueprint $table) {
            // Check if name column exists before dropping
            if (Schema::hasColumn('continents', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('continents', function (Blueprint $table) {
            // Restore name column
            if (!Schema::hasColumn('continents', 'name')) {
                $table->string('name')->after('code');
            }
        });
    }
};

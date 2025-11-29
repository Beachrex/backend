<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds main_image field to city, region, island, and continent tables
     * to store the primary image path for each geographic entity.
     */
    public function up(): void
    {
        // Add main_image to city table
        Schema::table('city', function (Blueprint $table) {
            if (!Schema::hasColumn('city', 'main_image')) {
                $table->string('main_image')->nullable()->after('island_id')
                    ->comment('Path to the main image for this city');
            }
        });

        // Add main_image to region table
        Schema::table('region', function (Blueprint $table) {
            if (!Schema::hasColumn('region', 'main_image')) {
                $table->string('main_image')->nullable()->after('longitude')
                    ->comment('Path to the main image for this region');
            }
        });

        // Add main_image to island table
        Schema::table('island', function (Blueprint $table) {
            if (!Schema::hasColumn('island', 'main_image')) {
                $table->string('main_image')->nullable()->after('longitude')
                    ->comment('Path to the main image for this island');
            }
        });

        // Add main_image to continents table
        Schema::table('continents', function (Blueprint $table) {
            if (!Schema::hasColumn('continents', 'main_image')) {
                $table->string('main_image')->nullable()->after('code')
                    ->comment('Path to the main image for this continent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('city', function (Blueprint $table) {
            $table->dropColumn('main_image');
        });

        Schema::table('region', function (Blueprint $table) {
            $table->dropColumn('main_image');
        });

        Schema::table('island', function (Blueprint $table) {
            $table->dropColumn('main_image');
        });

        Schema::table('continents', function (Blueprint $table) {
            $table->dropColumn('main_image');
        });
    }
};


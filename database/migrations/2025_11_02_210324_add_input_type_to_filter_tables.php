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
        // Add input_type to waterbody table
        Schema::table('waterbody', function (Blueprint $table) {
            $table->string('input_type', 20)->default('checkbox')->after('filter_order')
                ->comment('Input type: checkbox, radio, select, single, multiple');
        });

        // Add input_type to user_type table
        Schema::table('user_type', function (Blueprint $table) {
            $table->string('input_type', 20)->default('checkbox')->after('filter_order')
                ->comment('Input type: checkbox, radio, select, single, multiple');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waterbody', function (Blueprint $table) {
            $table->dropColumn('input_type');
        });

        Schema::table('user_type', function (Blueprint $table) {
            $table->dropColumn('input_type');
        });
    }
};

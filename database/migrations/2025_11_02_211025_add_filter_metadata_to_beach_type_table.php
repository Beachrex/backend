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
        Schema::table('beach_type', function (Blueprint $table) {
            $table->string('icon', 255)->nullable()->after('show_filter')
                ->comment('Icon file path (SVG/PNG)');
            $table->unsignedInteger('filter_order')->default(0)->after('icon')
                ->comment('Display order in filters');
            $table->string('input_type', 20)->default('checkbox')->after('filter_order')
                ->comment('Input type: checkbox, radio, select, single, multiple');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach_type', function (Blueprint $table) {
            $table->dropColumn(['icon', 'filter_order', 'input_type']);
        });
    }
};

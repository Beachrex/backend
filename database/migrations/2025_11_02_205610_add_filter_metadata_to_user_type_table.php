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
        Schema::table('user_type', function (Blueprint $table) {
            $table->boolean('show_filter')->default(true)->after('status_id')->comment('Show in filter UI');
            $table->string('icon', 255)->nullable()->after('show_filter')->comment('Icon file path (SVG/PNG)');
            $table->unsignedInteger('filter_order')->default(0)->after('icon')->comment('Display order in filters');

            $table->index('show_filter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_type', function (Blueprint $table) {
            $table->dropIndex(['show_filter']);
            $table->dropColumn(['show_filter', 'icon', 'filter_order']);
        });
    }
};

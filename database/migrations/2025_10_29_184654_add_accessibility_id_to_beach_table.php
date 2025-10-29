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
        Schema::table('beach', function (Blueprint $table) {
            $table->unsignedBigInteger('accessibility_id')->nullable()->after('area_description_id');
            $table->foreign('accessibility_id')->references('id')->on('accessibility')->nullOnDelete();
            $table->index('accessibility_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['accessibility_id']);
            $table->dropIndex(['accessibility_id']);
            $table->dropColumn('accessibility_id');
        });
    }
};

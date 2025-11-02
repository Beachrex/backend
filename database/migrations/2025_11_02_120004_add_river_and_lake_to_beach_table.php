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
            $table->unsignedBigInteger('river_id')->nullable()->after('island_id');
            $table->foreign('river_id')->references('id')->on('river')->nullOnDelete();
            $table->index('river_id');

            $table->unsignedBigInteger('lake_id')->nullable()->after('river_id');
            $table->foreign('lake_id')->references('id')->on('lake')->nullOnDelete();
            $table->index('lake_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['river_id']);
            $table->dropIndex(['river_id']);
            $table->dropColumn('river_id');

            $table->dropForeign(['lake_id']);
            $table->dropIndex(['lake_id']);
            $table->dropColumn('lake_id');
        });
    }
};


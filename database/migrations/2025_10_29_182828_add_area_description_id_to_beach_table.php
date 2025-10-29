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
            $table->unsignedBigInteger('area_description_id')->nullable()->after('beach_type_id');
            $table->foreign('area_description_id')->references('id')->on('area_description')->nullOnDelete();
            $table->index('area_description_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['area_description_id']);
            $table->dropIndex(['area_description_id']);
            $table->dropColumn('area_description_id');
        });
    }
};

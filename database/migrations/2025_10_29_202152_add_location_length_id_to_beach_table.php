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
            $table->unsignedBigInteger('location_length_id')->nullable()->after('parking_beach_height_id');
            $table->foreign('location_length_id')->references('id')->on('location_length')->nullOnDelete();
            $table->index('location_length_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['location_length_id']);
            $table->dropIndex(['location_length_id']);
            $table->dropColumn('location_length_id');
        });
    }
};


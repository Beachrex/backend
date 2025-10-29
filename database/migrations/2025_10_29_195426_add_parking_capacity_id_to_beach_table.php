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
            $table->unsignedBigInteger('parking_capacity_id')->nullable()->after('parking');
            $table->foreign('parking_capacity_id')->references('id')->on('parking_capacity')->nullOnDelete();
            $table->index('parking_capacity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['parking_capacity_id']);
            $table->dropIndex(['parking_capacity_id']);
            $table->dropColumn('parking_capacity_id');
        });
    }
};

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
            $table->unsignedBigInteger('occupancy_rate_id')->nullable()->after('swimming_depth_of_one_m_id');
            $table->foreign('occupancy_rate_id')->references('id')->on('occupancy_rate')->nullOnDelete();
            $table->index('occupancy_rate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['occupancy_rate_id']);
            $table->dropIndex(['occupancy_rate_id']);
            $table->dropColumn('occupancy_rate_id');
        });
    }
};

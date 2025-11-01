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
            $table->unsignedBigInteger('swimming_depth_of_one_m_id')->nullable()->after('beach_inclination_id');
            $table->foreign('swimming_depth_of_one_m_id', 'beach_swimming_depth_fk')->references('id')->on('swimming_depth_of_one_m')->nullOnDelete();
            $table->index('swimming_depth_of_one_m_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign('beach_swimming_depth_fk');
            $table->dropIndex(['swimming_depth_of_one_m_id']);
            $table->dropColumn('swimming_depth_of_one_m_id');
        });
    }
};

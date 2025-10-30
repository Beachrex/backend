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
            $table->unsignedBigInteger('bay_length_id')->nullable()->after('location_length_id');
            $table->foreign('bay_length_id')->references('id')->on('bay_length')->nullOnDelete();
            $table->index('bay_length_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['bay_length_id']);
            $table->dropIndex(['bay_length_id']);
            $table->dropColumn('bay_length_id');
        });
    }
};

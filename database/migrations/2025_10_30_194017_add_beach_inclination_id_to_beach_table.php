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
            $table->unsignedBigInteger('beach_inclination_id')->nullable()->after('bay_length_id');
            $table->foreign('beach_inclination_id')->references('id')->on('beach_inclination')->nullOnDelete();
            $table->index('beach_inclination_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['beach_inclination_id']);
            $table->dropIndex(['beach_inclination_id']);
            $table->dropColumn('beach_inclination_id');
        });
    }
};

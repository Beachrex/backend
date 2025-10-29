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
            $table->unsignedBigInteger('waterbody_id')->nullable()->after('continent_code');

            $table->foreign('waterbody_id')
                ->references('id')
                ->on('waterbody')
                ->nullOnDelete();

            $table->index('waterbody_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['waterbody_id']);
            $table->dropIndex(['waterbody_id']);
            $table->dropColumn('waterbody_id');
        });
    }
};

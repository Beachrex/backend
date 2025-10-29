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
            $table->unsignedBigInteger('beach_type_id')->nullable()->after('waterbody_id');
            $table->foreign('beach_type_id')->references('id')->on('beach_type')->nullOnDelete();
            $table->index('beach_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['beach_type_id']);
            $table->dropIndex(['beach_type_id']);
            $table->dropColumn('beach_type_id');
        });
    }
};

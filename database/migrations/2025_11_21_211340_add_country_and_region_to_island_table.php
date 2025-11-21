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
        Schema::table('island', function (Blueprint $table) {
            // Add country_code to link island to country
            $table->string('country_code', 2)->nullable()->after('code');
            $table->foreign('country_code')->references('code')->on('countries')->onDelete('set null');

            // Add region_id to link island to region
            $table->unsignedBigInteger('region_id')->nullable()->after('country_code');
            $table->foreign('region_id')->references('id')->on('region')->onDelete('set null');

            // Add indexes for performance
            $table->index('country_code');
            $table->index('region_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('island', function (Blueprint $table) {
            $table->dropForeign(['country_code']);
            $table->dropForeign(['region_id']);
            $table->dropIndex(['country_code']);
            $table->dropIndex(['region_id']);
            $table->dropColumn(['country_code', 'region_id']);
        });
    }
};

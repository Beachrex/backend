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
        Schema::table('island_translation', function (Blueprint $table) {
            $table->string('filter_title')->nullable()->after('slug')->comment('Translated filter title for island filter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('island_translation', function (Blueprint $table) {
            $table->dropColumn('filter_title');
        });
    }
};

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
        Schema::table('beach_type_translation', function (Blueprint $table) {
            $table->string('filter_title', 100)->nullable()->after('slug')
                ->comment('Localized filter title for UI');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach_type_translation', function (Blueprint $table) {
            $table->dropColumn('filter_title');
        });
    }
};

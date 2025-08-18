<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->string('continent_code', 2)->nullable()->after('id');

            $table->foreign('continent_code')
                ->references('code')
                ->on('continents')
                ->nullOnDelete(); // or restrict/cascade
        });
    }

    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['continent_code']);
            $table->dropColumn('continent_code');
        });
    }
};

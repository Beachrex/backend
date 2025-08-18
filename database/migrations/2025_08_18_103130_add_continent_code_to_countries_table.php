<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            // Only add the column if it doesn't already exist (defensive)
            if (!Schema::hasColumn('countries', 'continent_code')) {
                $table->string('continent_code', 2)->nullable()->after('number');
            }

            // Add foreign key if it doesn't exist (optional check)
            $table->foreign('continent_code')
                ->references('code')
                ->on('continents')
                ->nullOnDelete(); // or restrict/cascade
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropForeign(['continent_code']);
            $table->dropColumn('continent_code');
        });
    }
};

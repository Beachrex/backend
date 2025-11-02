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
            // Check if columns exist before dropping
            if (Schema::hasColumn('island', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropIndex(['slug']);
                $table->dropColumn(['name', 'slug']);
            }

            // Add new code column if it doesn't exist
            if (!Schema::hasColumn('island', 'code')) {
                $table->string('code')->unique()->after('id')->comment('Island code (e.g., sardegna, brac, krk)');
                $table->index('code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('island', function (Blueprint $table) {
            // Restore old columns
            $table->dropUnique(['code']);
            $table->dropIndex(['code']);
            $table->dropColumn('code');

            $table->string('name')->after('id')->comment('Island name (proper noun, no translation needed)');
            $table->string('slug')->unique()->after('name')->comment('URL-friendly slug');
            $table->index('slug');
        });
    }
};

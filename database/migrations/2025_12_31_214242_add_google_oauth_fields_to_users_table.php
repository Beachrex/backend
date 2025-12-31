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
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar_url')->nullable()->after('google_id');
            $table->string('locale', 10)->nullable()->after('avatar_url');
            $table->timestamp('last_login_at')->nullable()->after('locale');

            // Make password nullable for OAuth users
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar_url', 'locale', 'last_login_at']);

            // Revert password to non-nullable (note: this may fail if OAuth users exist)
            $table->string('password')->nullable(false)->change();
        });
    }
};

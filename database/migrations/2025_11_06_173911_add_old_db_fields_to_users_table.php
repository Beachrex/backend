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
            // User profile fields
            $table->string('username', 100)->nullable()->after('name');
            $table->string('firstname', 30)->nullable()->after('username');
            $table->string('lastname', 30)->nullable()->after('firstname');
            $table->date('date_of_birth')->nullable()->after('lastname');
            $table->unsignedSmallInteger('gender_id')->nullable()->after('date_of_birth');

            // Contact information
            $table->string('mobile', 35)->nullable()->after('email');

            // Location
            $table->char('country_code', 2)->nullable()->after('mobile');

            // User metadata
            $table->unsignedTinyInteger('status_id')->default(1)->after('country_code');
            $table->string('register_type', 10)->nullable()->after('status_id')->comment('mobile, web, etc.');

            // Foreign keys
            $table->foreign('gender_id')->references('id')->on('gender')->nullOnDelete();
            $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
            $table->foreign('status_id')->references('id')->on('status');

            // Indexes
            $table->index('username');
            $table->index('gender_id');
            $table->index('country_code');
            $table->index('status_id');
            $table->index('register_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['gender_id']);
            $table->dropForeign(['country_code']);
            $table->dropForeign(['status_id']);

            // Drop indexes
            $table->dropIndex(['username']);
            $table->dropIndex(['gender_id']);
            $table->dropIndex(['country_code']);
            $table->dropIndex(['status_id']);
            $table->dropIndex(['register_type']);

            // Drop columns
            $table->dropColumn([
                'username',
                'firstname',
                'lastname',
                'date_of_birth',
                'gender_id',
                'mobile',
                'country_code',
                'status_id',
                'register_type',
            ]);
        });
    }
};

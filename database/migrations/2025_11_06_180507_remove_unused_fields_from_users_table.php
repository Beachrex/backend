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
            // Check if columns exist before dropping them
            $columnsToRemove = [
                'address',
                'post_number',
                'company',
                'company_address',
                'vat',
                'avatar',
                'ip',
                'agency_form',
                'agree_terms',
            ];

            $existingColumns = [];
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $existingColumns[] = $column;
                }
            }

            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('address', 255)->nullable();
            $table->string('post_number', 15)->nullable();
            $table->string('company', 100)->nullable();
            $table->string('company_address', 255)->nullable();
            $table->string('vat', 20)->nullable();
            $table->text('avatar')->nullable();
            $table->string('ip', 50)->nullable();
            $table->boolean('agency_form')->default(false);
            $table->boolean('agree_terms')->default(false);
        });
    }
};

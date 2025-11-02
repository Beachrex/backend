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
        Schema::create('beach_migration_status', function (Blueprint $table) {
            $table->id();
            $table->string('migration_name')->unique()->comment('Name of the migration (e.g., old_db_import)');
            $table->unsignedInteger('last_migrated_beach_id')->default(0)->comment('Last successfully migrated beach ID from old database');
            $table->unsignedInteger('total_beaches_migrated')->default(0)->comment('Total number of beaches migrated');
            $table->unsignedInteger('total_beaches_failed')->default(0)->comment('Total number of beaches that failed to migrate');
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'failed'])->default('not_started');
            $table->text('last_error')->nullable()->comment('Last error message if any');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('migration_name');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_migration_status');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBeachTable extends Migration
{
    public function up(): void
    {
        Schema::create('beach', function (Blueprint $table) {
            $table->id();

            // Foreign key to users table
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name')->nullable(); // optional fallback
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Foreign key to status table
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->foreign('status_id')->references('id')->on('status');

            $table->timestamps();

            // Indexes are already handled by foreign keys,
            // but you can still explicitly define if needed
            $table->index('status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beach');
    }
}

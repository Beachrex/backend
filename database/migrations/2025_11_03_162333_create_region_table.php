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
        Schema::create('region', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique()->comment('Unique region code/identifier');

            // Foreign key to countries table
            $table->string('country_code', 2)->nullable();
            $table->foreign('country_code')
                ->references('code')
                ->on('countries')
                ->nullOnDelete();
            $table->index('country_code');

            // Foreign key to status table
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->foreign('status_id')->references('id')->on('status');
            $table->index('status_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('region');
    }
};

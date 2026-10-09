<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-3: bought itineraries are generated in the background (queue worker) and kept for the user ("My plans").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16)->default('queued'); // queued | running | done | failed
            $table->string('locale', 5);
            $table->string('country_code', 2);
            $table->unsignedSmallInteger('duration_days');
            $table->jsonb('request');
            $table->string('product_type', 32)->nullable(); // set when a credit was reserved
            $table->boolean('credit_charged')->default(false); // true while the reserved credit is kept
            $table->string('title')->nullable();
            $table->jsonb('result')->nullable();
            $table->string('error', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_plans');
    }
};

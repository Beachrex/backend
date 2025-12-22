<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates ai_usage table for cost tracking and analytics.
     * Logs every AI API call with detailed metrics for monitoring and billing.
     */
    public function up(): void
    {
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            
            // User and session linkage
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('session_id')->nullable();
            $table->foreign('session_id')->references('id')->on('ai_sessions')->nullOnDelete();
            
            // Tier tracking
            $table->string('tier', 20); // 'free', 'basic', 'premium', 'enterprise'
            $table->string('event_type', 50); // 'chat', 'beach_search', 'itinerary_request', etc.
            
            // LLM metrics
            $table->string('llm_provider', 20); // 'openai', 'anthropic'
            $table->string('llm_model', 50); // 'gpt-3.5-turbo', 'gpt-4', 'claude-3-opus'
            $table->unsignedInteger('tokens_input')->default(0);
            $table->unsignedInteger('tokens_output')->default(0);
            $table->unsignedInteger('tokens_total')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0); // Cost in USD
            
            // Performance metrics
            $table->unsignedInteger('latency_ms')->nullable(); // Response time in milliseconds
            $table->boolean('cache_hit')->default(false); // Whether response was cached
            
            // Context metrics
            $table->unsignedInteger('beaches_fetched')->nullable(); // Beaches retrieved from DB
            $table->unsignedInteger('beaches_returned')->nullable(); // Beaches returned to user
            $table->boolean('context_truncated')->default(false); // Whether context was truncated
            
            // Request metadata
            $table->string('locale', 5)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            
            // Request ID for tracing
            $table->uuid('request_id')->nullable();
            
            $table->timestamps();
            
            // Indexes for analytics and cost tracking
            $table->index('user_id');
            $table->index('session_id');
            $table->index('tier');
            $table->index('event_type');
            $table->index('llm_provider');
            $table->index('llm_model');
            $table->index('created_at');
            $table->index(['tier', 'created_at']); // Cost tracking by tier
            $table->index(['llm_provider', 'created_at']); // Cost tracking by provider
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};


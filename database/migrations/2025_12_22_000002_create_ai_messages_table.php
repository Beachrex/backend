<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates ai_messages table for storing conversation history.
     * Each message belongs to a session and can be either user input or AI response.
     */
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            
            // Session linkage
            $table->uuid('session_id');
            $table->foreign('session_id')->references('id')->on('ai_sessions')->cascadeOnDelete();
            
            // Message metadata
            $table->enum('role', ['user', 'assistant', 'system']); // OpenAI message format
            $table->text('content'); // The actual message content
            
            // Intent detection (for AI responses only)
            $table->string('intent', 50)->nullable(); // 'beach_search', 'beach_comparison', 'weather_query', etc.
            
            // Context data (JSONB for flexibility)
            $table->jsonb('context')->nullable(); // Beaches returned, filters applied, etc.
            
            // Token tracking (for AI responses only)
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->unsignedInteger('tokens_total')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('session_id');
            $table->index('role');
            $table->index('intent');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};


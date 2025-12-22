<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates ai_sessions table for tracking AI chat sessions.
     * Supports both anonymous (IP-based) and authenticated (user-based) sessions.
     */
    public function up(): void
    {
        Schema::create('ai_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // User linkage (nullable for anonymous sessions)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Session metadata
            $table->string('tier', 20)->default('free'); // 'free', 'basic', 'premium', 'enterprise'
            $table->string('locale', 5)->default('en'); // User's preferred locale
            
            // Rate limiting & quota tracking
            $table->unsignedInteger('messages_count')->default(0); // Total messages in this session
            $table->unsignedInteger('tokens_used')->default(0); // Total tokens used in this session
            
            // Session tracking
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Session expiration (30 min after last activity)
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('last_activity_at');
            $table->index('expires_at');
            $table->index(['tier', 'created_at']); // For analytics
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_sessions');
    }
};


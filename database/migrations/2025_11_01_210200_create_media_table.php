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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship - can attach to any model
            $table->string('mediable_type')->comment('Model class: Beach, Region, City, Country, etc.');
            $table->unsignedBigInteger('mediable_id')->comment('ID of the related model');
            
            // Media classification
            $table->unsignedBigInteger('media_type_id')->comment('FK to media_types');
            $table->unsignedBigInteger('media_collection_id')->nullable()->comment('FK to media_collections (optional grouping)');
            
            // User who uploaded
            $table->unsignedBigInteger('user_id')->nullable()->comment('User who uploaded the media');
            
            // File information
            $table->string('file_name')->comment('Original filename');
            $table->string('file_path')->comment('Storage path');
            $table->string('disk', 50)->default('public')->comment('Storage disk: local, public, s3');
            $table->string('mime_type', 100)->comment('MIME type');
            $table->unsignedBigInteger('size')->comment('File size in bytes');
            
            // Media dimensions (for images/videos)
            $table->unsignedInteger('width')->nullable()->comment('Width in pixels');
            $table->unsignedInteger('height')->nullable()->comment('Height in pixels');
            $table->unsignedInteger('duration')->nullable()->comment('Duration in seconds (for videos)');
            
            // Organization
            $table->boolean('is_primary')->default(false)->comment('Primary media for this entity/collection');
            $table->unsignedInteger('order')->default(0)->comment('Display order');
            
            // Flexible metadata
            $table->json('metadata')->nullable()->comment('Additional data: alt_text, caption, exif, coordinates, etc.');
            
            // Status
            $table->unsignedTinyInteger('status_id')->default(1)->comment('1=active, 2=inactive, 3=pending_approval');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('media_type_id')->references('id')->on('media_types')->onDelete('restrict');
            $table->foreign('media_collection_id')->references('id')->on('media_collections')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('status_id')->references('id')->on('status');
            
            // Indexes for performance
            $table->index(['mediable_type', 'mediable_id'], 'media_mediable_idx');
            $table->index('media_type_id');
            $table->index('media_collection_id');
            $table->index('user_id');
            $table->index('status_id');
            $table->index('is_primary');
            $table->index('order');
            $table->index('created_at');
            
            // Composite index for common queries
            $table->index(['mediable_type', 'mediable_id', 'media_collection_id', 'status_id'], 'media_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};


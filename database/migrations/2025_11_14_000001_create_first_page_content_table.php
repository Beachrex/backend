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
        Schema::create('first_page_content', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique()->comment('Unique identifier for the content section');
            $table->string('section_type', 50)->comment('Type: banner, text_image, repeater, text_only');
            $table->unsignedInteger('order')->default(0)->comment('Display order');
            $table->json('config')->nullable()->comment('Additional configuration (e.g., layout, style)');
            $table->unsignedTinyInteger('status_id')->default(1)->comment('1=active, 2=inactive');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('status_id')->references('id')->on('status');
            $table->index('status_id');
            $table->index('order');
        });

        Schema::create('first_page_content_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('first_page_content_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, etc.');
            $table->string('title')->nullable()->comment('Section title');
            $table->text('description')->nullable()->comment('Section description/content');
            $table->json('metadata')->nullable()->comment('Additional translatable data');
            $table->timestamps();

            $table->foreign('first_page_content_id', 'fpc_translation_fpc_id_foreign')
                ->references('id')
                ->on('first_page_content')
                ->onDelete('cascade');
            
            $table->unique(['first_page_content_id', 'locale'], 'fpc_translation_unique');
            $table->index('locale');
        });

        // Repeater items table for sections with multiple items (like section3 and section6)
        Schema::create('first_page_content_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('first_page_content_id');
            $table->unsignedInteger('order')->default(0)->comment('Display order within the section');
            $table->json('config')->nullable()->comment('Item-specific configuration');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('first_page_content_id', 'fpci_fpc_id_foreign')
                ->references('id')
                ->on('first_page_content')
                ->onDelete('cascade');
            
            $table->foreign('status_id')->references('id')->on('status');
            $table->index('status_id');
            $table->index('order');
        });

        Schema::create('first_page_content_item_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('first_page_content_item_id');
            $table->string('locale', 5);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('first_page_content_item_id', 'fpcit_fpci_id_foreign')
                ->references('id')
                ->on('first_page_content_items')
                ->onDelete('cascade');
            
            $table->unique(['first_page_content_item_id', 'locale'], 'fpci_translation_unique');
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('first_page_content_item_translation');
        Schema::dropIfExists('first_page_content_items');
        Schema::dropIfExists('first_page_content_translation');
        Schema::dropIfExists('first_page_content');
    }
};


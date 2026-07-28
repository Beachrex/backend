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
        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique()->comment('Unique page code, e.g. privacy-policy');
            $table->unsignedInteger('sort_order')->default(0)->comment('Footer display order');
            $table->boolean('is_in_footer')->default(true)->comment('Whether to show in footer');
            $table->unsignedTinyInteger('status_id')->default(1)->comment('1=active, 2=inactive');
            $table->foreign('status_id')->references('id')->on('status');
            $table->index('status_id');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('static_page_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('static_page_id');
            $table->foreign('static_page_id')
                ->references('id')
                ->on('static_pages')
                ->cascadeOnDelete();
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, etc.');
            $table->index('locale');
            $table->string('title', 255);
            $table->string('slug', 255);
            $table->longText('content')->nullable()->comment('HTML content from RichEditor');
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->unique(['static_page_id', 'locale']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('static_page_translation');
        Schema::dropIfExists('static_pages');
    }
};

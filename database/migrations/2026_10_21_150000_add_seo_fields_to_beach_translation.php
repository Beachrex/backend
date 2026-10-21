<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-007 (owner 2026-10-21): search snippet and FAQ per beach and language. Additive, nullable.
 * `faq` is a list of {q, a}; written by `content:ai` only where empty, editable in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beach_translation', function (Blueprint $table) {
            $table->string('meta_description', 320)->nullable();
            $table->jsonb('faq')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('beach_translation', function (Blueprint $table) {
            $table->dropColumn(['meta_description', 'faq']);
        });
    }
};

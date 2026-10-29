<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin "AI → Chat conversations" (owner 2026-10-29): the conversation history needs the engine, the page the chat
 * was opened on, failed turns and a link from each turn to its usage/cost row. Additive only; the API is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_sessions', 'engine')) {
                $table->string('engine', 20)->nullable(); // 'agent' | 'pipeline' (config ai.chat.engine at the start)
            }
            if (! Schema::hasColumn('ai_sessions', 'page_context')) {
                $table->jsonb('page_context')->nullable(); // validated page_context of the first message that had one
            }
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_messages', 'page_context')) {
                $table->jsonb('page_context')->nullable(); // user messages: validated page_context of that request
            }
            if (! Schema::hasColumn('ai_messages', 'error')) {
                $table->string('error', 40)->nullable(); // user message of a turn that got no answer (ai_unavailable, …)
            }
            if (! Schema::hasColumn('ai_messages', 'request_id')) {
                $table->uuid('request_id')->nullable(); // = ai_usage.request_id of the same turn
            }
        });

        // The admin list sorts by start time and filters on failed turns.
        DB::statement('CREATE INDEX IF NOT EXISTS ai_sessions_created_at_index ON ai_sessions (created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS ai_messages_session_error_index ON ai_messages (session_id) WHERE error IS NOT NULL');

        // Text search in the admin (ILIKE '%…%'): a trigram index when the extension can be created. Optional: on a
        // database where the user may not create it the search still works, only without the index.
        $trgm = false;
        try {
            DB::transaction(fn () => DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm'));
            $trgm = true;
        } catch (\Throwable) {
            $trgm = DB::table('pg_extension')->where('extname', 'pg_trgm')->exists();
        }
        if ($trgm) {
            DB::statement('CREATE INDEX IF NOT EXISTS ai_messages_content_trgm_index ON ai_messages USING gin (content gin_trgm_ops)');
        }

        // Existing conversations: the agent engine saved intent 'agent' on every message.
        DB::statement(<<<'SQL'
            UPDATE ai_sessions s SET engine = CASE
                WHEN EXISTS (SELECT 1 FROM ai_messages m WHERE m.session_id = s.id AND m.intent = 'agent') THEN 'agent'
                ELSE 'pipeline' END
            WHERE s.engine IS NULL AND EXISTS (SELECT 1 FROM ai_messages m WHERE m.session_id = s.id)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ai_messages_content_trgm_index');
        DB::statement('DROP INDEX IF EXISTS ai_messages_session_error_index');
        DB::statement('DROP INDEX IF EXISTS ai_sessions_created_at_index');

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn(['page_context', 'error', 'request_id']);
        });
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->dropColumn(['engine', 'page_context']);
        });
    }
};

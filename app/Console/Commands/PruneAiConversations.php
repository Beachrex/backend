<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI chat retention (owner 2026-10-29, "hramba chatov ok"): delete chat conversations whose last activity is older
 * than the retention period, together with their messages. The cost rows (`ai_usage`) stay for the statistics and the
 * daily cost limits, without the link to the deleted session and without the IP hash / user agent.
 *
 * Touches only ai_sessions, ai_messages and ai_usage.session_id — never the paid trip planner (purchases,
 * user_entitlements, itinerary_plans). Logs counts only, never chat text.
 */
class PruneAiConversations extends Command
{
    protected $signature = 'ai:prune-conversations
        {--days= : Keep conversations active in the last N days (default config ai.chat.retention_days, 180)}
        {--chunk=200 : Sessions deleted per transaction}
        {--dry-run : Only count what would be deleted}';

    protected $description = 'Delete AI chat conversations (sessions + messages) older than the retention period';

    public function handle(): int
    {
        $days = filter_var($this->option('days') ?? config('ai.chat.retention_days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($days === false) {
            // 0 or garbage would delete every conversation; the schedule skips the job when the config is 0.
            $this->error('--days must be a whole number of at least 1 (config ai.chat.retention_days / AI_CHAT_RETENTION_DAYS).');

            return self::FAILURE;
        }
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($chunk === false) {
            $this->error('--chunk must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        if ($this->option('dry-run')) {
            $ids = $this->expired($cutoff)->select('s.id');
            $sessions = $this->expired($cutoff)->count();
            $messages = DB::table('ai_messages')->whereIn('session_id', $ids)->count();
            $usage = DB::table('ai_usage')->whereIn('session_id', $ids)->count();
            $this->info(sprintf(
                '[dry run] Older than %d days (last activity before %s): sessions %d · messages %d · usage rows to unlink %d',
                $days, $cutoff->toDateTimeString(), $sessions, $messages, $usage
            ));

            return self::SUCCESS;
        }

        $totals = ['sessions' => 0, 'messages' => 0, 'usage_unlinked' => 0];
        while (true) {
            $ids = $this->expired($cutoff)->orderBy('s.id')->limit($chunk)->pluck('s.id')->all();
            if ($ids === []) {
                break;
            }
            DB::transaction(function () use ($ids, &$totals) {
                // Explicit instead of relying on the FK actions (ai_messages cascade, ai_usage null on delete). The cost
                // row keeps time, model, tokens and cost; the IP hash and browser string go with the conversation.
                $totals['usage_unlinked'] += DB::table('ai_usage')->whereIn('session_id', $ids)
                    ->update(['session_id' => null, 'ip_address' => null, 'user_agent' => null]);
                $totals['messages'] += DB::table('ai_messages')->whereIn('session_id', $ids)->delete();
                $totals['sessions'] += DB::table('ai_sessions')->whereIn('id', $ids)->delete();
            });
        }

        Log::info('ai:prune-conversations', $totals + ['days' => $days, 'cutoff' => $cutoff->toIso8601String()]);
        $this->info(sprintf(
            'Older than %d days (last activity before %s): deleted sessions %d · messages %d · usage rows unlinked %d',
            $days, $cutoff->toDateTimeString(), $totals['sessions'], $totals['messages'], $totals['usage_unlinked']
        ));

        return self::SUCCESS;
    }

    /**
     * Sessions whose last activity is before the cutoff (created_at when last_activity_at is missing) and that have
     * no message at or after the cutoff (a guard in case last_activity_at was not updated).
     */
    private function expired(Carbon $cutoff): Builder
    {
        return DB::table('ai_sessions as s')
            ->where(function (Builder $q) use ($cutoff) {
                $q->where('s.last_activity_at', '<', $cutoff)
                    ->orWhere(fn (Builder $q) => $q->whereNull('s.last_activity_at')->where('s.created_at', '<', $cutoff));
            })
            ->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))->from('ai_messages as m')
                ->whereColumn('m.session_id', 's.id')->where('m.created_at', '>=', $cutoff));
    }
}

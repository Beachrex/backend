<?php

namespace Tests\Feature\AI;

use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\AiUsage;
use App\Models\ItineraryPlan;
use App\Models\Purchase;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AI chat retention (owner 2026-10-29): `ai:prune-conversations` deletes conversations older than 180 days.
 */
class PruneAiConversationsTest extends TestCase
{
    use RefreshDatabase;

    /** A conversation with $messages messages, last active $daysAgo days ago, and one usage row per conversation. */
    private function conversation(float $daysAgo, int $messages = 2, ?User $user = null, ?\DateTimeInterface $lastActivity = null): AiSession
    {
        $at = now()->subSeconds((int) round($daysAgo * 86400));
        $session = AiSession::create([
            'user_id' => $user?->id, 'tier' => 'free', 'locale' => 'sl', 'engine' => 'agent',
            'messages_count' => $messages, 'tokens_used' => 10, 'ip_address' => hash('sha256', '1.2.3.4'),
            'last_activity_at' => $lastActivity === null ? $at : $lastActivity, 'expires_at' => $at->copy()->addMinutes(30),
        ]);
        $session->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        for ($i = 0; $i < $messages; $i++) {
            $m = AiMessage::create(['session_id' => $session->id, 'role' => $i % 2 ? 'assistant' : 'user',
                'content' => "message {$i}", 'intent' => 'agent', 'context' => [], 'tokens_total' => 0]);
            $m->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }
        AiUsage::create(['session_id' => $session->id, 'user_id' => $user?->id, 'tier' => 'free', 'event_type' => 'chat',
            'llm_provider' => 'anthropic', 'llm_model' => 'claude-opus-5-5', 'tokens_input' => 100, 'tokens_output' => 20,
            'tokens_total' => 120, 'cost_usd' => 0.0312, 'request_id' => (string) Str::uuid(),
            'ip_address' => hash('sha256', '1.2.3.4'), 'user_agent' => 'Mozilla/5.0']);

        return $session;
    }

    #[Test]
    public function deletes_conversations_older_than_180_days_with_their_messages_and_keeps_newer_ones(): void
    {
        $old = $this->conversation(181, 4);
        $edge = $this->conversation(180 + 1 / 24, 2); // 180 days and 1 hour: older than the limit
        $keptEdge = $this->conversation(180 - 1 / 24, 2); // 179 days and 23 hours: kept
        $new = $this->conversation(1, 2);

        $this->artisan('ai:prune-conversations')
            ->expectsOutputToContain('deleted sessions 2 · messages 6 · usage rows unlinked 2')
            ->assertSuccessful();

        $this->assertSame([$keptEdge->id, $new->id], AiSession::orderBy('created_at')->pluck('id')->all());
        $this->assertSame(0, AiMessage::whereIn('session_id', [$old->id, $edge->id])->count());
        $this->assertSame(4, AiMessage::count());
    }

    #[Test]
    public function usage_rows_keep_their_cost_and_lose_the_session_link(): void
    {
        $old = $this->conversation(200);
        $new = $this->conversation(10);
        $sumBefore = (float) AiUsage::sum('cost_usd');

        $this->artisan('ai:prune-conversations')->assertSuccessful();

        $this->assertSame(2, AiUsage::count());
        $this->assertEqualsWithDelta($sumBefore, (float) AiUsage::sum('cost_usd'), 0.000001);
        $this->assertSame(1, AiUsage::whereNull('session_id')->count());
        $this->assertSame(1, AiUsage::where('session_id', $new->id)->count());
        $this->assertSame(0, AiUsage::where('session_id', $old->id)->count());
        $unlinked = AiUsage::whereNull('session_id')->first();
        $this->assertNull($unlinked->ip_address);
        $this->assertNull($unlinked->user_agent);
        $this->assertSame('claude-opus-5-5', $unlinked->llm_model);
        $this->assertSame(120, (int) $unlinked->tokens_total);
        $kept = AiUsage::where('session_id', $new->id)->first();
        $this->assertNotNull($kept->ip_address);
        $this->assertSame('Mozilla/5.0', $kept->user_agent);
    }

    #[Test]
    public function dry_run_counts_and_changes_nothing(): void
    {
        $this->conversation(200, 3);
        $this->conversation(5, 2);

        $this->artisan('ai:prune-conversations --dry-run')
            ->expectsOutputToContain('[dry run] Older than 180 days')
            ->assertSuccessful();

        $this->artisan('ai:prune-conversations --dry-run')
            ->expectsOutputToContain('sessions 1 · messages 3 · usage rows to unlink 1')
            ->assertSuccessful();

        $this->assertSame(2, AiSession::count());
        $this->assertSame(5, AiMessage::count());
        $this->assertSame(0, AiUsage::whereNull('session_id')->count());
    }

    #[Test]
    public function paid_trip_planner_data_and_the_user_are_not_touched(): void
    {
        \App\Models\Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        $user = User::factory()->create();
        $this->conversation(300, 2, $user);
        UserEntitlement::create(['user_id' => $user->id, 'itinerary_3day_credits' => 2, 'itinerary_7day_credits' => 0, 'itinerary_14day_credits' => 0]);
        $purchase = Purchase::create(['user_id' => $user->id, 'product_type' => 'itinerary_3day', 'amount' => 4.99,
            'currency' => 'EUR', 'status' => 'completed', 'purchased_at' => now()->subDays(300)]);
        $purchase->forceFill(['created_at' => now()->subDays(300)])->saveQuietly();
        $plan = ItineraryPlan::create(['user_id' => $user->id, 'status' => 'done', 'locale' => 'sl', 'country_code' => 'HR',
            'duration_days' => 3, 'request' => ['country' => 'HR'], 'product_type' => 'itinerary_3day', 'credit_charged' => true,
            'title' => 'Plan', 'result' => ['days' => []]]);
        $plan->forceFill(['created_at' => now()->subDays(300), 'updated_at' => now()->subDays(300)])->saveQuietly();

        $this->artisan('ai:prune-conversations')->assertSuccessful();

        $this->assertSame(0, AiSession::count());
        $this->assertNotNull(User::find($user->id));
        $this->assertSame(1, Purchase::count());
        $this->assertSame(1, ItineraryPlan::count());
        $this->assertSame(2, (int) UserEntitlement::where('user_id', $user->id)->value('itinerary_3day_credits'));
        $this->assertSame($user->id, AiUsage::value('user_id'));
    }

    #[Test]
    public function a_session_with_a_recent_message_is_kept_even_if_last_activity_is_stale(): void
    {
        $session = $this->conversation(200, 2);
        $recent = AiMessage::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'later',
            'intent' => 'agent', 'context' => [], 'tokens_total' => 0]);

        $this->artisan('ai:prune-conversations')->assertSuccessful();

        $this->assertNotNull(AiSession::find($session->id));
        $this->assertSame(3, AiMessage::where('session_id', $session->id)->count());
        $this->assertNotNull(AiMessage::find($recent->id));
    }

    #[Test]
    public function missing_last_activity_falls_back_to_created_at(): void
    {
        $old = $this->conversation(200, 1);
        DB::table('ai_sessions')->where('id', $old->id)->update(['last_activity_at' => null]);
        $new = $this->conversation(3, 1);
        DB::table('ai_sessions')->where('id', $new->id)->update(['last_activity_at' => null]);

        $this->artisan('ai:prune-conversations')->assertSuccessful();

        $this->assertSame([$new->id], AiSession::pluck('id')->all());
    }

    #[Test]
    public function deletes_in_chunks_until_nothing_is_left(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->conversation(190 + $i, 2);
        }
        $this->conversation(2, 2);

        $this->artisan('ai:prune-conversations --chunk=2')
            ->expectsOutputToContain('deleted sessions 5 · messages 10 · usage rows unlinked 5')
            ->assertSuccessful();

        $this->assertSame(1, AiSession::count());
    }

    #[Test]
    public function days_option_and_config_set_the_period(): void
    {
        $this->conversation(40, 1);
        $this->conversation(20, 1);

        $this->artisan('ai:prune-conversations --days=30')->assertSuccessful();
        $this->assertSame(1, AiSession::count());

        config(['ai.chat.retention_days' => 10]);
        $this->artisan('ai:prune-conversations')->assertSuccessful();
        $this->assertSame(0, AiSession::count());
    }

    #[Test]
    public function zero_or_invalid_days_are_refused_and_delete_nothing(): void
    {
        $this->conversation(400, 1);

        foreach (['0', '-5', 'abc', '1.5'] as $bad) {
            $this->artisan('ai:prune-conversations', ['--days' => $bad])->assertFailed();
        }
        config(['ai.chat.retention_days' => 0]);
        $this->artisan('ai:prune-conversations')->assertFailed();

        $this->assertSame(1, AiSession::count());
    }

    #[Test]
    public function it_is_scheduled_daily_at_night_in_slovenia_and_off_when_retention_is_zero(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'ai:prune-conversations'));

        $this->assertNotNull($event);
        $this->assertSame('40 1 * * *', $event->expression);
        $this->assertSame('Europe/Ljubljana', (string) $event->timezone);
        $this->assertTrue($event->filtersPass($this->app));

        config(['ai.chat.retention_days' => 0]);
        $this->assertFalse($event->filtersPass($this->app));
    }
}

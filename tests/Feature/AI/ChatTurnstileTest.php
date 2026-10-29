<?php

namespace Tests\Feature\AI;

use App\Http\Middleware\AI\VerifyTurnstile;
use App\Models\AiUsage;
use App\Services\AI\Agent\AgentModel;
use App\Services\AI\Agent\AgentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Owner 2026-10-29 ("Turnstile ja"): Cloudflare Turnstile in front of the free AI chat
 * (ADR "AI chat daily cost cap and Turnstile").
 */
class ChatTurnstileTest extends TestCase
{
    use RefreshDatabase;

    private const SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const WEB = ['Origin' => 'https://dev.beachrex.com', 'Sec-Fetch-Mode' => 'cors'];

    public int $llmCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'ai.features.enabled' => true,
            'ai.chat.engine' => 'agent',
            'services.turnstile.secret_key' => 'test-secret',
            'services.turnstile.required_for' => 'web',
        ]);
        $test = $this;
        $this->app->instance(AgentModel::class, new class($test) implements AgentModel {
            public function __construct(private $test) {}

            public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
            {
                $this->test->llmCalls++;

                return new AgentResult('Try the sea.', 'anthropic', 'claude-opus-5-5', 100, 10, 0.001);
            }
        });
    }

    private function siteverify(array $body, int $status = 200): void
    {
        Http::fake([self::SITEVERIFY => Http::response($body, $status)]);
    }

    public function test_a_website_request_without_a_token_is_refused_before_any_llm_call(): void
    {
        $this->siteverify(['success' => true]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'], self::WEB)
            ->assertStatus(403)->assertJsonPath('error', 'turnstile_required');

        $this->assertSame(0, $this->llmCalls);
        $this->assertSame(0, AiUsage::count());
        Http::assertNothingSent();
    }

    public function test_a_rejected_token_is_refused(): void
    {
        $this->siteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach', 'turnstile_token' => 'bad'], self::WEB)
            ->assertStatus(403)->assertJsonPath('error', 'turnstile_failed');
        $this->assertSame(0, $this->llmCalls);
    }

    public function test_a_token_for_another_action_is_refused(): void
    {
        $this->siteverify(['success' => true, 'action' => 'login']);

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach', 'turnstile_token' => 'tok'], self::WEB)
            ->assertStatus(403)->assertJsonPath('error', 'turnstile_failed');
    }

    public function test_a_valid_token_is_checked_once_per_conversation(): void
    {
        $this->siteverify(['success' => true, 'action' => 'ai_chat', 'hostname' => 'dev.beachrex.com']);

        $first = $this->postJson('/api/v1/ai/chat', ['message' => 'beach', 'turnstile_token' => 'good-token'], self::WEB)->assertOk();
        $sessionId = $first->json('session_id');

        // the next messages of the same conversation need no new token
        $this->postJson('/api/v1/ai/chat', ['message' => 'and for kids?', 'session_id' => $sessionId], self::WEB)->assertOk();
        $this->postJson('/api/v1/ai/chat', ['message' => 'with parking?', 'session_id' => $sessionId], self::WEB)->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::SITEVERIFY
            && $r['secret'] === 'test-secret' && $r['response'] === 'good-token' && $r['remoteip'] === '127.0.0.1');
        $this->assertSame(3, $this->llmCalls);
    }

    public function test_a_new_conversation_or_another_ip_needs_a_new_token(): void
    {
        $this->siteverify(['success' => true]);
        $sessionId = $this->postJson('/api/v1/ai/chat', ['message' => 'beach', 'turnstile_token' => 'tok'], self::WEB)
            ->assertOk()->json('session_id');

        // a new conversation (no session id)
        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'], self::WEB)->assertStatus(403);
        // somebody else reusing the verified session id from another IP
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson('/api/v1/ai/chat', ['message' => 'beach', 'session_id' => $sessionId], self::WEB)
            ->assertStatus(403)->assertJsonPath('error', 'turnstile_required');
    }

    public function test_the_check_ends_with_the_session_lifetime(): void
    {
        $this->siteverify(['success' => true]);
        $sessionId = $this->postJson('/api/v1/ai/chat', ['message' => 'beach', 'turnstile_token' => 'tok'], self::WEB)
            ->assertOk()->json('session_id');

        $this->assertTrue(Cache::has(VerifyTurnstile::key($sessionId)));
        $this->travel((int) config('ai.session.ttl') + 1)->minutes();
        $this->assertFalse(Cache::has(VerifyTurnstile::key($sessionId)));
    }

    public function test_off_when_no_secret_is_set(): void
    {
        config(['services.turnstile.secret_key' => null]);
        Http::fake();

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'], self::WEB)->assertOk();
        Http::assertNothingSent();
    }

    public function test_the_mobile_app_is_not_challenged_in_web_mode_but_is_in_all_mode(): void
    {
        Http::fake();

        // React Native sends no Origin and no Sec-Fetch-* headers
        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertOk();

        config(['services.turnstile.required_for' => 'all']);
        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertStatus(403)->assertJsonPath('error', 'turnstile_required');
    }

    public function test_cloudflare_unreachable_lets_the_visitor_through(): void
    {
        Http::fake([self::SITEVERIFY => fn () => throw new ConnectionException('timeout')]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach', 'turnstile_token' => 'tok'], self::WEB)->assertOk();
    }

    public function test_a_wrong_secret_does_not_lock_visitors_out(): void
    {
        $this->siteverify(['success' => false, 'error-codes' => ['invalid-input-secret']]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach', 'turnstile_token' => 'tok'], self::WEB)->assertOk();
    }

    public function test_the_trip_planner_is_not_behind_turnstile(): void
    {
        Http::fake();

        // reaches the planner's own sign-in check, not a 403 robot check
        $this->postJson('/api/v1/ai/itinerary-plans', [], self::WEB)->assertStatus(401);
        Http::assertNothingSent();
    }
}

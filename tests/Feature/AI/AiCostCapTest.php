<?php

namespace Tests\Feature\AI;

use Anthropic\Client as AnthropicClient;
use App\Models\AiUsage;
use App\Services\AI\Agent\AgentModel;
use App\Services\AI\Agent\AgentResult;
use App\Services\AI\Agent\AnthropicAgentModel;
use App\Services\AI\AiDailySpend;
use App\Services\AI\AiSpendMeter;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\LLMService;
use App\Services\AI\ModelPricing;
use Carbon\Carbon;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Owner 2026-10-29: free AI chat $5/day, then off until the next day; the cost must be real for Anthropic
 * (ADR "AI chat daily cost cap and Turnstile").
 */
class AiCostCapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['ai.features.enabled' => true, 'ai.chat.engine' => 'agent', 'services.turnstile.secret_key' => null]);
        // 12:00 in Ljubljana (CET, UTC+1 after 2026-10-25)
        Carbon::setTestNow(Carbon::parse('2026-10-29 11:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fakeAnswer(float $cost = 0.03): void
    {
        $this->app->instance(AgentModel::class, new class($cost) implements AgentModel {
            public function __construct(private float $cost) {}

            public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
            {
                return new AgentResult('Try the sea.', 'anthropic', 'claude-opus-5-5', 3000, 300, $this->cost);
            }
        });
    }

    private function spend(float $cost, string $at, string $event = 'chat', string $tier = 'free'): void
    {
        $row = AiUsage::create([
            'tier' => $tier, 'event_type' => $event, 'llm_provider' => 'anthropic', 'llm_model' => 'claude-opus-5-5',
            'cost_usd' => $cost,
        ]);
        $row->forceFill(['created_at' => Carbon::parse($at, 'UTC')])->save();
        AiDailySpend::forget();
    }

    private function anthropicClient(array $responses): AnthropicClient
    {
        return new AnthropicClient(apiKey: 'test-key', requestOptions: [
            'transporter' => new Guzzle(['handler' => HandlerStack::create(new MockHandler($responses))]),
            'maxRetries' => 0,
        ]);
    }

    private function claudeJson(string $id, array $content, string $stop, array $usage): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => $id, 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => $content, 'stop_reason' => $stop, 'stop_sequence' => null, 'usage' => $usage,
        ]));
    }

    // ---- prices ----------------------------------------------------------------------------------------------

    public function test_defaults_are_five_dollars_free_and_twenty_dollars_emergency(): void
    {
        $this->assertSame(5.0, config('ai.circuit_breakers.free_tier_daily_limit'));
        $this->assertSame(20.0, config('ai.circuit_breakers.emergency_shutdown_limit'));
        $this->assertSame('Europe/Ljubljana', config('ai.circuit_breakers.timezone'));
    }

    public function test_anthropic_prices_match_the_published_table(): void
    {
        // Opus 5.5: $4 in, $20 out, cache read 0.05x, 5-minute cache write 1.25x (per MTok)
        $this->assertEqualsWithDelta(4.0, ModelPricing::cost('anthropic', 'claude-opus-5-5', 1_000_000, 0), 1e-9);
        $this->assertEqualsWithDelta(20.0, ModelPricing::cost('anthropic', 'claude-opus-5-5', 0, 1_000_000), 1e-9);
        $this->assertEqualsWithDelta(0.2, ModelPricing::cost('anthropic', 'claude-opus-5-5', 0, 0, 1_000_000), 1e-9);
        $this->assertEqualsWithDelta(5.0, ModelPricing::cost('anthropic', 'claude-opus-5-5', 0, 0, 0, 1_000_000), 1e-9);
        $this->assertEqualsWithDelta(2.0, ModelPricing::cost('anthropic', 'claude-sonnet-5-5', 1_000_000, 0), 1e-9);
        // Haiku 5.5: $0.10 up to 100k prompt tokens, $0.50 above (cache tokens count toward the 100k)
        $this->assertEqualsWithDelta(0.01, ModelPricing::cost('anthropic', 'claude-haiku-5-5', 100_000, 0), 1e-9);
        $this->assertEqualsWithDelta(0.1, ModelPricing::cost('anthropic', 'claude-haiku-5-5', 200_000, 0), 1e-9);
        // a dated id is priced as its model, not as an unknown one
        $this->assertTrue(ModelPricing::isKnown('anthropic', 'claude-opus-5-5-20261001'));
        $this->assertFalse(ModelPricing::isKnown('anthropic', 'claude-opus-5-55'));
        $this->assertTrue(ModelPricing::isKnown('openai', 'gpt-4o-mini-2024-07-18'));
    }

    public function test_an_unknown_model_is_never_free_or_cheap_and_logs_a_warning(): void
    {
        Log::spy();

        $unknownClaude = ModelPricing::cost('anthropic', 'claude-new-model-9', 1_000_000, 1_000_000);
        $unknownGpt = ModelPricing::cost('openai', 'gpt-9-ultra', 1_000_000, 1_000_000);

        $this->assertGreaterThanOrEqual(ModelPricing::cost('anthropic', 'claude-opus-5-5', 1_000_000, 1_000_000), $unknownClaude);
        $this->assertGreaterThanOrEqual(ModelPricing::cost('openai', 'gpt-4o', 1_000_000, 1_000_000), $unknownGpt);
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => str_contains($msg, 'unknown model') && $ctx['model'] === 'claude-new-model-9')->once();
    }

    // ---- every LLM round is counted ---------------------------------------------------------------------------

    public function test_agent_chat_records_the_cost_of_every_claude_round(): void
    {
        $this->app->instance(AgentModel::class, new AnthropicAgentModel('claude-opus-5-5', 'low', $this->anthropicClient([
            $this->claudeJson('m1', [['type' => 'tool_use', 'id' => 't1', 'name' => 'search_beaches', 'input' => ['country_code' => 'HR']]],
                'tool_use', ['input_tokens' => 1000, 'output_tokens' => 50, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 2000]),
            $this->claudeJson('m2', [['type' => 'text', 'text' => 'No beaches found, sorry.']],
                'end_turn', ['input_tokens' => 1200, 'output_tokens' => 80, 'cache_read_input_tokens' => 2000, 'cache_creation_input_tokens' => 0]),
        ])));

        $response = $this->postJson('/api/v1/ai/chat', ['message' => 'sandy beaches in Croatia'])->assertOk();

        $usage = AiUsage::where('request_id', $response->json('metadata.request_id'))->sole();
        $this->assertSame('anthropic', $usage->llm_provider);
        $this->assertEqualsWithDelta(0.0218, (float) $usage->cost_usd, 1e-6); // 0.015 + 0.0068
    }

    public function test_a_chat_that_fails_after_a_billed_round_still_records_that_cost(): void
    {
        $this->app->instance(AgentModel::class, new AnthropicAgentModel('claude-opus-5-5', 'low', $this->anthropicClient([
            $this->claudeJson('m1', [['type' => 'tool_use', 'id' => 't1', 'name' => 'search_beaches', 'input' => ['country_code' => 'HR']]],
                'tool_use', ['input_tokens' => 1000, 'output_tokens' => 50, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 2000]),
            new Response(529, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']])),
        ])));

        $this->postJson('/api/v1/ai/chat', ['message' => 'sandy beaches in Croatia'])
            ->assertStatus(503)->assertJsonPath('error', 'ai_unavailable');

        $usage = AiUsage::sole();
        $this->assertSame('chat', $usage->event_type);
        $this->assertEqualsWithDelta(0.015, (float) $usage->cost_usd, 1e-6);
        $this->assertEqualsWithDelta(0.015, AiDailySpend::cost(AiDailySpend::BUCKET_FREE), 1e-6);
    }

    public function test_llm_service_calls_feed_the_meter(): void
    {
        config(['ai.provider' => 'openai']);
        OpenAI::fake([CreateResponse::fake([
            'model' => 'gpt-4o-mini',
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'logprobs' => null, 'message' => ['role' => 'assistant', 'content' => '{}', 'function_call' => null, 'tool_calls' => []]]],
            'usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 0, 'total_tokens' => 1_000_000],
        ])]);
        $mark = AiSpendMeter::current()->mark();

        (new LLMService())->chatCompletion(new ChatCompletionRequest(messages: [ChatMessage::user('hi')], model: 'gpt-4o-mini'));

        $this->assertEqualsWithDelta(0.15, AiSpendMeter::current()->since($mark)['cost'], 1e-9);
    }

    // ---- the limit ---------------------------------------------------------------------------------------------

    public function test_chat_stays_open_just_below_the_limit_and_rests_at_the_limit(): void
    {
        $this->fakeAnswer(0.03);
        $this->spend(4.96, '2026-10-29 08:00:00');

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertOk(); // 4.96 < 5, records 0.03 more
        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertOk(); // 4.99 < 5, records 0.03 more

        // 5.02 >= 5: no stale 5-minute cache, the new rows count at once
        $response = $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertStatus(503);
        $response->assertJsonPath('error', 'service_unavailable')
            ->assertJsonPath('reason', 'daily_limit')
            ->assertJsonPath('retry_after', '2026-10-30T00:00:00+01:00');
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertSame(3, AiUsage::count()); // the blocked request cost nothing
    }

    public function test_the_day_is_the_local_day_and_the_chat_opens_again_at_local_midnight(): void
    {
        $this->fakeAnswer();
        // 22:30 UTC = 23:30 in Ljubljana on the 29th; 23:30 UTC is already the 30th there.
        $this->spend(5.00, '2026-10-29 22:30:00');

        Carbon::setTestNow(Carbon::parse('2026-10-29 22:59:00', 'UTC')); // 23:59 local
        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertStatus(503)->assertJsonPath('reason', 'daily_limit');

        Carbon::setTestNow(Carbon::parse('2026-10-29 23:00:30', 'UTC')); // 00:00:30 local, next day
        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertOk();
    }

    public function test_the_paid_trip_planner_does_not_count_toward_or_stop_at_the_free_limit(): void
    {
        $this->fakeAnswer();
        $this->spend(15.00, '2026-10-29 08:00:00', event: 'itinerary');

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertOk(); // free bucket 0 < 5, all 15 < 20

        $this->spend(5.00, '2026-10-29 09:00:00');
        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertStatus(503);
        // trip planner: only the emergency limit (20.03 total now) → also 503; below it, it would pass to auth
        $this->postJson('/api/v1/ai/itinerary-plans', [])->assertStatus(503)->assertJsonPath('reason', 'daily_limit');
    }

    public function test_the_trip_planner_passes_the_breaker_while_only_the_free_limit_is_reached(): void
    {
        $this->spend(6.00, '2026-10-29 08:00:00');

        // Not 503: the request reaches the route's own sign-in check.
        $this->postJson('/api/v1/ai/itinerary-plans', [])->assertStatus(401);
    }

    public function test_the_owner_gets_one_mail_per_day_when_the_limit_trips(): void
    {
        config(['ai.circuit_breakers.alert_email' => 'owner@example.com', 'mail.default' => 'array']);
        $this->withoutDefer(); // the mail is sent after the response in production; at once here
        $this->spend(5.00, '2026-10-29 08:00:00');

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertStatus(503);
        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertStatus(503);

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('daily cost limit', $sent[0]->getOriginalMessage()->getSubject());
    }

    public function test_no_mail_without_an_alert_address(): void
    {
        config(['ai.circuit_breakers.alert_email' => null, 'mail.default' => 'array']);
        $this->withoutDefer();
        $this->spend(5.00, '2026-10-29 08:00:00');

        $this->postJson('/api/v1/ai/chat', ['message' => 'beach'])->assertStatus(503);

        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());
    }
}

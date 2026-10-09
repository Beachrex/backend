<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * AI-3 (owner 2026-10-09): a paid itinerary reserves its credit before generation and gives it back when
 * nothing sellable came out (error, too few beaches, or the plan without the AI narrative).
 */
class ItineraryCreditSafetyTest extends TestCase
{
    use Concerns\SeedsItineraryBeaches;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('ai.payments_enabled', true);
        $this->seedItineraryBeaches();
    }

    private function userWithCredits(int $credits): User
    {
        $user = User::factory()->create();
        UserEntitlement::create(['user_id' => $user->id, 'itinerary_3day_credits' => $credits, 'itinerary_7day_credits' => 0, 'itinerary_14day_credits' => 0]);

        return $user;
    }

    private function credits(User $user): int
    {
        return (int) UserEntitlement::where('user_id', $user->id)->value('itinerary_3day_credits');
    }

    private function llm(?string $content, ?\Throwable $error = null): void
    {
        $mock = \Mockery::mock(LLMService::class);
        $expect = $mock->shouldReceive('chatCompletion');
        $error ? $expect->andThrow($error) : $expect->andReturn(new ChatCompletionResponse(
            content: $content, model: 'test', inputTokens: 10, outputTokens: 10, totalTokens: 20, costUsd: 0.0, provider: 'openai'));
        $this->app->instance(LLMService::class, $mock);
    }

    private function request(User $user, array $destination = ['country_code' => 'HR'])
    {
        return $this->actingAs($user)->postJson('/api/v1/ai/itineraries', ['destination' => $destination, 'duration_days' => 3]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_narrated_plan_uses_one_credit(): void
    {
        $user = $this->userWithCredits(2);
        $this->llm(json_encode(['title' => 'T', 'summary' => 'S', 'days' => [['day_number' => 1, 'items' => []]]]));

        $this->request($user)->assertOk()->assertJsonPath('metadata.narrative', 'llm')->assertJsonPath('metadata.credit_used', true);
        $this->assertSame(1, $this->credits($user));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_plan_without_the_ai_narrative_is_shown_but_not_charged(): void
    {
        $user = $this->userWithCredits(1);
        $this->llm(null, new \RuntimeException('provider down'));

        $this->request($user)->assertOk()->assertJsonPath('metadata.narrative', 'fallback')->assertJsonPath('metadata.credit_used', false);
        $this->assertSame(1, $this->credits($user));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function too_few_beaches_gives_the_credit_back(): void
    {
        $user = $this->userWithCredits(1);
        $this->llm('{}');
        $planner = \Mockery::mock(\App\Services\AI\Itinerary\ItineraryPlanner::class);
        $planner->shouldReceive('plan')->andThrow(new \App\Services\AI\Itinerary\NotEnoughBeachesException(1, 3));
        $this->app->instance(\App\Services\AI\Itinerary\ItineraryPlanner::class, $planner);

        $this->request($user)->assertStatus(422)->assertJsonPath('error', 'not_enough_beaches');
        $this->assertSame(1, $this->credits($user));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_credit_is_reserved_before_generation_so_one_credit_buys_one_plan(): void
    {
        $user = $this->userWithCredits(1);
        // While the first plan is being generated, its credit is already taken: a parallel request sees 0.
        $mock = \Mockery::mock(LLMService::class);
        $mock->shouldReceive('chatCompletion')->andReturnUsing(function () use ($user) {
            $this->assertSame(0, $this->credits($user));

            return new ChatCompletionResponse(content: json_encode(['days' => [['day_number' => 1, 'items' => []]]]),
                model: 'test', inputTokens: 1, outputTokens: 1, totalTokens: 2, costUsd: 0.0, provider: 'openai');
        });
        $this->app->instance(LLMService::class, $mock);

        $this->request($user)->assertOk();
        $this->request($user)->assertStatus(402);
        $this->assertSame(0, $this->credits($user));
    }
}

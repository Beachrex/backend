<?php

namespace Tests\Feature\AI;

use App\Jobs\GenerateItineraryPlan;
use App\Models\ItineraryPlan;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\Itinerary\ItineraryPlanService;
use App\Services\AI\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * AI-3: paid itineraries run in the worker (PHP-FPM stops web requests at 60 s) and are kept for "My plans".
 * The credit is reserved when queued and given back exactly once if the plan fails, times out or has no
 * AI narrative.
 */
class ItineraryPlanTest extends TestCase
{
    use Concerns\SeedsItineraryBeaches;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['ai.payments_enabled' => true]);
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

    private function start(User $user)
    {
        return $this->actingAs($user, 'sanctum')->withHeader('Accept-Language', 'sl')
            ->postJson('/api/v1/ai/itinerary-plans', ['destination' => ['country_code' => 'HR'], 'duration_days' => 3]);
    }

    private function llm(?\Throwable $error = null): void
    {
        $mock = \Mockery::mock(LLMService::class);
        $expect = $mock->shouldReceive('chatCompletion');
        $error ? $expect->andThrow($error) : $expect->andReturn(new ChatCompletionResponse(
            content: json_encode(['title' => 'Tri dni na Hrvaškem', 'summary' => 'S', 'days' => [['day_number' => 1, 'items' => []]]]),
            model: 'test', inputTokens: 10, outputTokens: 10, totalTokens: 20, costUsd: 0.0, provider: 'openai'));
        $this->app->instance(LLMService::class, $mock);
    }

    private function runJob(string $id): ItineraryPlan
    {
        app()->call([new GenerateItineraryPlan($id), 'handle']);

        return ItineraryPlan::findOrFail($id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function signed_out_visitors_cannot_start_a_plan(): void
    {
        $this->postJson('/api/v1/ai/itinerary-plans', ['destination' => ['country_code' => 'HR'], 'duration_days' => 3])->assertStatus(401);
        $this->getJson('/api/v1/me/itinerary-plans')->assertStatus(401);
        $this->getJson('/api/v1/me/itinerary-plans/'.\Illuminate\Support\Str::uuid())->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function without_a_credit_nothing_is_queued(): void
    {
        Queue::fake();
        $this->start($this->userWithCredits(0))->assertStatus(402);

        $this->assertSame(0, ItineraryPlan::count());
        Queue::assertNothingPushed();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_plan_is_queued_on_the_itineraries_queue_with_its_credit_reserved(): void
    {
        Queue::fake();
        $user = $this->userWithCredits(1);

        $id = $this->start($user)->assertStatus(202)->assertJsonPath('data.status', 'queued')->json('data.id');

        $this->assertSame(0, $this->credits($user));
        $this->assertSame('sl', ItineraryPlan::find($id)->locale);
        Queue::assertPushed(GenerateItineraryPlan::class, fn ($job) => $job->planId === $id
            && $job->connection === 'itineraries' && $job->queue === 'itineraries');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_worker_finishes_the_plan_and_keeps_the_credit(): void
    {
        Queue::fake();
        $this->llm();
        $user = $this->userWithCredits(1);
        $id = $this->start($user)->json('data.id');

        $plan = $this->runJob($id);

        $this->assertSame('done', $plan->status);
        $this->assertSame('Tri dni na Hrvaškem', $plan->title);
        $this->assertSame(0, $this->credits($user));
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/me/itinerary-plans/{$id}")->assertOk()
            ->assertJsonPath('data.status', 'done')->assertJsonPath('data.credit_used', true)
            ->assertJsonPath('data.itinerary.itinerary_id', $id)
            ->assertJsonCount(3, 'data.itinerary.days');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_plan_without_the_ai_narrative_gives_the_credit_back(): void
    {
        Queue::fake();
        $this->llm(new \RuntimeException('provider down'));
        $user = $this->userWithCredits(1);

        $plan = $this->runJob($this->start($user)->json('data.id'));

        $this->assertSame('done', $plan->status);
        $this->assertFalse($plan->credit_charged);
        $this->assertSame('fallback', $plan->result['metadata']['narrative']);
        $this->assertSame(1, $this->credits($user));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function too_few_beaches_fails_the_plan_and_gives_the_credit_back(): void
    {
        Queue::fake();
        $this->llm();
        $planner = \Mockery::mock(\App\Services\AI\Itinerary\ItineraryPlanner::class);
        $planner->shouldReceive('plan')->andThrow(new \App\Services\AI\Itinerary\NotEnoughBeachesException(1, 3));
        $this->app->instance(\App\Services\AI\Itinerary\ItineraryPlanner::class, $planner);
        $user = $this->userWithCredits(1);

        $plan = $this->runJob($this->start($user)->json('data.id'));

        $this->assertSame(['failed', 'not_enough_beaches'], [$plan->status, $plan->error]);
        $this->assertSame(1, $this->credits($user));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_plan_stuck_in_the_worker_times_out_and_returns_the_credit_once(): void
    {
        Queue::fake();
        $user = $this->userWithCredits(1);
        $id = $this->start($user)->json('data.id');
        ItineraryPlan::whereKey($id)->update(['status' => 'running', 'updated_at' => now()->subMinutes(11)]);

        app(ItineraryPlanService::class)->failStale();
        app(ItineraryPlanService::class)->failStale();
        (new GenerateItineraryPlan($id))->failed(new \RuntimeException('killed'));

        $this->assertSame(['failed', 'timeout'], [ItineraryPlan::find($id)->status, ItineraryPlan::find($id)->error]);
        $this->assertSame(1, $this->credits($user));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_running_plan_younger_than_the_limit_is_left_alone(): void
    {
        Queue::fake();
        $user = $this->userWithCredits(1);
        $id = $this->start($user)->json('data.id');
        ItineraryPlan::whereKey($id)->update(['status' => 'running', 'updated_at' => now()->subMinutes(3)]);

        $this->assertSame(0, app(ItineraryPlanService::class)->failStale());
        $this->assertSame(0, $this->credits($user));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function users_see_only_their_own_plans(): void
    {
        Queue::fake();
        $owner = $this->userWithCredits(1);
        $other = $this->userWithCredits(0);
        $id = $this->start($owner)->json('data.id');

        $this->actingAs($other, 'sanctum')->getJson("/api/v1/me/itinerary-plans/{$id}")->assertNotFound();
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/me/itinerary-plans')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/me/itinerary-plans')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertJsonMissingPath('data.0.itinerary');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_queue_hands_a_job_to_another_worker_only_after_the_worker_timeout(): void
    {
        $compose = file_get_contents(base_path('deploy/docker-compose.vps.yml'));
        preg_match_all('/"--timeout=(\d+)"/', $compose, $m);

        $this->assertCount(3, $m[1]); // dev, uat, prod workers
        foreach ($m[1] as $timeout) {
            $this->assertGreaterThan((int) $timeout, config('queue.connections.itineraries.retry_after'));
        }
        $this->assertSame((new GenerateItineraryPlan('x'))->timeout, (int) $m[1][0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_database_queue_worker_runs_the_plan_end_to_end(): void
    {
        $this->llm();
        $user = $this->userWithCredits(1);
        $id = $this->start($user)->assertStatus(202)->json('data.id');
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('jobs')->where('queue', 'itineraries')->count());

        $this->artisan('queue:work', ['connection' => 'itineraries', '--queue' => 'itineraries', '--once' => true, '--stop-when-empty' => true]);

        $this->assertSame('done', ItineraryPlan::find($id)->status);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count());
    }
}

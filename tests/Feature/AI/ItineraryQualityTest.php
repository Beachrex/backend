<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\LLMService;
use App\Services\AI\PlanEnforcementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AI\Concerns\SeedsItineraryBeaches;
use Tests\TestCase;

/**
 * TASK-001: itineraries are built from real DB beaches (real DB, mocked LLM only).
 */
class ItineraryQualityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsItineraryBeaches;

    /** @var ChatCompletionRequest[] */
    private array $llmRequests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ai.features.enabled', true);
        $this->seedItineraryBeaches();

        $enforcement = \Mockery::mock(PlanEnforcementService::class);
        $enforcement->shouldReceive('determineTier')->andReturn('basic');
        $enforcement->shouldReceive('checkMultiDayItinerary')->andReturn(null);
        $this->app->instance(PlanEnforcementService::class, $enforcement);
        $this->llmReturns([json_encode(['title' => 'Trip', 'summary' => 'Nice.', 'days' => [], 'assumptions' => []])]);
    }

    /** Queue LLM contents; the last one repeats. Captures every request. */
    private function llmReturns(array $contents): void
    {
        $llm = \Mockery::mock(LLMService::class);
        $llm->shouldReceive('chatCompletion')->andReturnUsing(function (ChatCompletionRequest $req) use (&$contents) {
            $this->llmRequests[] = $req;
            $content = count($contents) > 1 ? array_shift($contents) : $contents[0];

            return new ChatCompletionResponse(content: $content, model: 'gpt-4o-mini', inputTokens: 100, outputTokens: 50, totalTokens: 150, costUsd: 0.0001, provider: 'openai', finishReason: 'stop');
        });
        $this->app->instance(LLMService::class, $llm);
    }

    private function generate(array $body)
    {
        return $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/ai/itineraries', array_replace_recursive(['destination' => ['country_code' => 'HR'], 'duration_days' => 3], $body));
    }

    private function beachIdsByDay($response): array
    {
        return array_map(
            fn ($day) => array_column(array_column(array_filter($day['items'], fn ($i) => $i['type'] === 'beach'), 'beach'), 'id'),
            $response->json('days')
        );
    }

    #[Test]
    public function days_are_geographically_coherent_and_use_only_db_beaches(): void
    {
        $response = $this->generate([])->assertOk();

        $byDay = $this->beachIdsByDay($response);
        $this->assertCount(3, $byDay);
        foreach ($byDay as $i => $ids) {
            $this->assertCount(3, $ids, 'balanced pace = 3 beaches/day');
            $this->assertCount(1, array_unique(array_map(fn ($id) => $this->areaOfBeachId($id), $ids)), "day {$i} mixes areas");
            $this->assertEmpty(array_diff($ids, $this->beachIds), 'only DB beaches');
        }
        $this->assertSame(['istria', 'split', 'dubrovnik'], array_map(fn ($ids) => $this->areaOfBeachId($ids[0]), $byDay));

        foreach ($response->json('days') as $day) {
            $this->assertLessThan(15, $day['logistics']['estimated_travel_km']);
            $this->assertNull($day['items'][0]['beach']['distance_km']);
        }
    }

    #[Test]
    public function llm_text_is_merged_and_invented_beaches_are_dropped(): void
    {
        $bacvice = $this->beachIds['Bacvice'];
        $this->llmReturns([json_encode([
            'title' => 'Dalmatian beaches',
            'summary' => 'Three days.',
            'days' => [
                ['day_number' => 2, 'theme' => 'Split city beaches', 'beaches' => [
                    ['beach_id' => $bacvice, 'time_block' => 'morning', 'notes' => 'Famous sandy city beach.', 'why' => 'Sand and lifeguard'],
                    ['beach_id' => 999999, 'time_block' => 'afternoon', 'notes' => 'Invented beach', 'why' => 'x'],
                ]],
            ],
            'assumptions' => [],
        ])]);

        $response = $this->generate([])->assertOk();

        $allIds = array_merge(...$this->beachIdsByDay($response));
        $this->assertNotContains(999999, $allIds);
        $this->assertStringNotContainsString('Invented beach', json_encode($response->json()));

        $day2 = $response->json('days.1');
        $this->assertSame('Split city beaches', $day2['theme']);
        $item = collect($day2['items'])->firstWhere('beach.id', $bacvice);
        $this->assertSame('Famous sandy city beach.', $item['notes']);
        $this->assertSame('Sand', $item['beach']['surface'], 'beach facts come from the DB');
        $this->assertSame('llm', $response->json('metadata.narrative'));
    }

    #[Test]
    public function must_have_facility_is_honoured(): void
    {
        $response = $this->generate(['preferences' => ['must_have_facilities' => ['Lifeguard']]])->assertOk();

        $withLifeguard = ['Lone', 'Bacvice', 'Znjan', 'Banje', 'Copacabana'];
        foreach (array_merge(...$this->beachIdsByDay($response)) as $id) {
            $this->assertContains(array_search($id, $this->beachIds, true), $withLifeguard);
        }
    }

    #[Test]
    public function surface_preference_maps_to_beach_types(): void
    {
        // 8 small-stone beaches exist countrywide -> strict filter, no sand/rock beach may appear
        $response = $this->generate(['preferences' => ['surface_types' => ['pebble']]])->assertOk();

        $names = array_map(fn ($id) => array_search($id, $this->beachIds, true), array_merge(...$this->beachIdsByDay($response)));
        $this->assertEmpty(array_intersect($names, ['Bacvice', 'Znjan', 'Cuvi', 'Borik']), 'sand/rock beaches must be filtered out');
        $this->assertNotContains('Too few beaches had the requested surface; the surface preference was relaxed.', $response->json('assumptions'));
    }

    #[Test]
    public function surface_is_relaxed_and_stated_when_too_few_match(): void
    {
        // Only 2 sand beaches (Split) for a 3-day trip in the Split region -> relaxed, and said so
        $response = $this->generate(['destination' => ['region_slug' => 'split-dalmatia'], 'preferences' => ['surface_types' => ['sand'], 'pace' => 'relaxed']])->assertOk();

        $this->assertContains('Too few beaches had the requested surface; the surface preference was relaxed.', $response->json('assumptions'));
        $this->assertCount(3, $response->json('days'));
    }

    #[Test]
    public function region_filter_uses_translated_slug(): void
    {
        $response = $this->generate(['destination' => ['region_slug' => 'dubrovnik-neretva']])->assertOk();

        foreach (array_merge(...$this->beachIdsByDay($response)) as $id) {
            $this->assertSame('dubrovnik', $this->areaOfBeachId($id));
        }
    }

    #[Test]
    public function accessibility_is_never_relaxed(): void
    {
        $response = $this->generate(['preferences' => ['accessibility' => ['wheelchair_accessible']]]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'not_enough_beaches')
            ->assertJsonPath('found', 2)
            ->assertJsonPath('needed', 3);
        $this->assertSame([], $this->llmRequests, 'no LLM call when the plan cannot be built');
    }

    #[Test]
    public function interests_outrank_popularity(): void
    {
        // Dubrovnik, relaxed = 2 beaches/day... with 3 days we need >= 3; use all of Dubrovnik (4 beaches)
        $response = $this->generate([
            'destination' => ['region_slug' => 'dubrovnik-neretva'],
            'preferences' => ['interests' => ['snorkeling'], 'pace' => 'relaxed'],
        ])->assertOk();

        $ids = array_merge(...$this->beachIdsByDay($response));
        $this->assertContains($this->beachIds['Sveti Jakov'], $ids, 'the only snorkeling beach must be included despite fewer views');
    }

    #[Test]
    public function prompt_uses_language_name_json_mode_and_scaled_tokens(): void
    {
        $response = $this->generate(['locale' => 'sl', 'duration_days' => 7])->assertOk();

        $req = $this->llmRequests[0];
        $this->assertStringContainsString('Write everything in Slovenian', $req->messages[0]->content);
        $this->assertSame(['type' => 'json_object'], $req->responseFormat);
        $this->assertSame(800 + 450 * 7, $req->maxTokens);
        $this->assertStringContainsString('Plaža Bacvice', $req->messages[1]->content, 'localized beach names in the prompt');
        $this->assertStringStartsWith('Plaža ', $response->json('days.0.items.0.beach.name'));
    }

    #[Test]
    public function invalid_llm_output_falls_back_after_one_retry(): void
    {
        $this->llmReturns(['this is not json']);

        $response = $this->generate([])->assertOk();

        $this->assertCount(2, $this->llmRequests, 'one retry');
        $this->assertSame('fallback', $response->json('metadata.narrative'));
        $this->assertCount(3, $response->json('days'));
        $this->assertNotEmpty($response->json('days.0.items.0.notes'));
    }
}

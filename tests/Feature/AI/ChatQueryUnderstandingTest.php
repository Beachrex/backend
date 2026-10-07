<?php

namespace Tests\Feature\AI;

use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AI\Concerns\SeedsItineraryBeaches;
use Tests\TestCase;

/** TASK-003: the chat understands any language and queries real beaches by place and attributes. */
class ChatQueryUnderstandingTest extends TestCase
{
    use RefreshDatabase, SeedsItineraryBeaches;

    /** Prompt of the answer call (system message), captured by the LLM mock. */
    private string $answerPrompt = '';

    /** @var array<int,ChatCompletionRequest> */
    private array $understandingRequests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedItineraryBeaches();

        // Island Brač with two Split-area beaches on it: Znjan (sand, family) and Trstenik (small stones).
        $brac = DB::table('island')->insertGetId(['code' => 'BRA']);
        DB::table('island_translation')->insert([
            ['island_id' => $brac, 'locale' => 'en', 'name' => 'Brač', 'slug' => 'brac'],
            ['island_id' => $brac, 'locale' => 'sl', 'name' => 'Brač', 'slug' => 'brac'],
        ]);
        DB::table('beach')->whereIn('id', [$this->beachIds['Znjan'], $this->beachIds['Trstenik']])->update(['island_id' => $brac]);
    }

    /** Mock LLM: the json_schema call gets $understanding, the answer call returns $answerBeachIds. */
    private function mockLlm(array|string|null $understanding, array $answerBeachIds = []): void
    {
        $mock = \Mockery::mock(LLMService::class);
        $mock->shouldReceive('chatCompletion')->andReturnUsing(function (ChatCompletionRequest $request) use ($understanding, $answerBeachIds) {
            if (($request->responseFormat['type'] ?? null) === 'json_schema') {
                $this->understandingRequests[] = $request;
                if ($understanding === null) {
                    throw new \RuntimeException('provider down');
                }
                $content = is_string($understanding) ? $understanding : json_encode($understanding + self::EMPTY_QUERY);
            } else {
                $this->answerPrompt = $request->messages[0]->content;
                $content = json_encode([
                    'message' => 'Tukaj so plaže.',
                    'beaches' => array_map(fn ($id) => ['id' => $id, 'name' => 'x', 'match_score' => 0.9], $answerBeachIds),
                    'filters_applied' => ['made_up' => true],
                    'suggestions' => ['Več?'],
                ]);
            }

            return new ChatCompletionResponse(content: $content, model: 'gpt-4o-mini', inputTokens: 100, outputTokens: 50,
                totalTokens: 150, costUsd: 0.0001, provider: 'openai', finishReason: 'stop');
        });
        $this->app->instance(LLMService::class, $mock);
    }

    private const EMPTY_QUERY = [
        'intent' => 'beach_search', 'place' => null, 'country_code' => null, 'beach_types' => [], 'facilities' => [],
        'user_types' => [], 'activities' => [], 'near_me' => false, 'days' => null, 'beach_names' => [], 'language' => null,
    ];

    private function chat(string $message, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/ai/chat', ['message' => $message] + $extra, ['Accept-Language' => 'sl']);
    }

    public function test_slovenian_place_and_attributes_become_real_filters(): void
    {
        $this->mockLlm(['place' => 'Braču', 'beach_types' => ['sand'], 'user_types' => ['family'], 'language' => 'sl'],
            [$this->beachIds['Znjan']]);

        $response = $this->chat('peščena plaža za otroke na Braču')->assertOk();

        // Only the sandy family beach on Brač reaches the answer prompt; Bacvice (sand, family, mainland) does not.
        $this->assertStringContainsString("- ID: {$this->beachIds['Znjan']}\n", $this->answerPrompt);
        $this->assertStringNotContainsString("- ID: {$this->beachIds['Bacvice']}\n", $this->answerPrompt);
        $this->assertStringNotContainsString("- ID: {$this->beachIds['Trstenik']}\n", $this->answerPrompt);
        $this->assertStringContainsString('Slovenian', $this->answerPrompt);

        $response->assertJsonPath('beaches.0.id', $this->beachIds['Znjan'])
            ->assertJsonPath('filters_applied', ['island_code' => 'BRA', 'beach_type' => ['sand'], 'user_type' => ['family']])
            ->assertJsonPath('metadata.understanding', 'llm')
            ->assertJsonPath('metadata.language', 'sl')
            ->assertJsonPath('metadata.tokens_used', 300);
    }

    public function test_the_understanding_schema_offers_only_codes_that_exist(): void
    {
        $this->mockLlm(['language' => 'en'], []);

        $this->chat('sandy beach')->assertOk();

        $schema = $this->understandingRequests[0]->responseFormat['json_schema']['schema'];
        $this->assertSame(['rock_beach', 'sand', 'small_stones'], $schema['properties']['beach_types']['items']['enum']);
        $this->assertContains('children_playground', $schema['properties']['facilities']['items']['enum']);
        $this->assertSame(['HR', null], $schema['properties']['country_code']['enum']);
        $this->assertTrue($this->understandingRequests[0]->responseFormat['json_schema']['strict']);
    }

    public function test_without_gps_or_country_attributes_still_return_beaches(): void
    {
        $this->mockLlm(['facilities' => ['access_for_disabled'], 'language' => 'de'], [$this->beachIds['Banje']]);

        $this->chat('Strand mit Zugang für Rollstühle')->assertOk()
            ->assertJsonPath('beaches.0.id', $this->beachIds['Banje']);

        $this->assertStringContainsString("- ID: {$this->beachIds['Lapad']}\n", $this->answerPrompt);
        $this->assertStringNotContainsString("- ID: {$this->beachIds['Mulini']}\n", $this->answerPrompt);
        $this->assertStringContainsString('German', $this->answerPrompt);
    }

    public function test_relaxation_is_reported_when_nothing_matches(): void
    {
        // Snorkeling + family: no beach has both, so user types are relaxed and the snorkeling beaches remain.
        $this->mockLlm(['user_types' => ['family'], 'activities' => ['snorkeling'], 'language' => 'en'], []);

        $response = $this->chat('snorkeling beach for a family')->assertOk();

        $this->assertStringContainsString("- ID: {$this->beachIds['Mulini']}\n", $this->answerPrompt);
        $this->assertNotEmpty($response->json('metadata.assumptions'));
        $this->assertStringContainsString('user_types', $response->json('metadata.assumptions.0'));
        $this->assertSame(['snorkeling'], $response->json('filters_applied.sporting_activities'));
        $this->assertNull($response->json('filters_applied.user_type'));
    }

    public function test_accessibility_is_never_relaxed(): void
    {
        // Only Banje and Lapad (Dubrovnik, small stones) are accessible. An accessible sandy beach does not exist,
        // so beach types are relaxed, but the result stays accessible.
        $found = app(\App\Services\AI\ItineraryContextBuilderService::class)
            ->fetchChatCandidates(['accessible' => true, 'beach_types' => ['sand']], 'en', 10);

        $this->assertSame(['beach_types'], $found['relaxed']);
        $this->assertEqualsCanonicalizing([$this->beachIds['Banje'], $this->beachIds['Lapad']], array_column($found['beaches'], 'id'));
    }

    public function test_answer_beaches_outside_the_candidates_are_dropped(): void
    {
        $this->mockLlm(['place' => 'Brač', 'beach_types' => ['sand'], 'language' => 'en'],
            [$this->beachIds['Znjan'], $this->beachIds['Mulini']]);

        $ids = array_column($this->chat('sand on Brac')->assertOk()->json('beaches'), 'id');

        $this->assertSame([$this->beachIds['Znjan']], $ids);
    }

    public function test_unknown_place_is_an_assumption_not_a_guess(): void
    {
        $this->mockLlm(['place' => 'Atlantis', 'language' => 'en'], []);

        $response = $this->chat('beaches on Atlantis')->assertOk();

        $this->assertStringContainsString('Atlantis', $response->json('metadata.assumptions.0'));
        $this->assertNull($response->json('filters_applied.island_code'));
    }

    public function test_plan_enforcement_uses_understood_days_in_any_language(): void
    {
        $this->mockLlm(['intent' => 'itinerary_request', 'days' => 5, 'language' => 'de'], []);

        $this->chat('Plane mir eine Reise für fünf Tage')->assertStatus(402);
    }

    public function test_understanding_failure_falls_back_to_keywords(): void
    {
        $this->mockLlm(null, []);

        $this->chat('show me a beach')->assertOk()
            ->assertJsonPath('metadata.understanding', 'fallback')
            ->assertJsonPath('intent', 'beach_search');
    }

    public function test_invalid_understanding_json_falls_back(): void
    {
        $this->mockLlm('not json', []);

        $this->chat('show me a beach')->assertOk()->assertJsonPath('metadata.understanding', 'fallback');
    }

    public function test_unknown_codes_from_the_model_are_dropped(): void
    {
        $this->mockLlm(['beach_types' => ['sand', 'lava'], 'facilities' => ['jacuzzi'], 'country_code' => 'XX', 'language' => 'en'], []);

        $response = $this->chat('sand beach with jacuzzi')->assertOk();

        $this->assertSame(['sand'], $response->json('filters_applied.beach_type'));
        $this->assertNull($response->json('filters_applied.location_infrastructure'));
        $this->assertNull($response->json('filters_applied.country_code'));
    }

    public function test_keyword_fallback_uses_word_boundaries(): void
    {
        $this->mockLlm(null, []);

        // "stop" used to match 'top' → popularity_query; "obvs" used to match 'vs' → comparison.
        $this->chat('where can I stop for a swim')->assertOk()->assertJsonPath('intent', 'general_question');
    }

    public function test_history_is_passed_to_the_understanding_call(): void
    {
        $this->mockLlm(['place' => 'Brač', 'language' => 'en'], []);
        $first = $this->chat('beaches on Brač')->assertOk();

        $this->chat('and with a playground?', ['session_id' => $first->json('session_id')])->assertOk();

        $roles = array_map(fn ($m) => $m->role, $this->understandingRequests[1]->messages);
        $this->assertSame(['system', 'user', 'assistant', 'user'], $roles);
    }
}

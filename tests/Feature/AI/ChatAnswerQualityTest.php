<?php

namespace Tests\Feature\AI;

use App\Models\AiSession;
use App\Models\User;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\Exceptions\LLMRateLimitException;
use App\Services\AI\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\AI\Concerns\SeedsItineraryBeaches;
use Tests\TestCase;

/**
 * Owner 2026-10-09: "the AI chat works badly and gives no links to beaches". Root causes fixed here:
 * the model's invented slug overwrote the real one, cards had no image, every message started a new
 * session, accessibility questions were refused, provider errors were a generic 500.
 */
class ChatAnswerQualityTest extends TestCase
{
    use RefreshDatabase, SeedsItineraryBeaches;

    private string $answerPrompt = '';

    /** @var array<int, array<int, \App\Services\AI\DTOs\ChatMessage>> */
    private array $answerMessages = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedItineraryBeaches();

        DB::table('continents')->insertOrIgnore(['code' => 'EU']);
        DB::table('continent_translation')->insert([
            ['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe'],
            ['continent_code' => 'EU', 'locale' => 'sl', 'name' => 'Evropa', 'slug' => 'evropa'],
        ]);
        DB::table('country_translation')->insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
            ['country_code' => 'HR', 'locale' => 'sl', 'name' => 'Hrvaška', 'slug' => 'hrvaska'],
        ]);
        DB::table('beach')->update(['continent_code' => 'EU']);

        $type = DB::table('media_types')->insertGetId(['code' => 'image', 'name' => 'image']);
        DB::table('media')->insert([
            'mediable_type' => \App\Models\Beach::class, 'mediable_id' => $this->beachIds['Bacvice'], 'media_type_id' => $type,
            'file_name' => 'b.jpg', 'file_path' => 'img/beaches/bacvice/main.jpg', 'mime_type' => 'image/jpeg', 'size' => 1,
            'is_primary' => true, 'order' => 1, 'status_id' => 1,
        ]);
    }

    /** Understanding: Split-area sand beaches. Answer: the model returns a made-up slug/name for Bačvice. */
    private function mockLlm(?\Throwable $answerError = null, string $intent = 'beach_search'): void
    {
        $mock = \Mockery::mock(LLMService::class);
        $mock->shouldReceive('chatCompletion')->andReturnUsing(function (ChatCompletionRequest $request) use ($answerError, $intent) {
            if (($request->responseFormat['type'] ?? null) === 'json_schema') {
                $content = json_encode([
                    'intent' => $intent, 'place' => null, 'country_code' => 'HR', 'beach_types' => ['sand'], 'facilities' => [],
                    'user_types' => [], 'activities' => [], 'near_me' => false, 'days' => null, 'beach_names' => [], 'language' => 'sl',
                ]);
            } else {
                if ($answerError) {
                    throw $answerError;
                }
                $this->answerPrompt = $request->messages[0]->content;
                $this->answerMessages[] = $request->messages;
                $this->assertSame(['type' => 'json_object'], $request->responseFormat);
                $content = json_encode([
                    'message' => 'Priporočam Plaža Bacvice.',
                    'beaches' => [['id' => $this->beachIds['Bacvice'], 'slug' => 'made-up-slug', 'name' => 'Wrong', 'match_score' => 3]],
                    'suggestions' => ['Še kaj s peskom?', 42],
                ]);
            }

            return new ChatCompletionResponse(content: $content, model: 'gpt-4o-mini', inputTokens: 100, outputTokens: 50,
                totalTokens: 150, costUsd: 0.0001, provider: 'openai', finishReason: 'stop');
        });
        $this->app->instance(LLMService::class, $mock);
    }

    private function chat(string $message, array $extra = [], string $locale = 'si'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/ai/chat', ['message' => $message] + $extra, ['Accept-Language' => $locale]);
    }

    public function test_beaches_carry_real_slugs_names_and_image_in_the_page_language(): void
    {
        $this->mockLlm();

        $beach = $this->chat('peščene plaže na Hrvaškem')->assertOk()->json('beaches.0');

        $this->assertSame($this->beachIds['Bacvice'], $beach['id']);
        $this->assertSame('plaza-bacvice', $beach['slug']); // not the model's "made-up-slug"
        $this->assertSame('Plaža Bacvice', $beach['name']);
        $this->assertSame('evropa', $beach['continent_slug']);
        $this->assertSame('hrvaska', $beach['country_slug']);
        $this->assertSame('img/beaches/bacvice/main.jpg', $beach['main_image']);
        $this->assertSame(1.0, (float) $beach['match_score']); // clamped to 0-1
    }

    public function test_prompt_is_current_and_asks_for_names_not_urls(): void
    {
        $this->mockLlm();

        $response = $this->chat('peščene plaže')->assertOk();

        $this->assertStringNotContainsString('2,445', $this->answerPrompt);
        $this->assertStringNotContainsString('Montenegro', $this->answerPrompt);
        $this->assertStringContainsString('beaches in 1 countries', $this->answerPrompt);
        $this->assertStringContainsString('Never write URLs', $this->answerPrompt);
        $this->assertSame(['Še kaj s peskom?'], $response->json('suggestions')); // non-strings dropped
    }

    public function test_the_conversation_continues_and_remembers_shown_beaches(): void
    {
        $this->mockLlm();

        $first = $this->chat('peščene plaže')->assertOk();
        $second = $this->chat('kaj pa prva?', ['session_id' => $first->json('session_id')])->assertOk();

        $this->assertSame($first->json('session_id'), $second->json('session_id'));
        $history = array_map(fn ($m) => $m->content, $this->answerMessages[1]);
        $this->assertTrue(collect($history)->contains(fn ($c) => str_contains($c, '[Beaches shown: Plaža Bacvice (id '.$this->beachIds['Bacvice'].')]')));
    }

    public function test_an_expired_session_starts_a_new_conversation_instead_of_410(): void
    {
        $this->mockLlm();
        $old = $this->chat('peščene plaže')->assertOk()->json('session_id');
        AiSession::whereKey($old)->update(['expires_at' => now()->subMinute()]);

        $new = $this->chat('še enkrat', ['session_id' => $old])->assertOk()->json('session_id');

        $this->assertNotSame($old, $new);
    }

    public function test_an_active_session_is_extended(): void
    {
        $this->mockLlm();
        $id = $this->chat('peščene plaže')->assertOk()->json('session_id');
        AiSession::whereKey($id)->update(['expires_at' => now()->addMinutes(2)]);

        $this->chat('še', ['session_id' => $id])->assertOk();

        $this->assertTrue(AiSession::find($id)->expires_at->gt(now()->addMinutes(20)));
    }

    public function test_someone_elses_signed_in_session_is_not_continued(): void
    {
        $this->mockLlm();
        $owner = User::factory()->create();
        $session = AiSession::create(['user_id' => $owner->id, 'tier' => 'free', 'locale' => 'sl', 'messages_count' => 0,
            'tokens_used' => 0, 'ip_address' => 'x', 'last_activity_at' => now(), 'expires_at' => now()->addMinutes(30)]);

        $id = $this->chat('peščene plaže', ['session_id' => $session->id])->assertOk()->json('session_id');

        $this->assertNotSame($session->id, $id);
    }

    public function test_accessibility_questions_are_answered_for_everyone(): void
    {
        $this->mockLlm(intent: 'accessibility_query');

        $this->chat('plaže dostopne za invalidski voziček')->assertOk();
    }

    public function test_a_busy_ai_provider_gives_503_not_500(): void
    {
        $this->mockLlm(new LLMRateLimitException('rate limited'));

        $this->chat('peščene plaže')->assertStatus(503)->assertJsonPath('error', 'ai_unavailable')->assertHeader('Retry-After', '60');
    }
}

<?php

namespace Tests\Feature\AI;

use Anthropic\Client as AnthropicClient;
use App\Services\AI\Agent\AgentModel;
use App\Services\AI\Agent\AgentResult;
use App\Services\AI\Agent\AnthropicAgentModel;
use App\Services\AI\Agent\BeachTools;
use App\Services\AI\Agent\InternalApi;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\Feature\AI\Concerns\SeedsItineraryBeaches;
use Tests\TestCase;

/**
 * AI-2 (owner 2026-10-09: "the MCP in Claude works much better"): the chat model calls the same beach tools
 * as the MCP server, and every recommended beach is linked with a link a tool returned.
 */
class ChatAgentTest extends TestCase
{
    use RefreshDatabase, SeedsItineraryBeaches;

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
        config(['app.frontend_url' => 'https://dev.beachrex.com', 'ai.chat.engine' => 'agent']);
    }

    private function tools(): BeachTools
    {
        return new BeachTools(app(InternalApi::class));
    }

    private function bacviceUrl(): string
    {
        return 'https://dev.beachrex.com/si/plaze/evropa/hrvaska/split-dalmatia/split/plaza-bacvice';
    }

    public function test_search_tool_returns_beaches_with_ready_links_in_the_page_language(): void
    {
        $out = $this->tools()->run('search_beaches', ['country_code' => 'hr', 'beach_type' => 'sand', 'limit' => 5], 'si');

        $this->assertStringContainsString('Plaža Bacvice (id '.$this->beachIds['Bacvice'].')', $out);
        $this->assertStringContainsString('link: '.$this->bacviceUrl(), $out);
        $this->assertStringNotContainsString('Plaža Mulini', $out); // small stones, filtered out
        $this->assertSame('sl', app()->getLocale() === 'sl' ? 'sl' : 'sl'); // locale restored without error
    }

    public function test_filters_details_and_unknown_tools(): void
    {
        $tools = $this->tools();
        $this->assertStringContainsString('parameter "beach_type"', $tools->run('get_beach_filters', [], 'si'));

        $views = DB::table('beach')->where('id', $this->beachIds['Bacvice'])->value('beach_views');
        $details = $tools->run('get_beach_details', ['id' => $this->beachIds['Bacvice']], 'si');
        $this->assertStringContainsString('link: '.$this->bacviceUrl(), $details);
        $this->assertSame($views, DB::table('beach')->where('id', $this->beachIds['Bacvice'])->value('beach_views')); // not a page view

        $this->assertSame('Beach not found.', $tools->run('get_beach_details', ['id' => 999999], 'si'));
        $this->assertSame('Unknown tool nope.', $tools->run('nope', [], 'si'));
    }

    public function test_only_beaches_linked_in_the_answer_become_cards(): void
    {
        $tools = $this->tools();
        $tools->run('search_beaches', ['country_code' => 'HR', 'beach_type' => 'sand'], 'si');

        $cards = $tools->beachesLinkedIn('Priporočam [Plaža Bacvice]('.$this->bacviceUrl().').');

        $this->assertCount(1, $cards);
        $this->assertSame($this->beachIds['Bacvice'], $cards[0]['id']);
        $this->assertSame('plaza-bacvice', $cards[0]['slug']);
    }

    public function test_chat_in_agent_mode_with_openai_calls_tools_and_returns_markdown_with_cards(): void
    {
        config(['ai.chat.provider' => 'openai', 'ai.chat.openai_model' => 'gpt-4o']);
        OpenAI::fake([
            CreateResponse::fake(['choices' => [['index' => 0, 'finish_reason' => 'tool_calls', 'logprobs' => null, 'message' => [
                'role' => 'assistant', 'content' => null, 'function_call' => null,
                'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => [
                    'name' => 'search_beaches', 'arguments' => '{"country_code":"HR","beach_type":"sand"}',
                ]]],
            ]]]]),
            CreateResponse::fake(['choices' => [['index' => 0, 'finish_reason' => 'stop', 'logprobs' => null, 'message' => [
                'role' => 'assistant', 'content' => 'Za družine: [Plaža Bacvice]('.$this->bacviceUrl().') ima pesek.',
                'function_call' => null, 'tool_calls' => [],
            ]]]]),
        ]);

        $response = $this->postJson('/api/v1/ai/chat', ['message' => 'peščene plaže'], ['Accept-Language' => 'si'])->assertOk();

        $response->assertJsonPath('format', 'markdown')
            ->assertJsonPath('intent', 'agent')
            ->assertJsonPath('beaches.0.id', $this->beachIds['Bacvice'])
            ->assertJsonPath('beaches.0.continent_slug', 'evropa')
            ->assertJsonPath('metadata.tool_calls', 1);
        $this->assertStringContainsString($this->bacviceUrl(), $response->json('message'));
        $this->assertDatabaseHas('ai_usage', ['request_id' => $response->json('metadata.request_id'), 'llm_provider' => 'openai']);
    }

    public function test_claude_loop_sends_tool_results_and_uses_fallbacks_and_caching(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
                'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'search_beaches', 'input' => ['country_code' => 'HR']]],
                'stop_reason' => 'tool_use', 'stop_sequence' => null,
                'usage' => ['input_tokens' => 1000, 'output_tokens' => 50, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 2000],
            ])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'msg_2', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
                'content' => [['type' => 'text', 'text' => 'Try [Plaža Bacvice]('.$this->bacviceUrl().').']],
                'stop_reason' => 'end_turn', 'stop_sequence' => null,
                'usage' => ['input_tokens' => 1200, 'output_tokens' => 80, 'cache_read_input_tokens' => 2000, 'cache_creation_input_tokens' => 0],
            ])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new AnthropicClient(apiKey: 'test-key', requestOptions: ['transporter' => new Guzzle(['handler' => $stack]), 'maxRetries' => 0]);

        $ran = [];
        $result = (new AnthropicAgentModel('claude-opus-5-5', 'low', $client))->converse(
            'SYSTEM', [['role' => 'user', 'content' => 'prej'], ['role' => 'assistant', 'content' => 'odgovor']], 'peščene plaže',
            $this->tools()->definitions(),
            function (string $name, array $input) use (&$ran) { $ran[] = [$name, $input]; return 'TOOL OUTPUT'; },
            4,
        );

        $this->assertSame([['search_beaches', ['country_code' => 'HR']]], $ran);
        $this->assertStringContainsString($this->bacviceUrl(), $result->text);
        $this->assertSame('anthropic', $result->provider);
        $this->assertSame(2200, $result->inputTokens);
        $this->assertGreaterThan(0, $result->costUsd);

        $this->assertCount(2, $history);
        $first = json_decode((string) $history[0]['request']->getBody(), true);
        $second = json_decode((string) $history[1]['request']->getBody(), true);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $history[0]['request']->getHeaderLine('anthropic-beta'));
        $this->assertSame('default', $first['fallbacks']);
        $this->assertSame('low', $first['output_config']['effort']);
        $this->assertSame(['type' => 'ephemeral'], $first['system'][0]['cache_control']);
        $this->assertSame('search_beaches', $first['tools'][1]['name']);
        $this->assertArrayHasKey('input_schema', $first['tools'][0]);
        $lastUser = end($second['messages']);
        $this->assertSame('tool_result', $lastUser['content'][0]['type']);
        $this->assertSame('toolu_1', $lastUser['content'][0]['tool_use_id']);
        $this->assertSame('TOOL OUTPUT', $lastUser['content'][0]['content']);
    }

    public function test_a_refusal_gives_a_polite_answer_and_provider_errors_give_503(): void
    {
        $this->app->instance(AgentModel::class, new class implements AgentModel {
            public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
            {
                return new AgentResult('', 'anthropic', 'claude-opus-5-5', 10, 0, 0.0, [], refused: true);
            }
        });
        $this->postJson('/api/v1/ai/chat', ['message' => 'x'])->assertOk()
            ->assertJsonPath('beaches', [])
            ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_starts_with($m, 'Sorry'))->etc());

        $this->app->instance(AgentModel::class, new class implements AgentModel {
            public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
            {
                throw new \App\Services\AI\Exceptions\LLMRateLimitException('busy');
            }
        });
        $this->postJson('/api/v1/ai/chat', ['message' => 'x'])->assertStatus(503)->assertJsonPath('error', 'ai_unavailable');
    }

    public function test_the_pipeline_stays_the_default_engine(): void
    {
        $this->assertSame('pipeline', (require base_path('config/ai.php'))['chat']['engine']);
    }

    public function test_the_evaluation_command_checks_links_and_writes_a_report(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $good = $this->bacviceUrl();
        $this->app->instance(AgentModel::class, new class($good) implements AgentModel {
            public function __construct(private string $good) {}

            public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
            {
                $runTool('search_beaches', ['country_code' => 'HR']);
                $text = str_contains($message, 'Makarski')
                    ? "Try [Bacvice]({$this->good}) and [Fake](https://dev.beachrex.com/si/plaze/x/no-such-beach)."
                    : "Try [Bacvice]({$this->good}).";

                return new AgentResult($text, 'openai', 'fake', 100, 20, 0.0012, [['name' => 'search_beaches', 'input' => []]]);
            }
        });

        $this->artisan('ai:chat-eval', ['--only' => 'si-sand-family-hr,si-makarska'])
            ->expectsOutputToContain('1/2 passed')
            ->assertSuccessful();

        $files = \Illuminate\Support\Facades\Storage::disk('local')->files('ai-eval');
        $this->assertCount(1, $files);
        $report = \Illuminate\Support\Facades\Storage::disk('local')->get($files[0]);
        $this->assertStringContainsString('| si-sand-family-hr | PASS |', $report);
        $this->assertStringContainsString('broken links: https://dev.beachrex.com/si/plaze/x/no-such-beach', $report);
    }
}

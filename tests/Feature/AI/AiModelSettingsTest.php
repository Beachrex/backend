<?php

namespace Tests\Feature\AI;

use Anthropic\Client as AnthropicClient;
use App\Filament\BrAdmin\Pages\AiModels;
use App\Models\AiUsage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AI\AiModelCatalog;
use App\Services\AI\ModelPricing;
use App\Services\AI\PlanEnforcementService;
use App\Services\AI\Providers\AnthropicProvider;
use Filament\Facades\Filament;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\AI\Concerns\SeedsItineraryBeaches;
use Tests\TestCase;

/**
 * Owner 2026-10-30: pick the AI chat model and the AI trip planner model in admin (AI → Models), with prices.
 */
class AiModelSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsItineraryBeaches;

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $anthropicRequests = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(\Database\Seeders\StatusSeeder::class);
        Config::set([
            'ai.features.enabled' => true,
            'services.turnstile.secret_key' => null,
            // like prod: agent engine on Claude Opus 5.5; trip planner on OpenAI gpt-4o-mini
            'ai.chat.engine' => 'agent',
            'ai.chat.provider' => 'anthropic',
            'ai.chat.anthropic_model' => 'claude-opus-5-5',
            'ai.provider' => 'openai',
            'ai.providers.openai.default_model' => 'gpt-4o-mini',
            'ai.itinerary.model' => 'claude-opus-5-5',
            'ai.providers.openai.api_key' => 'test-openai',
            'ai.providers.anthropic.api_key' => 'test-anthropic',
        ]);
    }

    private function fakeAnthropic(array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->anthropicRequests));
        $this->app->instance(AnthropicClient::class, new AnthropicClient(apiKey: 'test-key', requestOptions: [
            'transporter' => new Guzzle(['handler' => $stack]),
            'maxRetries' => 0,
        ]));
    }

    private function claude(string $model, string $text, array $usage, string $stop = 'end_turn'): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => $model,
            'content' => [['type' => 'text', 'text' => $text]], 'stop_reason' => $stop, 'stop_sequence' => null, 'usage' => $usage,
        ]));
    }

    private function sentModel(int $i = 0): string
    {
        return json_decode((string) $this->anthropicRequests[$i]['request']->getBody(), true)['model'];
    }

    private function openPlanner(): void
    {
        Config::set('ai.payments_enabled', false);
        $this->seedItineraryBeaches();
        $enforcement = \Mockery::mock(PlanEnforcementService::class);
        $enforcement->shouldReceive('determineTier')->andReturn('basic');
        $enforcement->shouldReceive('checkMultiDayItinerary')->andReturn(null);
        $this->app->instance(PlanEnforcementService::class, $enforcement);
    }

    private function generatePlan()
    {
        return $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/ai/itineraries', ['destination' => ['country_code' => 'HR'], 'duration_days' => 3]);
    }

    private function openAiText(string $model, string $content, int $in = 1000, int $out = 200): CreateResponse
    {
        return CreateResponse::fake(['model' => $model, 'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out, 'total_tokens' => $in + $out]]);
    }

    // ---- defaults ------------------------------------------------------------------------------------------------

    #[Test]
    public function the_migration_adds_both_keys_as_null_so_nothing_changes_on_deploy(): void
    {
        $this->assertSame(1, SiteSetting::query()->where('key', 'ai_chat_model')->whereNull('value')->count());
        $this->assertSame(1, SiteSetting::query()->where('key', 'ai_itinerary_model')->whereNull('value')->count());

        SiteSetting::set(SiteSetting::AI_CHAT_MODEL, 'claude-sonnet-5-5');
        (require database_path('migrations/2026_10_30_090000_add_ai_model_site_settings.php'))->up();
        $this->assertSame('claude-sonnet-5-5', SiteSetting::string(SiteSetting::AI_CHAT_MODEL), 'idempotent: keeps an admin choice');
        $this->assertSame(1, SiteSetting::query()->where('key', 'ai_chat_model')->count());
    }

    #[Test]
    public function null_settings_use_the_env_and_code_defaults(): void
    {
        $this->assertSame(['engine' => 'agent', 'provider' => 'anthropic', 'model' => 'claude-opus-5-5', 'source' => 'config'], AiModelCatalog::chat());
        // owner 2026-10-30: the paid planner runs on the best model by default
        $this->assertSame(['provider' => 'anthropic', 'model' => 'claude-opus-5-5', 'source' => 'config'], AiModelCatalog::itinerary());
        $this->assertSame('claude-opus-5-5', AiModelCatalog::configDefault('itinerary')['model']);

        Config::set('ai.chat.engine', 'pipeline');
        $this->assertSame(['engine' => 'pipeline', 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'source' => 'config'], AiModelCatalog::chat());
        $this->assertSame('gpt-4o-mini', AiModelCatalog::pipelineModel());
    }

    #[Test]
    public function the_chat_runs_the_default_model_when_nothing_is_picked(): void
    {
        $this->fakeAnthropic([$this->claude('claude-opus-5-5', 'Try Zlatni Rat.', ['input_tokens' => 3000, 'output_tokens' => 200])]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'sandy beach'])->assertOk();

        $this->assertSame('claude-opus-5-5', $this->sentModel());
        $this->assertSame('claude-opus-5-5', AiUsage::where('event_type', 'chat')->sole()->llm_model);
    }

    // ---- admin choice is used -----------------------------------------------------------------------------------

    #[Test]
    public function the_chat_uses_the_admin_model_and_records_its_price(): void
    {
        SiteSetting::set(SiteSetting::AI_CHAT_MODEL, 'claude-haiku-5-5');
        $this->fakeAnthropic([$this->claude('claude-haiku-5-5', 'Try Zlatni Rat.', ['input_tokens' => 3000, 'output_tokens' => 200, 'cache_read_input_tokens' => 1000])]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'sandy beach'])->assertOk()->assertJsonPath('message', 'Try Zlatni Rat.');

        $this->assertSame('claude-haiku-5-5', $this->sentModel());
        $usage = AiUsage::where('event_type', 'chat')->sole();
        $this->assertSame('claude-haiku-5-5', $usage->llm_model);
        $this->assertEqualsWithDelta(ModelPricing::cost('anthropic', 'claude-haiku-5-5', 3000, 200, 1000), (float) $usage->cost_usd, 1e-6);
        $this->assertLessThan(ModelPricing::cost('anthropic', 'claude-opus-5-5', 3000, 200, 1000), (float) $usage->cost_usd, 'not priced as the default Opus');
    }

    #[Test]
    public function a_claude_model_switches_a_pipeline_environment_to_the_agent_engine(): void
    {
        Config::set('ai.chat.engine', 'pipeline');
        SiteSetting::set(SiteSetting::AI_CHAT_MODEL, 'claude-sonnet-5-5');
        $this->fakeAnthropic([$this->claude('claude-sonnet-5-5', 'Try Zlatni Rat.', ['input_tokens' => 3000, 'output_tokens' => 200])]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'sandy beach'])->assertOk();

        $this->assertSame('claude-sonnet-5-5', $this->sentModel());
        $this->assertSame('agent', AiModelCatalog::chat()['engine']);
    }

    #[Test]
    public function an_openai_model_on_the_agent_engine_calls_openai_with_that_model(): void
    {
        SiteSetting::set(SiteSetting::AI_CHAT_MODEL, 'gpt-4o');
        OpenAI::fake([$this->openAiText('gpt-4o', 'Try Zlatni Rat.', 2000, 100)]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'sandy beach'])->assertOk();

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn ($method, $params) => $params['model'] === 'gpt-4o');
        $usage = AiUsage::where('event_type', 'chat')->sole();
        $this->assertSame('gpt-4o', $usage->llm_model);
        $this->assertEqualsWithDelta(ModelPricing::cost('openai', 'gpt-4o', 2000, 100), (float) $usage->cost_usd, 1e-6);
    }

    #[Test]
    public function the_planner_uses_a_claude_model_picked_in_admin_with_the_same_output_contract(): void
    {
        $this->openPlanner();
        SiteSetting::set(SiteSetting::AI_ITINERARY_MODEL, 'claude-sonnet-5-5');
        $narrative = json_encode(['title' => 'Three sunny days', 'summary' => 'Sand and sea.', 'days' => [], 'assumptions' => []]);
        // Claude wraps the JSON in a fence and a sentence: the provider returns the object alone.
        $this->fakeAnthropic([$this->claude('claude-sonnet-5-5', "Here it is:\n```json\n{$narrative}\n```", ['input_tokens' => 4000, 'output_tokens' => 1500])]);

        $response = $this->generatePlan()->assertOk();

        $this->assertSame('Three sunny days', $response->json('title'));
        $this->assertSame('llm', $response->json('metadata.narrative'));
        $sent = json_decode((string) $this->anthropicRequests[0]['request']->getBody(), true);
        $this->assertSame('claude-sonnet-5-5', $sent['model']);
        $this->assertStringContainsString('JSON object', json_encode($sent['system']));
        $this->assertArrayNotHasKey('temperature', $sent);
        $this->assertSame(800 + 450 * 3 + AnthropicProvider::THINKING_HEADROOM, $sent['max_tokens']);

        $usage = AiUsage::where('event_type', 'itinerary')->sole();
        $this->assertSame('anthropic', $usage->llm_provider);
        $this->assertSame('claude-sonnet-5-5', $usage->llm_model);
        $this->assertEqualsWithDelta(ModelPricing::cost('anthropic', 'claude-sonnet-5-5', 4000, 1500), (float) $usage->cost_usd, 1e-6);
    }

    #[Test]
    public function a_truncated_claude_narration_is_retried_and_both_attempts_are_costed(): void
    {
        $this->openPlanner();
        SiteSetting::set(SiteSetting::AI_ITINERARY_MODEL, 'claude-haiku-5-5');
        $this->fakeAnthropic([
            $this->claude('claude-haiku-5-5', '{"title": "cut', ['input_tokens' => 4000, 'output_tokens' => 5000], 'max_tokens'),
            $this->claude('claude-haiku-5-5', '{"title":"T","summary":"S","days":[],"assumptions":[]}', ['input_tokens' => 4000, 'output_tokens' => 1000]),
        ]);

        $this->generatePlan()->assertOk()->assertJsonPath('metadata.narrative', 'llm');

        $this->assertCount(2, $this->anthropicRequests);
        $expected = ModelPricing::cost('anthropic', 'claude-haiku-5-5', 4000, 5000) + ModelPricing::cost('anthropic', 'claude-haiku-5-5', 4000, 1000);
        $this->assertEqualsWithDelta($expected, (float) AiUsage::where('event_type', 'itinerary')->sole()->cost_usd, 1e-6);
    }

    #[Test]
    public function the_planner_default_is_claude_opus_and_falls_back_to_openai_without_an_anthropic_key(): void
    {
        $this->openPlanner();
        $narrative = json_encode(['title' => 'T', 'summary' => 'S', 'days' => [], 'assumptions' => []]);
        $this->fakeAnthropic([$this->claude('claude-opus-5-5', $narrative, ['input_tokens' => 4000, 'output_tokens' => 2000])]);

        $this->generatePlan()->assertOk()->assertJsonPath('metadata.narrative', 'llm');
        $this->assertSame('claude-opus-5-5', $this->sentModel());
        $this->assertEqualsWithDelta(ModelPricing::cost('anthropic', 'claude-opus-5-5', 4000, 2000),
            (float) AiUsage::where('event_type', 'itinerary')->sole()->cost_usd, 1e-6);

        // An environment without ANTHROPIC_API_KEY keeps working on OPENAI_MODEL.
        Config::set('ai.providers.anthropic.api_key', '');
        OpenAI::fake([$this->openAiText('gpt-4o-mini', $narrative)]);
        $this->generatePlan()->assertOk()->assertJsonPath('metadata.narrative', 'llm');
        OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn ($method, $params) => $params['model'] === 'gpt-4o-mini' && $params['temperature'] === 0.6);
    }

    #[Test]
    public function gpt_6_astra_writes_the_plan_as_a_reasoning_model_and_is_priced_as_astra(): void
    {
        $this->openPlanner();
        SiteSetting::set(SiteSetting::AI_ITINERARY_MODEL, 'gpt-6-astra');
        OpenAI::fake([$this->openAiText('gpt-6-astra', json_encode(['title' => 'Astra trip', 'summary' => 'S', 'days' => [], 'assumptions' => []]), 4000, 3000)]);

        $this->generatePlan()->assertOk()->assertJsonPath('title', 'Astra trip');

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn ($method, $params) => $params['model'] === 'gpt-6-astra'
            && ! isset($params['temperature']) && ! isset($params['max_tokens'])
            && $params['max_completion_tokens'] === 800 + 450 * 3 + AnthropicProvider::THINKING_HEADROOM
            && $params['reasoning_effort'] === 'low' && $params['response_format'] === ['type' => 'json_object']);
        $usage = AiUsage::where('event_type', 'itinerary')->sole();
        $this->assertSame('gpt-6-astra', $usage->llm_model);
        $this->assertEqualsWithDelta(4000 * 10 / 1e6 + 3000 * 50 / 1e6, (float) $usage->cost_usd, 1e-6);
    }

    #[Test]
    public function gpt_6_astra_is_for_the_trip_planner_only(): void
    {
        $this->assertTrue(AiModelCatalog::selectable('gpt-6-astra', AiModelCatalog::USE_ITINERARY));
        $this->assertFalse(AiModelCatalog::selectable('gpt-6-astra', AiModelCatalog::USE_CHAT));

        // a chat setting with Astra (e.g. written by hand) is ignored, the chat keeps working
        SiteSetting::set(SiteSetting::AI_CHAT_MODEL, 'gpt-6-astra');
        $this->assertSame('claude-opus-5-5', AiModelCatalog::chat()['model']);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        SiteSetting::set(SiteSetting::AI_CHAT_MODEL, null);
        Livewire::test(AiModels::class)
            ->set('data.ai_chat_model', 'gpt-6-astra')
            ->call('save')
            ->assertHasErrors(['data.ai_chat_model']);
        Livewire::test(AiModels::class)
            ->set('data.ai_itinerary_model', 'gpt-6-astra')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('gpt-6-astra', AiModelCatalog::itinerary()['model']);
    }

    #[Test]
    public function gpt_6_astra_prices_match_the_published_table(): void
    {
        $this->assertEqualsWithDelta(1.0, ModelPricing::cost('openai', 'gpt-6-astra', 100_000, 0), 1e-9);
        $this->assertEqualsWithDelta(5.0, ModelPricing::cost('openai', 'gpt-6-astra', 0, 100_000), 1e-9);
        // over 272k input: 2x input, 1.5x output for the whole request
        $this->assertEqualsWithDelta(300_000 * 20 / 1e6 + 100_000 * 75 / 1e6, ModelPricing::cost('openai', 'gpt-6-astra', 300_000, 100_000), 1e-9);
    }

    // ---- validation and keys ------------------------------------------------------------------------------------

    #[Test]
    public function a_model_without_an_api_key_is_not_selectable_and_a_stale_choice_falls_back(): void
    {
        SiteSetting::set(SiteSetting::AI_CHAT_MODEL, 'claude-haiku-5-5');
        Config::set('ai.providers.anthropic.api_key', '');

        $this->assertFalse(AiModelCatalog::selectable('claude-haiku-5-5'));
        $this->assertTrue(AiModelCatalog::selectable('gpt-4o-mini'));
        $this->assertNotContains('claude-opus-5-5', AiModelCatalog::selectableIds('chat'));
        $this->assertSame('openai', AiModelCatalog::itinerary()['provider'], 'planner default Opus needs the key: falls back to OPENAI_MODEL');
        $this->assertSame('config', AiModelCatalog::chat()['source'], 'a saved model whose key is gone is ignored');
        $this->assertFalse(AiModelCatalog::selectable('claude-3-opus'), 'not in the list');
    }

    #[Test]
    public function admin_saves_both_models_and_clears_back_to_default(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AiModels::class)
            ->assertOk()
            ->assertSee('AI chat model')
            ->assertSee('Claude Haiku 5.5')
            ->assertSee('server default')
            ->assertSet('data.ai_chat_model', null)
            ->set('data.ai_chat_model', 'claude-sonnet-5-5')
            ->set('data.ai_itinerary_model', 'claude-haiku-5-5')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('claude-sonnet-5-5', AiModelCatalog::chat()['model']);
        $this->assertSame(['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'source' => 'admin'], AiModelCatalog::itinerary());

        Livewire::test(AiModels::class)
            ->assertSet('data.ai_chat_model', 'claude-sonnet-5-5')
            ->set('data.ai_chat_model', null)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertNull(SiteSetting::string(SiteSetting::AI_CHAT_MODEL));
        $this->assertSame('claude-opus-5-5', AiModelCatalog::chat()['model']);
    }

    #[Test]
    public function admin_cannot_save_an_unknown_model_or_one_without_a_key(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AiModels::class)
            ->set('data.ai_chat_model', 'claude-3-opus-20240229')
            ->call('save')
            ->assertHasErrors(['data.ai_chat_model']);
        $this->assertNull(SiteSetting::string(SiteSetting::AI_CHAT_MODEL));

        Config::set('ai.providers.openai.api_key', null);
        Livewire::test(AiModels::class)
            ->set('data.ai_itinerary_model', 'gpt-4o')
            ->call('save')
            ->assertHasErrors(['data.ai_itinerary_model']);
        $this->assertNull(SiteSetting::string(SiteSetting::AI_ITINERARY_MODEL));
    }

    #[Test]
    public function non_admin_gets_403_and_guest_is_sent_to_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $url = AiModels::getUrl();

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->assertNull(SiteSetting::string(SiteSetting::AI_CHAT_MODEL), 'unchanged');
    }

    #[Test]
    public function the_page_renders_for_an_admin_with_prices_from_model_pricing(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AiModels::class)->assertOk()
            ->assertSeeHtml('$4 / $20')      // Opus 5.5
            ->assertSeeHtml('$0.1 / $0.5')   // Haiku 5.5
            ->assertSeeHtml('Answers per $5 day')
            ->assertSee('in use');
    }

    #[Test]
    public function the_test_button_asks_the_selected_unsaved_model_and_records_the_cost(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->fakeAnthropic([$this->claude('claude-haiku-5-5', 'Try Kašjuni and Bačvice.', ['input_tokens' => 3000, 'output_tokens' => 150])]);

        Livewire::test(AiModels::class)
            ->set('data.ai_chat_model', 'claude-haiku-5-5')
            ->callAction('testChat')
            ->assertNotified();

        $this->assertSame('claude-haiku-5-5', $this->sentModel());
        $this->assertStringContainsString(AiModels::TEST_CHAT_QUESTION, (string) $this->anthropicRequests[0]['request']->getBody());
        $row = AiUsage::where('event_type', 'test')->sole();
        $this->assertSame('free', $row->tier, 'counts toward the daily limits');
        $this->assertSame('claude-haiku-5-5', $row->llm_model);
        $this->assertEqualsWithDelta(ModelPricing::cost('anthropic', 'claude-haiku-5-5', 3000, 150), (float) $row->cost_usd, 1e-6);
        $this->assertNull(SiteSetting::string(SiteSetting::AI_CHAT_MODEL), 'testing does not save');
    }

    #[Test]
    public function the_planner_test_button_returns_json_from_the_selected_model(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        OpenAI::fake([$this->openAiText('gpt-4o', '{"days":[{"title":"Zlatni Rat","summary":"Swim.","tips":[]}]}', 200, 60)]);

        Livewire::test(AiModels::class)
            ->set('data.ai_itinerary_model', 'gpt-4o')
            ->callAction('testItinerary')
            ->assertNotified();

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, fn ($method, $params) => $params['model'] === 'gpt-4o');
        $this->assertSame('gpt-4o', AiUsage::where('event_type', 'test')->sole()->llm_model);
    }

    // ---- estimates ----------------------------------------------------------------------------------------------

    #[Test]
    public function estimates_use_the_assumption_until_enough_answers_are_measured(): void
    {
        $this->assertSame('assumption', AiModelCatalog::typicalTokens('chat')['source']);
        // Calibrated to the 2026-10-09 evaluation: Opus 5.5 ~ $0.036 per chat answer.
        $this->assertEqualsWithDelta(0.0368, AiModelCatalog::estimate('claude-opus-5-5', 'chat'), 0.0005);
        $this->assertLessThan(AiModelCatalog::estimate('claude-sonnet-5-5', 'chat'), AiModelCatalog::estimate('claude-haiku-5-5', 'chat'));

        for ($i = 0; $i < AiModelCatalog::MIN_MEASURED; $i++) {
            AiUsage::create(['tier' => 'free', 'event_type' => 'chat', 'llm_provider' => 'anthropic', 'llm_model' => 'claude-opus-5-5',
                'tokens_input' => 10000, 'tokens_output' => 1000, 'tokens_total' => 11000, 'cost_usd' => 0.06]);
        }
        Cache::flush();

        $typical = AiModelCatalog::typicalTokens('chat');
        $this->assertSame(['in' => 10000, 'out' => 1000, 'cache_read' => 4000, 'measured' => 20, 'source' => 'measured'], $typical);
        $this->assertEqualsWithDelta(0.06, AiModelCatalog::measuredCost('claude-opus-5-5', 'chat')['cost'], 1e-6);
        $this->assertNull(AiModelCatalog::measuredCost('claude-haiku-5-5', 'chat'));
    }

    #[Test]
    public function every_listed_model_has_a_real_price(): void
    {
        foreach (array_keys(AiModelCatalog::MODELS) as $id) {
            $this->assertTrue(ModelPricing::isKnown(AiModelCatalog::MODELS[$id]['provider'], $id), $id);
        }
    }
}

<?php

namespace Tests\Feature\AI;

use App\Filament\BrAdmin\Resources\AiConversationResource\Pages\ListAiConversations;
use App\Filament\BrAdmin\Resources\AiConversationResource\Pages\ViewAiConversation;
use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\AiUsage;
use App\Models\User;
use App\Services\AI\Agent\AgentModel;
use App\Services\AI\Agent\AgentResult;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\Exceptions\LLMTimeoutException;
use App\Services\AI\LLMService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin "AI → Chat conversations" (owner 2026-10-29): what the chat stores for the history, and the read-only admin.
 */
class ChatHistoryAdminTest extends TestCase
{
    use RefreshDatabase;

    /** When set, the fake agent model throws it. */
    public static ?\Throwable $agentThrows = null;

    /** History the fake agent model received on the last call. */
    public static array $agentHistory = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        \App\Models\Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        self::$agentThrows = null;
        self::$agentHistory = [];
        $this->app->instance(AgentModel::class, new class implements AgentModel {
            public function converse(string $system, array $history, string $message, array $tools, callable $runTool, int $maxToolRounds): AgentResult
            {
                ChatHistoryAdminTest::$agentHistory = $history;
                if (ChatHistoryAdminTest::$agentThrows) {
                    throw ChatHistoryAdminTest::$agentThrows;
                }

                return new AgentResult('Try **Bačvice** in Split.', 'openai', 'fake-model', 100, 20, 0.0012,
                    [['name' => 'search_beaches', 'input' => ['city' => 'Split']]]);
            }
        });
    }

    private function adminPanel(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    /** A stored conversation: [role, content, error] turns. */
    private function conversation(array $turns, ?User $user = null, array $attrs = []): AiSession
    {
        $session = AiSession::create($attrs + [
            'user_id' => $user?->id, 'tier' => 'free', 'locale' => 'sl', 'engine' => 'agent',
            'messages_count' => count($turns), 'tokens_used' => 10, 'ip_address' => hash('sha256', '1.2.3.4'),
            'last_activity_at' => now(), 'expires_at' => now()->addMinutes(30),
        ]);
        if (isset($attrs['created_at'])) {
            $session->forceFill(['created_at' => $attrs['created_at']])->saveQuietly();
        }
        foreach ($turns as $i => [$role, $content, $error]) {
            AiMessage::create(['session_id' => $session->id, 'role' => $role, 'content' => $content, 'intent' => 'agent',
                'context' => [], 'error' => $error, 'tokens_total' => 0]);
        }

        return $session;
    }

    // --- what the chat stores ---------------------------------------------------------------------------------

    #[Test]
    public function agent_turn_stores_engine_page_context_request_id_and_both_messages(): void
    {
        config(['ai.chat.engine' => 'agent']);
        $page = ['page_type' => 'city', 'path' => '/sl/kraji/split', 'title' => 'Split'];

        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'Kje se kopati v Splitu?', 'page_context' => $page], ['Accept-Language' => 'sl'])
            ->assertOk();

        $session = AiSession::findOrFail($res->json('session_id'));
        $this->assertSame('agent', $session->engine);
        $this->assertEquals($page, $session->page_context);
        $this->assertNull($session->user_id);

        $messages = $session->messages()->orderBy('id')->get();
        $this->assertSame(['user', 'assistant'], $messages->pluck('role')->all());
        $this->assertSame('Kje se kopati v Splitu?', $messages[0]->content);
        $this->assertSame('Try **Bačvice** in Split.', $messages[1]->content);
        $this->assertEquals($page, $messages[0]->page_context);
        $this->assertSame('search_beaches', $messages[0]->context['tool_calls'][0]['name']);
        $requestId = $res->json('metadata.request_id');
        $this->assertSame([$requestId, $requestId], $messages->pluck('request_id')->all());
        $this->assertSame($requestId, AiUsage::where('session_id', $session->id)->value('request_id'));

        // A later message from another page keeps the first page on the session, stores its own on the message.
        $this->postJson('/api/v1/ai/chat', ['message' => 'In jutri?', 'session_id' => $session->id,
            'page_context' => ['page_type' => 'home', 'path' => '/sl']])->assertOk();
        $this->assertEquals($page, $session->fresh()->page_context);
        $this->assertSame('home', $session->messages()->where('role', 'user')->orderByDesc('id')->first()->page_context['page_type']);
    }

    #[Test]
    public function signed_in_user_session_is_linked_to_the_user(): void
    {
        config(['ai.chat.engine' => 'agent']);
        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/v1/ai/chat', ['message' => 'Hi'])->assertOk();

        $this->assertSame($user->id, AiSession::find($res->json('session_id'))->user_id);
    }

    #[Test]
    public function agent_provider_failure_records_the_question_with_an_error_and_keeps_it_out_of_the_model_history(): void
    {
        config(['ai.chat.engine' => 'agent']);
        self::$agentThrows = new LLMTimeoutException();

        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'Najlepša plaža na Hvaru?'])->assertStatus(503)
            ->assertJsonPath('error', 'ai_unavailable');

        $failed = AiMessage::sole();
        $this->assertSame('user', $failed->role);
        $this->assertSame('Najlepša plaža na Hvaru?', $failed->content);
        $this->assertSame('ai_unavailable', $failed->error);
        $this->assertSame($res->json('request_id'), $failed->request_id);
        $this->assertSame(0, AiSession::sole()->messages_count);

        // The next turn in the same session: the model does not see the unanswered question.
        self::$agentThrows = null;
        $sessionId = $failed->session_id;
        $this->postJson('/api/v1/ai/chat', ['message' => 'Pa na Braču?', 'session_id' => $sessionId])->assertOk();
        $this->assertSame([], self::$agentHistory);
        $this->assertSame(3, AiMessage::where('session_id', $sessionId)->count());
    }

    #[Test]
    public function pipeline_provider_failure_records_the_question_with_an_error(): void
    {
        config(['ai.chat.engine' => 'pipeline']);
        $llm = \Mockery::mock(LLMService::class);
        $llm->shouldReceive('chatCompletion')->andThrow(new LLMTimeoutException());
        $this->app->instance(LLMService::class, $llm);

        $this->postJson('/api/v1/ai/chat', ['message' => 'Beaches in Croatia', 'page_context' => ['page_type' => 'map']])
            ->assertStatus(503);

        $failed = AiMessage::sole();
        $this->assertSame('ai_unavailable', $failed->error);
        $this->assertSame(['page_type' => 'map'], $failed->page_context);
        $this->assertSame('pipeline', AiSession::sole()->engine);
    }

    #[Test]
    public function pipeline_turn_stores_both_messages_with_request_id(): void
    {
        config(['ai.chat.engine' => 'pipeline']);
        $llm = \Mockery::mock(LLMService::class);
        $llm->shouldReceive('chatCompletion')->andReturn(new ChatCompletionResponse(
            content: json_encode(['message' => 'Here are some beaches.', 'beaches' => [], 'suggestions' => []]),
            model: 'fake', inputTokens: 50, outputTokens: 10, totalTokens: 60, costUsd: 0.0001, provider: 'openai'));
        $this->app->instance(LLMService::class, $llm);

        $res = $this->postJson('/api/v1/ai/chat', ['message' => 'Hello'])->assertOk();

        $messages = AiMessage::orderBy('id')->get();
        $this->assertSame(['user', 'assistant'], $messages->pluck('role')->all());
        $this->assertSame('Here are some beaches.', $messages[1]->content);
        $this->assertSame([$res->json('metadata.request_id')], $messages->pluck('request_id')->unique()->values()->all());
        $this->assertNull($messages[0]->error);
    }

    // --- admin ------------------------------------------------------------------------------------------------

    #[Test]
    public function guests_are_redirected_and_non_admins_get_403(): void
    {
        $session = $this->conversation([['user', 'Hi', null]]);

        $this->get(route('filament.admin.resources.ai-conversations.index'))->assertRedirect();
        $this->get(route('filament.admin.resources.ai-conversations.view', $session))->assertRedirect();

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('filament.admin.resources.ai-conversations.index'))->assertForbidden();
        $this->actingAs($user)->get(route('filament.admin.resources.ai-conversations.view', $session))->assertForbidden();
    }

    #[Test]
    public function admin_sees_anonymous_and_signed_in_conversations_newest_first(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $old = $this->conversation([['user', 'Old question', null]], null, ['created_at' => now()->subDays(3)]);
        $mine = $this->conversation([['user', 'Signed in question', null], ['assistant', 'Answer', null]], $user);
        $empty = $this->conversation([]); // a session without messages is not a conversation
        $this->adminPanel();

        Livewire::test(ListAiConversations::class)->assertOk()
            ->assertCanSeeTableRecords([$mine, $old], inOrder: true)
            ->assertCanNotSeeTableRecords([$empty])
            ->assertSee('ana@example.com')
            ->assertSee('Signed in question')
            ->assertSee('anonymous')
            ->filterTable('signed_in', false)
            ->assertCanSeeTableRecords([$old])->assertCanNotSeeTableRecords([$mine]);

    }

    #[Test]
    public function search_finds_assistant_text_and_filters_work(): void
    {
        $a = $this->conversation([['user', 'Kje je pesek?', null], ['assistant', 'Plaža Zlatni rat ima prod.', null]]);
        $b = $this->conversation([['user', 'Vreme jutri?', 'ai_unavailable']], null, ['engine' => 'pipeline', 'locale' => 'de']);
        $this->adminPanel();

        Livewire::test(ListAiConversations::class)
            ->searchTable('zlatni')
            ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b])
            ->searchTable('%') // LIKE wildcards are literal, not "match all"
            ->assertCanNotSeeTableRecords([$a, $b]);

        Livewire::test(ListAiConversations::class)
            ->filterTable('has_error', true)->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
        Livewire::test(ListAiConversations::class)
            ->filterTable('engine', 'pipeline')->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
        Livewire::test(ListAiConversations::class)
            ->filterTable('locale', 'de')->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
        Livewire::test(ListAiConversations::class)
            ->filterTable('started', ['from' => now()->addDay()->toDateString()])->assertCanNotSeeTableRecords([$a, $b]);
    }

    #[Test]
    public function list_query_count_does_not_grow_with_rows(): void
    {
        $this->adminPanel();
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(ListAiConversations::class)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $user = User::factory()->create();
        $this->conversation([['user', 'q', null], ['assistant', 'a', null]], $user);
        $few = $count();
        for ($i = 0; $i < 15; $i++) {
            $this->conversation([['user', "q{$i}", null], ['assistant', "a{$i}", null]], User::factory()->create());
        }
        $this->assertSame($few, $count());
    }

    #[Test]
    public function view_page_shows_the_transcript_metadata_and_can_delete_one_conversation(): void
    {
        $session = $this->conversation([], null, ['page_context' => ['page_type' => 'beach', 'beach_id' => 42, 'path' => '/sl/plaze/x']]);
        AiMessage::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'Je <b>ta</b> plaža za otroke?', 'intent' => 'agent',
            'context' => ['tool_calls' => [['name' => 'get_beach_details', 'input' => ['id' => 42]]]]]);
        AiMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => 'Da, plitvo morje.', 'intent' => 'agent',
            'context' => ['beaches' => [['id' => 42, 'name' => 'Plaža Bacvice']]], 'tokens_total' => 30]);
        AiMessage::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'Pa parkirišče?', 'intent' => 'agent',
            'context' => [], 'error' => 'ai_unavailable']);
        AiUsage::create(['session_id' => $session->id, 'tier' => 'free', 'event_type' => 'chat', 'llm_provider' => 'openai',
            'llm_model' => 'fake', 'cost_usd' => 0.0123]);
        $other = $this->conversation([['user', 'Other', null]]);
        $this->adminPanel();

        // (A full HTTP page render needs the dashboard route, which tests do not register; Livewire renders the page.)
        Livewire::test(ViewAiConversation::class, ['record' => $session->getKey()])->assertOk()
            ->assertSee('Je &lt;b&gt;ta&lt;/b&gt; plaža za otroke?', false) // escaped
            ->assertSee('Da, plitvo morje.')
            ->assertSee('Plaža Bacvice')
            ->assertSee('get_beach_details')
            ->assertSee('beach #42 · /sl/plaze/x')
            ->assertSee('the AI provider was busy')
            ->assertSee('$0.0123')
            ->assertDontSee('1.2.3.4')->assertDontSee(hash('sha256', '1.2.3.4'));

        Livewire::test(ViewAiConversation::class, ['record' => $session->getKey()])->callAction('delete');

        $this->assertNull(AiSession::find($session->id));
        $this->assertSame(0, AiMessage::where('session_id', $session->id)->count());
        $this->assertSame(1, AiUsage::count()); // cost statistics stay
        $this->assertNotNull(AiSession::find($other->id));
    }
}

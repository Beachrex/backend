<?php

namespace App\Filament\BrAdmin\Pages;

use App\Models\AiUsage;
use App\Models\SiteSetting;
use App\Services\AI\AiDailySpend;
use App\Services\AI\AiModelCatalog;
use App\Services\AI\AiSpendMeter;
use App\Services\AI\Agent\ChatAgent;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\LLMService;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owner 2026-10-30: pick the AI chat model and the AI trip planner model, with approximate prices.
 * Guide (Slovenian): docs/guides/admin/ai-modeli.md.
 */
class AiModels extends Page implements HasForms
{
    use InteractsWithForms;

    public const TEST_CHAT_QUESTION = 'Two sandy beaches for families near Split, Croatia?';

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string $view = 'filament.br-admin.pages.ai-models';

    protected static ?string $navigationGroup = 'AI';

    protected static ?string $navigationLabel = 'AI models';

    protected static ?string $title = 'AI models';

    protected static ?string $slug = 'ai-models';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            SiteSetting::AI_CHAT_MODEL => SiteSetting::string(SiteSetting::AI_CHAT_MODEL),
            SiteSetting::AI_ITINERARY_MODEL => SiteSetting::string(SiteSetting::AI_ITINERARY_MODEL),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('AI chat')
                    ->description('The beach chat on the website and in the mobile app. Applies to this environment only.')
                    ->schema([$this->modelSelect(SiteSetting::AI_CHAT_MODEL, AiModelCatalog::USE_CHAT)]),
                Section::make('AI trip planner (paid)')
                    ->description('Writes the text of a bought day-by-day plan (the beaches and the order are chosen by our code). Applies to this environment only.')
                    ->schema([$this->modelSelect(SiteSetting::AI_ITINERARY_MODEL, AiModelCatalog::USE_ITINERARY)]),
            ])
            ->statePath('data');
    }

    private function modelSelect(string $key, string $use): Select
    {
        $default = AiModelCatalog::configDefault($use);
        $options = [];
        foreach (AiModelCatalog::MODELS as $id => $m) {
            [$in, $out] = AiModelCatalog::pricePerMillion($id);
            $options[$id] = sprintf('%s — $%s / $%s per 1M tokens — ~$%s per %s%s%s',
                $m['label'], self::money($in), self::money($out),
                self::money($use === AiModelCatalog::USE_CHAT ? AiModelCatalog::estimate($id, $use) : AiModelCatalog::estimatePlan($id, 7), 4),
                $use === AiModelCatalog::USE_CHAT ? 'answer' : '7-day plan',
                $id === $default['model'] ? ' (server default)' : '',
                ! AiModelCatalog::hasKey($m['provider']) ? ' — no API key' : ($use === AiModelCatalog::USE_CHAT && ! $m['chat'] ? ' — trip planner only' : ''));
        }

        $engineNote = $use === AiModelCatalog::USE_CHAT && config('ai.chat.engine') !== 'agent'
            ? ' This environment runs the simple chat engine (OpenAI only); picking a Claude model switches the chat to the tool-using engine.'
            : '';

        return Select::make($key)
            ->label($use === AiModelCatalog::USE_CHAT ? 'AI chat model' : 'AI trip planner model')
            ->options($options)
            ->placeholder("Server default ({$default['model']})")
            ->disableOptionWhen(fn (string $value) => ! AiModelCatalog::selectable($value, $use))
            ->in(AiModelCatalog::selectableIds($use))
            ->nullable()
            ->native(false)
            ->helperText("Empty = the server default ({$default['model']}). Models without an API key in this environment cannot be picked.".$engineNote);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        foreach ([SiteSetting::AI_CHAT_MODEL => AiModelCatalog::USE_CHAT, SiteSetting::AI_ITINERARY_MODEL => AiModelCatalog::USE_ITINERARY] as $key => $use) {
            $value = $state[$key] ?? null;
            $value = $value === '' ? null : $value;
            // Second line of defence behind the select's own rule (a forged Livewire payload).
            if ($value !== null && ! AiModelCatalog::selectable((string) $value, $use)) {
                throw ValidationException::withMessages(["data.{$key}" => 'This model is not supported or has no API key in this environment.']);
            }
            SiteSetting::set($key, $value);
        }
        Cache::forget('ai_models:typical_tokens:'.AiModelCatalog::USE_CHAT);
        Cache::forget('ai_models:typical_tokens:'.AiModelCatalog::USE_ITINERARY);

        $chat = AiModelCatalog::chat();
        $planner = AiModelCatalog::itinerary();
        Notification::make()
            ->title('Saved')
            ->body("AI chat: {$chat['model']} ({$chat['engine']} engine). AI trip planner: {$planner['model']}. Applies to the next question at once.")
            ->success()
            ->send();
    }

    /** Table rows for the view. */
    public function rows(string $use): array
    {
        $current = $use === AiModelCatalog::USE_CHAT ? AiModelCatalog::chat() : AiModelCatalog::itinerary();
        $default = AiModelCatalog::configDefault($use);
        $cap = (float) config('ai.circuit_breakers.free_tier_daily_limit', 5.0);
        $rows = [];
        foreach (AiModelCatalog::MODELS as $id => $m) {
            [$in, $out] = AiModelCatalog::pricePerMillion($id);
            $estimate = AiModelCatalog::estimate($id, $use);
            $plans = [];
            if ($use === AiModelCatalog::USE_ITINERARY) {
                foreach ([3, 7, 14] as $days) {
                    $cost = AiModelCatalog::estimatePlan($id, $days);
                    $price = (float) config("ai.stripe.products.itinerary_{$days}day.amount", 0);
                    $plans[$days] = ['cost' => '$'.self::money($cost, 3), 'margin' => $price > 0 ? round(100 * $cost / $price, 2) : null];
                }
            }
            $rows[] = [
                'id' => $id,
                'plans' => $plans,
                'chat' => $m['chat'],
                'label' => $m['label'],
                'provider' => $m['provider'] === 'anthropic' ? 'Anthropic' : 'OpenAI',
                'price' => '$'.self::money($in).' / $'.self::money($out),
                'estimate' => '$'.self::money($estimate, 4),
                'per_day' => $use === AiModelCatalog::USE_CHAT && $estimate > 0 ? number_format((int) floor($cap / $estimate), 0, ',', '.') : null,
                'measured' => AiModelCatalog::measuredCost($id, $use),
                'quality' => $m['quality'],
                'key' => AiModelCatalog::hasKey($m['provider']),
                'current' => $current['model'] === $id,
                'default' => $default['model'] === $id,
            ];
        }

        return $rows;
    }

    public function testChatAction(): Action
    {
        return Action::make('testChat')
            ->label('Test the chat model')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Sends one fixed question ("'.self::TEST_CHAT_QUESTION.'") to the model selected above (saved or not) and shows the answer, the time and the cost. It costs about one chat answer and counts toward the daily AI limit.')
            ->action(fn () => $this->runTest(AiModelCatalog::USE_CHAT));
    }

    public function testItineraryAction(): Action
    {
        return Action::make('testItinerary')
            ->label('Test the trip planner model')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Asks the model selected above (saved or not) for a short one-day plan in the trip planner\'s JSON format and shows the result, the time and the cost. It costs a few cents at most and counts toward the daily AI limit.')
            ->action(fn () => $this->runTest(AiModelCatalog::USE_ITINERARY));
    }

    private function runTest(string $use): void
    {
        $key = $use === AiModelCatalog::USE_CHAT ? SiteSetting::AI_CHAT_MODEL : SiteSetting::AI_ITINERARY_MODEL;
        $picked = $this->data[$key] ?? null;
        if ($picked !== null && $picked !== '' && ! AiModelCatalog::selectable((string) $picked, $use)) {
            Notification::make()->title('This model cannot be used here (not supported or no API key).')->danger()->send();

            return;
        }
        $target = ($picked !== null && $picked !== '')
            ? ['provider' => AiModelCatalog::MODELS[$picked]['provider'], 'model' => (string) $picked]
            : AiModelCatalog::configDefault($use);

        if (AiDailySpend::cost(AiDailySpend::BUCKET_ALL) >= (float) config('ai.circuit_breakers.emergency_shutdown_limit', 20.0)) {
            Notification::make()->title('The daily AI limit is reached; try again tomorrow.')->warning()->send();

            return;
        }

        $mark = AiSpendMeter::current()->mark();
        $start = microtime(true);
        $text = null;
        $error = null;
        try {
            if ($use === AiModelCatalog::USE_CHAT) {
                $text = app(ChatAgent::class)->answer(self::TEST_CHAT_QUESTION, [], 'en', null, null,
                    ChatAgent::modelFor($target['provider'], $target['model']))['message'];
            } else {
                $text = app(LLMService::class)->chatCompletion(new ChatCompletionRequest(
                    messages: [
                        ChatMessage::system('You write the text of Beachrex beach trip plans. Use only the beach given.'),
                        ChatMessage::user('Write a one-day plan for Zlatni Rat beach (Bol, Brač, Croatia) as JSON: {"days":[{"title":"...","summary":"...","tips":["..."]}]}. At most 80 words.'),
                    ],
                    model: $target['model'],
                    temperature: $target['provider'] === 'openai' ? 0.6 : null,
                    maxTokens: 600,
                    responseFormat: ['type' => 'json_object'],
                    provider: $target['provider'],
                ))->content;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::warning('Admin AI model test failed', ['model' => $target['model'], 'error' => $error]);
        }
        $ms = (int) round((microtime(true) - $start) * 1000);
        $spent = AiSpendMeter::current()->since($mark);

        if ($spent['cost'] > 0 || $spent['in'] + $spent['out'] > 0) {
            AiUsage::create([
                'user_id' => auth()->id(),
                'tier' => 'free',
                'event_type' => 'test',
                'llm_provider' => $spent['provider'] ?? $target['provider'],
                'llm_model' => Str::limit((string) ($spent['model'] ?? $target['model']), 50, ''),
                'tokens_input' => $spent['in'],
                'tokens_output' => $spent['out'],
                'tokens_total' => $spent['in'] + $spent['out'],
                'cost_usd' => $spent['cost'],
                'latency_ms' => $ms,
            ]);
        }

        $meta = sprintf('%s · %.1f s · $%s', $target['model'], $ms / 1000, self::money($spent['cost'], 4));
        $notification = Notification::make()->persistent();
        if ($error !== null) {
            $notification->title('Test failed: '.$meta)->body(Str::limit($error, 400))->danger()->send();

            return;
        }
        $notification->title('Test answer: '.$meta)->body(Str::limit((string) $text, 1200))->success()->send();
    }

    private static function money(float $value, int $decimals = 2): string
    {
        $s = number_format($value, $decimals, '.', '');

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') ?: '0' : $s;
    }
}

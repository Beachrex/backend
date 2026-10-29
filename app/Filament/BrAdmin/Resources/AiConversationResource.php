<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\AiConversationResource\Pages;
use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\AiMessage;
use App\Models\AiSession;
use App\Models\AiUsage;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin "AI → Chat conversations" (owner 2026-10-29): read-only history of the website AI chat, to see what people ask
 * and what the assistant answers. One row = one chat session (`ai_sessions`), its turns are `ai_messages`.
 * Privacy: shows no IP hash and no user agent; chat text is shown only here.
 */
class AiConversationResource extends Resource
{
    protected static ?string $model = AiSession::class;

    protected static ?string $slug = 'ai-conversations';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'AI';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Chat conversations';

    protected static ?string $modelLabel = 'chat conversation';

    protected static ?string $pluralModelLabel = 'chat conversations';

    public const ENGINES = ['agent' => 'Agent', 'pipeline' => 'Pipeline'];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /** Read-only: no retention/deletion policy is decided yet (owner decision, ADR 2026-10-29). */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** No global search over chat text (it may hold personal data). */
    public static function canGloballySearch(): bool
    {
        return false;
    }

    /**
     * Sessions that have at least one message, with the list columns as subqueries (one query per page, no N+1).
     */
    public static function getEloquentQuery(): Builder
    {
        $of = fn ($q) => $q->whereColumn('ai_messages.session_id', 'ai_sessions.id');

        return parent::getEloquentQuery()
            ->whereExists(fn ($q) => $of($q->from('ai_messages')->selectRaw('1')))
            ->select('ai_sessions.*')
            ->with('user:id,email')
            ->withCount('messages')
            ->addSelect([
                'first_question' => $of(AiMessage::query()->select('content')->where('role', 'user')
                    ->orderBy('created_at')->orderBy('id')->limit(1)),
                'last_message_at' => $of(AiMessage::query()->selectRaw('max(created_at)')),
                'errors_count' => $of(AiMessage::query()->selectRaw('count(*)')->whereNotNull('error')),
                'cost_usd' => AiUsage::query()->selectRaw('sum(cost_usd)')->whereColumn('ai_usage.session_id', 'ai_sessions.id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Started')->dateTime('Y-m-d H:i')->sortable(),
                Tables\Columns\TextColumn::make('last_message_at')->label('Last message')->dateTime('Y-m-d H:i')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('last_message_at', $direction)),
                Tables\Columns\TextColumn::make('user.email')->label('User')->default('anonymous')
                    ->color(fn (AiSession $r) => $r->user_id === null ? 'gray' : null),
                Tables\Columns\TextColumn::make('locale')->label('Lang')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('engine')->label('Engine')->badge()->color('gray')->toggleable(),
                Tables\Columns\TextColumn::make('messages_count')->label('Messages')->numeric()->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_question')->label('First question')->limit(80)->wrap()
                    ->tooltip(fn (AiSession $r) => mb_strlen((string) $r->first_question) > 80 ? $r->first_question : null)
                    ->searchable(query: fn (Builder $query, string $search) => self::searchText($query, $search)),
                Tables\Columns\TextColumn::make('page_context')->label('Opened on')->wrap()
                    ->state(fn (AiSession $r) => self::pageLabel($r->page_context))->placeholder('—'),
                Tables\Columns\TextColumn::make('tokens_used')->label('Tokens')->numeric()->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('cost_usd')->label('Cost')->alignEnd()->toggleable()
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : '$'.number_format((float) $state, 4)),
                Tables\Columns\TextColumn::make('errors_count')->label('Errors')->badge()->alignEnd()
                    ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'gray'),
            ])
            ->filters([
                Tables\Filters\Filter::make('started')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Started from'),
                        Forms\Components\DatePicker::make('until')->label('Started until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('ai_sessions.created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('ai_sessions.created_at', '<=', $d))),
                Tables\Filters\TernaryFilter::make('signed_in')->label('User')
                    ->placeholder('All')->trueLabel('Signed in')->falseLabel('Anonymous')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('ai_sessions.user_id'),
                        false: fn (Builder $q) => $q->whereNull('ai_sessions.user_id'),
                    ),
                Tables\Filters\SelectFilter::make('locale')->label('Language')
                    ->options(array_combine(SetLocaleFromHeader::SUPPORTED_LOCALES, SetLocaleFromHeader::SUPPORTED_LOCALES)),
                Tables\Filters\SelectFilter::make('engine')->options(self::ENGINES),
                Tables\Filters\Filter::make('has_error')->label('Has an error')->toggle()
                    ->query(fn (Builder $query) => $query->whereExists(fn ($q) => $q->from('ai_messages')->selectRaw('1')
                        ->whereColumn('ai_messages.session_id', 'ai_sessions.id')->whereNotNull('ai_messages.error'))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->paginated([25, 50, 100]);
    }

    /** Search the text of all messages (user and assistant) and the user's e-mail. */
    public static function searchText(Builder $query, string $search): Builder
    {
        $like = '%'.addcslashes($search, '\\%_').'%';

        return $query->where(fn (Builder $q) => $q
            ->whereExists(fn ($m) => $m->from('ai_messages')->selectRaw('1')
                ->whereColumn('ai_messages.session_id', 'ai_sessions.id')
                ->where('ai_messages.content', 'ilike', $like))
            ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', $like)));
    }

    /** "beach #123 · /si/plaze/…" from the stored (validated) page_context. */
    public static function pageLabel(?array $page): ?string
    {
        if (! $page || empty($page['page_type'])) {
            return null;
        }
        $label = (string) $page['page_type'];
        if (! empty($page['beach_id'])) {
            $label .= ' #'.(int) $page['beach_id'];
        }
        if (! empty($page['path'])) {
            $label .= ' · '.$page['path'];
        }

        return $label;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Conversation')->columns(4)->schema([
                Infolists\Components\TextEntry::make('created_at')->label('Started')->dateTime('Y-m-d H:i:s'),
                Infolists\Components\TextEntry::make('last_message_at')->label('Last message')->dateTime('Y-m-d H:i:s'),
                Infolists\Components\TextEntry::make('user.email')->label('User')->default('anonymous'),
                Infolists\Components\TextEntry::make('locale')->label('Language'),
                Infolists\Components\TextEntry::make('engine')->placeholder('—'),
                Infolists\Components\TextEntry::make('tier'),
                Infolists\Components\TextEntry::make('messages_count')->label('Messages'),
                Infolists\Components\TextEntry::make('errors_count')->label('Failed turns'),
                Infolists\Components\TextEntry::make('tokens_used')->label('Tokens'),
                Infolists\Components\TextEntry::make('cost_usd')->label('Cost (USD)')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : '$'.number_format((float) $state, 4))->placeholder('—'),
                Infolists\Components\TextEntry::make('models')->label('Model')->placeholder('—')
                    ->state(fn (AiSession $r) => AiUsage::query()->where('session_id', $r->id)
                        ->distinct()->orderBy('llm_model')->pluck('llm_model')->implode(', ') ?: null),
                Infolists\Components\TextEntry::make('page_context')->label('Opened on')->columnSpan(2)
                    ->state(fn (AiSession $r) => self::pageLabel($r->page_context))->placeholder('—'),
                Infolists\Components\TextEntry::make('id')->label('Session id')->columnSpanFull()->copyable(),
            ]),
            Infolists\Components\Section::make('Transcript')->schema([
                Infolists\Components\ViewEntry::make('transcript')->hiddenLabel()
                    ->view('filament.ai-conversations.transcript')
                    ->state(fn (AiSession $r) => [
                        'messages' => $r->messages()->orderBy('created_at')->orderBy('id')->get(),
                        // Model and cost of each answered turn (ai_usage row with the same request_id), one query.
                        'usage' => AiUsage::query()->where('session_id', $r->id)->whereNotNull('request_id')
                            ->get(['request_id', 'llm_model', 'cost_usd'])->keyBy('request_id'),
                    ]),
            ]),
        ]);
    }

    public static function getWidgets(): array
    {
        return [AiConversationResource\Widgets\AiConversationStats::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiConversations::route('/'),
            'view' => Pages\ViewAiConversation::route('/{record}'),
        ];
    }
}

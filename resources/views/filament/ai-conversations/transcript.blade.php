{{-- Admin "AI → Chat conversations": one conversation as a transcript. All text is escaped ({{ }}). --}}
@php
    $errorLabels = [
        'ai_unavailable' => 'No answer: the AI provider was busy or timed out',
        'internal_error' => 'No answer: server error',
        'payment_required' => 'No answer: paid feature (multi-day trip plan)',
    ];
@endphp
<div class="space-y-3">
    @forelse ($getState() ?? [] as $message)
        @php
            $isUser = $message->role === 'user';
            $ctx = is_array($message->context) ? $message->context : [];
            $beaches = array_values(array_filter($ctx['beaches'] ?? [], fn ($b) => is_array($b) && isset($b['name'])));
            $tools = array_values(array_filter($ctx['tool_calls'] ?? [], fn ($c) => is_array($c) && isset($c['name'])));
            $page = \App\Filament\BrAdmin\Resources\AiConversationResource::pageLabel($message->page_context);
        @endphp
        <div @class([
            'rounded-lg p-3 border',
            'bg-gray-50 border-gray-200 dark:bg-white/5 dark:border-white/10' => $isUser,
            'bg-primary-50 border-primary-200 dark:bg-primary-500/10 dark:border-primary-500/20' => ! $isUser,
        ]) style="{{ $isUser ? '' : 'margin-left: 2rem;' }}{{ $message->error ? ' border-color: rgb(248 113 113);' : '' }}">
            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-1">
                <span class="font-semibold uppercase text-gray-700 dark:text-gray-200">{{ $isUser ? 'User' : ($message->role === 'assistant' ? 'Assistant' : $message->role) }}</span>
                <span>{{ $message->created_at?->format('Y-m-d H:i:s') }}</span>
                @if ($message->intent && $message->intent !== 'agent')<span>intent: {{ $message->intent }}</span>@endif
                @if ($message->tokens_total)<span>{{ $message->tokens_total }} tokens</span>@endif
                @if ($page)<span>page: {{ $page }}</span>@endif
                @if (! empty($ctx['refused']))<span class="text-warning-600">refused (off-topic)</span>@endif
            </div>
            <div class="whitespace-pre-wrap break-words text-sm text-gray-950 dark:text-white">{{ $message->content }}</div>
            @if ($message->error)
                <div class="mt-2 text-xs font-semibold text-danger-600">{{ $errorLabels[$message->error] ?? 'No answer: '.$message->error }}</div>
            @endif
            @if ($tools)
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Tools used:
                    @foreach ($tools as $call)
                        <code>{{ $call['name'] }}({{ \Illuminate\Support\Str::limit(json_encode($call['input'] ?? [], JSON_UNESCAPED_UNICODE), 120) }})</code>@if (! $loop->last), @endif
                    @endforeach
                </div>
            @endif
            @if (! $isUser && $beaches)
                <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                    Beaches shown ({{ count($beaches) }}):
                    @foreach ($beaches as $b)
                        <span>{{ $b['name'] }}@isset($b['id']) <span class="text-gray-400">#{{ $b['id'] }}</span>@endisset</span>@if (! $loop->last) · @endif
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">No messages.</p>
    @endforelse
</div>

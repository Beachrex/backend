<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                Save
            </x-filament::button>
            {{ $this->testChatAction }}
            {{ $this->testItineraryAction }}
        </div>
    </form>

    @foreach ([\App\Services\AI\AiModelCatalog::USE_CHAT => 'AI chat — price per answer', \App\Services\AI\AiModelCatalog::USE_ITINERARY => 'AI trip planner — price per plan'] as $use => $heading)
        @php($tokens = \App\Services\AI\AiModelCatalog::typicalTokens($use))
        <x-filament::section :heading="$heading">
            <x-slot name="description">
                @if ($tokens['source'] === 'measured')
                    Estimate from the average of {{ $tokens['measured'] }} answers in the last 30 days on this environment
                    ({{ number_format($tokens['in']) }} input + {{ number_format($tokens['out']) }} output tokens per answer).
                @else
                    Estimate from a typical {{ $use === 'chat' ? 'chat answer with 2–3 beach searches' : '7-day plan (3- and 14-day plans scaled by length)' }}
                    ({{ number_format($tokens['in']) }} input{{ $tokens['cache_read'] ? ', '.number_format($tokens['cache_read']).' cached' : '' }}
                    + {{ number_format($tokens['out']) }} output tokens); too few answers on this environment to measure ({{ $tokens['measured'] }}).
                @endif
                Prices in USD per 1M tokens (input / output). Real costs vary with the question.@if ($use !== 'chat') The percentage is the AI cost as a share of what the buyer pays for that plan (Stripe price, before fees and VAT).@endif
            </x-slot>
            <div style="overflow-x:auto">
                <table style="width:100%; border-collapse:collapse; font-size:0.875rem">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid rgba(127,127,127,.3)">
                            <th style="padding:6px 8px">Model</th>
                            <th style="padding:6px 8px">Price (in / out)</th>
                            @if ($use === 'chat')
                                <th style="padding:6px 8px">Est. per answer</th>
                            @else
                                @foreach ([3, 7, 14] as $days)
                                    <th style="padding:6px 8px">{{ $days }}-day plan (${{ rtrim(rtrim(number_format((float) config("ai.stripe.products.itinerary_{$days}day.amount"), 2, '.', ''), '0'), '.') }})</th>
                                @endforeach
                            @endif
                            @if ($use === 'chat')
                                <th style="padding:6px 8px">Answers per ${{ rtrim(rtrim(number_format((float) config('ai.circuit_breakers.free_tier_daily_limit'), 2, '.', ''), '0'), '.') }} day</th>
                            @endif
                            <th style="padding:6px 8px">Measured (30 days)</th>
                            <th style="padding:6px 8px">When to use</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows($use) as $row)
                            <tr style="border-bottom:1px solid rgba(127,127,127,.15); {{ $row['key'] && ($use !== 'chat' || $row['chat']) ? '' : 'opacity:.55' }}">
                                <td style="padding:6px 8px; white-space:nowrap">
                                    <strong>{{ $row['label'] }}</strong>
                                    <div style="font-size:.75rem; opacity:.75">{{ $row['provider'] }} · <code>{{ $row['id'] }}</code></div>
                                    @if ($row['current'])
                                        <x-filament::badge color="success">in use</x-filament::badge>
                                    @endif
                                    @if ($row['default'])
                                        <x-filament::badge color="gray">server default</x-filament::badge>
                                    @endif
                                    @unless ($row['key'])
                                        <x-filament::badge color="danger">no API key</x-filament::badge>
                                    @endunless
                                    @if ($use === 'chat' && ! $row['chat'])
                                        <x-filament::badge color="gray">trip planner only</x-filament::badge>
                                    @endif
                                </td>
                                <td style="padding:6px 8px; white-space:nowrap">{{ $row['price'] }}</td>
                                @if ($use === 'chat')
                                    <td style="padding:6px 8px; white-space:nowrap">~{{ $row['estimate'] }}</td>
                                @else
                                    @foreach ($row['plans'] as $plan)
                                        <td style="padding:6px 8px; white-space:nowrap">~{{ $plan['cost'] }}@if ($plan['margin'] !== null) <span style="opacity:.7">({{ $plan['margin'] }}% of price)</span>@endif</td>
                                    @endforeach
                                @endif
                                @if ($use === 'chat')
                                    <td style="padding:6px 8px">~{{ $row['per_day'] }}</td>
                                @endif
                                <td style="padding:6px 8px; white-space:nowrap">
                                    @if ($row['measured'])
                                        ${{ number_format($row['measured']['cost'], 4) }} ({{ $row['measured']['n'] }}×)
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="padding:6px 8px">{{ $row['quality'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endforeach

</x-filament-panels::page>

<?php

namespace App\Filament\BrAdmin\Resources\AiConversationResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** Conversations per day (last 14 days) and a few totals; one aggregate query. Shown only above the list. */
class AiConversationStats extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $from = now()->startOfDay()->subDays(13);
        $perDay = DB::table('ai_sessions as s')
            ->whereExists(fn ($q) => $q->from('ai_messages as m')->selectRaw('1')->whereColumn('m.session_id', 's.id'))
            ->where('s.created_at', '>=', $from)
            ->selectRaw('date(s.created_at) as day, count(*) as n, count(s.user_id) as signed_in')
            ->groupByRaw('date(s.created_at)')
            ->get()->keyBy('day');

        $days = collect(range(13, 0))->map(fn ($i) => (int) ($perDay[now()->subDays($i)->toDateString()]->n ?? 0));
        $total = $days->sum();
        $signedIn = (int) $perDay->sum('signed_in');
        $failed = DB::table('ai_messages')->whereNotNull('error')->where('created_at', '>=', $from)->count();

        return [
            Stat::make('Conversations today', $days->last())->chart($days->all())
                ->description('last 14 days: '.$total),
            Stat::make('Signed in (14 days)', $signedIn)
                ->description(($total ? round(100 * $signedIn / $total) : 0).'% of conversations'),
            Stat::make('Failed turns (14 days)', $failed)->color($failed > 0 ? 'danger' : 'gray')
                ->description('questions without an answer'),
        ];
    }
}

<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Models\Beach;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class BeachTrendsWidget extends ChartWidget
{
    protected static ?string $heading = 'Beach Creation Trends';
    protected static ?string $description = 'Number of beaches added per month (last 12 months)';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $data = Beach::query()
            ->select(
                DB::raw('DATE_TRUNC(\'month\', created_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = [];
        $values = [];

        foreach ($data as $item) {
            $labels[] = \Carbon\Carbon::parse($item->month)->format('M Y');
            $values[] = $item->count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Beaches Added',
                    'data' => $values,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}


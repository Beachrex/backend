<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Models\Beach;
use Filament\Widgets\ChartWidget;

class CountryDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Top 10 Countries by Beach Count';
    protected static ?string $description = 'Distribution of beaches across countries';
    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $data = Beach::query()
            ->with('country.translations')
            ->select('country_code')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $labels = [];
        $values = [];
        $colors = [
            'rgba(59, 130, 246, 0.8)',   // Blue
            'rgba(16, 185, 129, 0.8)',   // Green
            'rgba(245, 158, 11, 0.8)',   // Amber
            'rgba(239, 68, 68, 0.8)',    // Red
            'rgba(139, 92, 246, 0.8)',   // Purple
            'rgba(236, 72, 153, 0.8)',   // Pink
            'rgba(20, 184, 166, 0.8)',   // Teal
            'rgba(251, 146, 60, 0.8)',   // Orange
            'rgba(99, 102, 241, 0.8)',   // Indigo
            'rgba(168, 85, 247, 0.8)',   // Violet
        ];

        foreach ($data as $item) {
            $countryName = $item->country?->translations()->where('locale', 'en')->first()?->name 
                ?? $item->country_code 
                ?? 'Unknown';
            $labels[] = $countryName;
            $values[] = $item->count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Beaches',
                    'data' => $values,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}


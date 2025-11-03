<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Models\Beach;
use Filament\Widgets\ChartWidget;

class DataQualityWidget extends ChartWidget
{
    protected static ?string $heading = 'Data Quality Overview';
    protected static ?string $description = 'Percentage of beaches with complete data';
    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $total = Beach::count();

        $withGPS = Beach::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        $withMedia = Beach::has('media')->count();

        $withTranslations = Beach::has('translations', '=', 14)->count();

        $withType = Beach::whereNotNull('beach_type_id')->count();

        $withAccessibility = Beach::whereNotNull('accessibility_id')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Completeness %',
                    'data' => [
                        $total > 0 ? round(($withGPS / $total) * 100, 1) : 0,
                        $total > 0 ? round(($withMedia / $total) * 100, 1) : 0,
                        $total > 0 ? round(($withTranslations / $total) * 100, 1) : 0,
                        $total > 0 ? round(($withType / $total) * 100, 1) : 0,
                        $total > 0 ? round(($withAccessibility / $total) * 100, 1) : 0,
                    ],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',   // GPS - Blue
                        'rgba(16, 185, 129, 0.8)',   // Media - Green
                        'rgba(245, 158, 11, 0.8)',   // Translations - Amber
                        'rgba(139, 92, 246, 0.8)',   // Type - Purple
                        'rgba(236, 72, 153, 0.8)',   // Accessibility - Pink
                    ],
                ],
            ],
            'labels' => [
                'GPS Coordinates',
                'Media Files',
                'All Translations',
                'Beach Type',
                'Accessibility',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => [
                        'callback' => 'function(value) { return value + "%"; }',
                    ],
                ],
            ],
        ];
    }
}


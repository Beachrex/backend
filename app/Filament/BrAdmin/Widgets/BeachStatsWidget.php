<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Models\Beach;
use App\Models\Media;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BeachStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalBeaches = Beach::count();
        $activeBeaches = Beach::where('status_id', 1)->count();
        $beachesWithLocation = Beach::whereNotNull('location')->count();
        $beachesWithMedia = Media::where('mediable_type', 'App\Models\Beach')
            ->where('status_id', 1)
            ->distinct('mediable_id')
            ->count('mediable_id');

        return [
            Stat::make('Total Beaches', number_format($totalBeaches))
                ->description('All beaches in database')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary'),
            
            Stat::make('Active Beaches', number_format($activeBeaches))
                ->description(round(($activeBeaches / max($totalBeaches, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            
            Stat::make('With Location', number_format($beachesWithLocation))
                ->description(round(($beachesWithLocation / max($totalBeaches, 1)) * 100, 1) . '% have GPS coordinates')
                ->descriptionIcon('heroicon-m-map')
                ->color('info'),
            
            Stat::make('With Media', number_format($beachesWithMedia))
                ->description(round(($beachesWithMedia / max($totalBeaches, 1)) * 100, 1) . '% have photos/videos')
                ->descriptionIcon('heroicon-m-photo')
                ->color('warning'),
        ];
    }
}


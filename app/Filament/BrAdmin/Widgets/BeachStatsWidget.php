<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Filament\BrAdmin\Resources\BeachResource;
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
        $inactiveBeaches = $totalBeaches - $activeBeaches;
        $beachesWithLocation = Beach::whereNotNull('location')->count();
        $beachesWithoutLocation = $totalBeaches - $beachesWithLocation;
        $beachesWithMedia = Media::where('mediable_type', 'App\Models\Beach')
            ->where('status_id', 1)
            ->distinct('mediable_id')
            ->count('mediable_id');
        $beachesWithoutMedia = $totalBeaches - $beachesWithMedia;

        return [
            Stat::make('Total Beaches', number_format($totalBeaches))
                ->description('Click to view all beaches')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('primary')
                ->url(BeachResource::getUrl('index')),

            Stat::make('Active Beaches', number_format($activeBeaches))
                ->description(number_format($inactiveBeaches) . ' inactive - Click to view inactive')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url(BeachResource::getUrl('index', ['tableFilters' => ['status' => ['values' => [2, 4, 5, 6, 11]]]])),

            Stat::make('GPS Location', number_format($beachesWithoutLocation) . ' / ' . number_format($totalBeaches))
                ->description(number_format($beachesWithoutLocation) . ' missing GPS location (' . round(($beachesWithLocation / max($totalBeaches, 1)) * 100, 1) . '% complete) - Click to view missing')
                ->descriptionIcon('heroicon-m-map')
                ->color($beachesWithoutLocation > 0 ? 'danger' : 'success')
                ->url(BeachResource::getUrl('index', ['tableFilters' => ['has_location' => ['value' => 0]]])),

            Stat::make('Media Files', number_format($beachesWithoutMedia) . ' / ' . number_format($totalBeaches))
                ->description(number_format($beachesWithoutMedia) . ' missing media files (' . round(($beachesWithMedia / max($totalBeaches, 1)) * 100, 1) . '% complete) - Click to view missing')
                ->descriptionIcon('heroicon-m-photo')
                ->color($beachesWithoutMedia > 0 ? 'warning' : 'success')
                ->url(BeachResource::getUrl('index', ['tableFilters' => ['has_media' => ['value' => 0]]])),
        ];
    }
}


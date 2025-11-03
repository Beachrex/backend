<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Models\Article;
use App\Models\Badge;
use App\Models\Media;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $totalArticles = Article::count();
        $activeArticles = Article::where('status_id', 1)->count();
        $totalBadges = Badge::count();
        $totalMedia = Media::count();
        $totalUsers = User::count();

        return [
            Stat::make('Articles', number_format($totalArticles))
                ->description(number_format($activeArticles) . ' active')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
            
            Stat::make('Badges', number_format($totalBadges))
                ->description('Regional & location badges')
                ->descriptionIcon('heroicon-m-star')
                ->color('success'),
            
            Stat::make('Media Files', number_format($totalMedia))
                ->description('Photos, videos & documents')
                ->descriptionIcon('heroicon-m-photo')
                ->color('warning'),
            
            Stat::make('Users', number_format($totalUsers))
                ->description('Admin & content managers')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}


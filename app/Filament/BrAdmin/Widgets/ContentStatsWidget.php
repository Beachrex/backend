<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Filament\BrAdmin\Resources\ArticleResource;
use App\Filament\BrAdmin\Resources\BadgeResource;
use App\Filament\BrAdmin\Resources\MediaResource;
use App\Filament\BrAdmin\Resources\UserResource;
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
        $activeMedia = Media::where('status_id', 1)->count();
        $totalUsers = User::count();

        return [
            Stat::make('Articles', number_format($totalArticles))
                ->description(number_format($activeArticles) . ' active - Click to view all')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->url(ArticleResource::getUrl('index')),

            Stat::make('Badges', number_format($totalBadges))
                ->description('Click to manage regional & location badges')
                ->descriptionIcon('heroicon-m-star')
                ->color('success')
                ->url(BadgeResource::getUrl('index')),

            Stat::make('Media Files', number_format($totalMedia))
                ->description(number_format($activeMedia) . ' active - Click to view all')
                ->descriptionIcon('heroicon-m-photo')
                ->color('warning')
                ->url(MediaResource::getUrl('index')),

            Stat::make('Users', number_format($totalUsers))
                ->description('Click to manage users')
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->url(UserResource::getUrl('index')),
        ];
    }
}


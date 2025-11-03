<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Models\Continent;
use App\Models\Country;
use App\Models\Region;
use App\Models\City;
use App\Models\Island;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GeographyStatsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $continents = Continent::count();
        $countries = Country::count();
        $regions = Region::count();
        $cities = City::count();
        $islands = Island::count();

        return [
            Stat::make('Continents', number_format($continents))
                ->description('Geographic coverage')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary'),
            
            Stat::make('Countries', number_format($countries))
                ->description('With beach locations')
                ->descriptionIcon('heroicon-m-flag')
                ->color('success'),
            
            Stat::make('Regions', number_format($regions))
                ->description('Administrative areas')
                ->descriptionIcon('heroicon-m-map')
                ->color('info'),
            
            Stat::make('Cities & Islands', number_format($cities + $islands))
                ->description(number_format($cities) . ' cities, ' . number_format($islands) . ' islands')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('warning'),
        ];
    }
}


<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Filament\BrAdmin\Resources\ContinentResource;
use App\Filament\BrAdmin\Resources\CountryResource;
use App\Filament\BrAdmin\Resources\RegionResource;
use App\Filament\BrAdmin\Resources\CityResource;
use App\Filament\BrAdmin\Resources\IslandResource;
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
                ->description('Click to view geographic coverage')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary')
                ->url(ContinentResource::getUrl('index')),

            Stat::make('Countries', number_format($countries))
                ->description('Click to view all countries')
                ->descriptionIcon('heroicon-m-flag')
                ->color('success')
                ->url(CountryResource::getUrl('index')),

            Stat::make('Regions', number_format($regions))
                ->description('Click to view administrative areas')
                ->descriptionIcon('heroicon-m-map')
                ->color('info')
                ->url(RegionResource::getUrl('index')),

            Stat::make('Cities & Islands', number_format($cities + $islands))
                ->description(number_format($cities) . ' cities, ' . number_format($islands) . ' islands - Click for cities')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('warning')
                ->url(CityResource::getUrl('index')),
        ];
    }
}


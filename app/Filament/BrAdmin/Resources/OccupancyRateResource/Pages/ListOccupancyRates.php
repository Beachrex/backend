<?php

namespace App\Filament\BrAdmin\Resources\OccupancyRateResource\Pages;

use App\Filament\BrAdmin\Resources\OccupancyRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOccupancyRates extends ListRecords
{
    protected static string $resource = OccupancyRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

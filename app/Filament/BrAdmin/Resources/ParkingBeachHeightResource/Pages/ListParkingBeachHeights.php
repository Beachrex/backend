<?php

namespace App\Filament\BrAdmin\Resources\ParkingBeachHeightResource\Pages;

use App\Filament\BrAdmin\Resources\ParkingBeachHeightResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParkingBeachHeights extends ListRecords
{
    protected static string $resource = ParkingBeachHeightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\BrAdmin\Resources\RoadToParkingTypeResource\Pages;

use App\Filament\BrAdmin\Resources\RoadToParkingTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoadToParkingTypes extends ListRecords
{
    protected static string $resource = RoadToParkingTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\BrAdmin\Resources\ParkingCapacityResource\Pages;

use App\Filament\BrAdmin\Resources\ParkingCapacityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParkingCapacities extends ListRecords
{
    protected static string $resource = ParkingCapacityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

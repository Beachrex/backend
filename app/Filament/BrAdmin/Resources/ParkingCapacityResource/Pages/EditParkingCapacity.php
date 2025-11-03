<?php

namespace App\Filament\BrAdmin\Resources\ParkingCapacityResource\Pages;

use App\Filament\BrAdmin\Resources\ParkingCapacityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParkingCapacity extends EditRecord
{
    protected static string $resource = ParkingCapacityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

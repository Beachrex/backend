<?php

namespace App\Filament\BrAdmin\Resources\ParkingBeachDistanceResource\Pages;

use App\Filament\BrAdmin\Resources\ParkingBeachDistanceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParkingBeachDistance extends EditRecord
{
    protected static string $resource = ParkingBeachDistanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\BrAdmin\Resources\ParkingBeachHeightResource\Pages;

use App\Filament\BrAdmin\Resources\ParkingBeachHeightResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParkingBeachHeight extends EditRecord
{
    protected static string $resource = ParkingBeachHeightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

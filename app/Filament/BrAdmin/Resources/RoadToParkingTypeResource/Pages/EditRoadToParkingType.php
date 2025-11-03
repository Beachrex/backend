<?php

namespace App\Filament\BrAdmin\Resources\RoadToParkingTypeResource\Pages;

use App\Filament\BrAdmin\Resources\RoadToParkingTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoadToParkingType extends EditRecord
{
    protected static string $resource = RoadToParkingTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

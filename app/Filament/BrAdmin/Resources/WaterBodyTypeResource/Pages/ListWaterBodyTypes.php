<?php

namespace App\Filament\BrAdmin\Resources\WaterBodyTypeResource\Pages;

use App\Filament\BrAdmin\Resources\WaterBodyTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaterBodyTypes extends ListRecords
{
    protected static string $resource = WaterBodyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

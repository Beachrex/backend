<?php

namespace App\Filament\BrAdmin\Resources\UnderwaterCharacteristicResource\Pages;

use App\Filament\BrAdmin\Resources\UnderwaterCharacteristicResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUnderwaterCharacteristics extends ListRecords
{
    protected static string $resource = UnderwaterCharacteristicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

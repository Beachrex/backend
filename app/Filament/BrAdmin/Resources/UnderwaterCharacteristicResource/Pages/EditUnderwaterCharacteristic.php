<?php

namespace App\Filament\BrAdmin\Resources\UnderwaterCharacteristicResource\Pages;

use App\Filament\BrAdmin\Resources\UnderwaterCharacteristicResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUnderwaterCharacteristic extends EditRecord
{
    protected static string $resource = UnderwaterCharacteristicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

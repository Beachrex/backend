<?php

namespace App\Filament\BrAdmin\Resources\WaterBodyTypeResource\Pages;

use App\Filament\BrAdmin\Resources\WaterBodyTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWaterBodyType extends EditRecord
{
    protected static string $resource = WaterBodyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

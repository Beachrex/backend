<?php

namespace App\Filament\BrAdmin\Resources\BeachTypeResource\Pages;

use App\Filament\BrAdmin\Resources\BeachTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBeachType extends EditRecord
{
    protected static string $resource = BeachTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\BrAdmin\Resources\BeachResource\Pages;

use App\Filament\BrAdmin\Resources\BeachResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBeach extends EditRecord
{
    protected static string $resource = BeachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

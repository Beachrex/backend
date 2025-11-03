<?php

namespace App\Filament\BrAdmin\Resources\LocationLengthResource\Pages;

use App\Filament\BrAdmin\Resources\LocationLengthResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocationLength extends EditRecord
{
    protected static string $resource = LocationLengthResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

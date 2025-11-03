<?php

namespace App\Filament\BrAdmin\Resources\RiverResource\Pages;

use App\Filament\BrAdmin\Resources\RiverResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRiver extends EditRecord
{
    protected static string $resource = RiverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}


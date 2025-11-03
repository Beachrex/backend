<?php

namespace App\Filament\BrAdmin\Resources\MediaCollectionResource\Pages;

use App\Filament\BrAdmin\Resources\MediaCollectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMediaCollection extends EditRecord
{
    protected static string $resource = MediaCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

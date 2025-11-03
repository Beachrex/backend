<?php

namespace App\Filament\BrAdmin\Resources\AreaDescriptionResource\Pages;

use App\Filament\BrAdmin\Resources\AreaDescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAreaDescription extends EditRecord
{
    protected static string $resource = AreaDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

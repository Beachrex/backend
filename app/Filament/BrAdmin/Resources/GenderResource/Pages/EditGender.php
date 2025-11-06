<?php

namespace App\Filament\BrAdmin\Resources\GenderResource\Pages;

use App\Filament\BrAdmin\Resources\GenderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGender extends EditRecord
{
    protected static string $resource = GenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

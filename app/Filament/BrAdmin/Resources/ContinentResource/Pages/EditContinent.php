<?php

namespace App\Filament\BrAdmin\Resources\ContinentResource\Pages;

use App\Filament\BrAdmin\Resources\ContinentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContinent extends EditRecord
{
    protected static string $resource = ContinentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

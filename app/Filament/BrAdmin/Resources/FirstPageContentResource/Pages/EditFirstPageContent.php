<?php

namespace App\Filament\BrAdmin\Resources\FirstPageContentResource\Pages;

use App\Filament\BrAdmin\Resources\FirstPageContentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFirstPageContent extends EditRecord
{
    protected static string $resource = FirstPageContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}


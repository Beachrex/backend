<?php

namespace App\Filament\BrAdmin\Resources\FirstPageContentResource\Pages;

use App\Filament\BrAdmin\Resources\FirstPageContentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFirstPageContent extends CreateRecord
{
    protected static string $resource = FirstPageContentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}


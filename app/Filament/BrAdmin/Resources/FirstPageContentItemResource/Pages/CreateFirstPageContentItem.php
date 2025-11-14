<?php

namespace App\Filament\BrAdmin\Resources\FirstPageContentItemResource\Pages;

use App\Filament\BrAdmin\Resources\FirstPageContentItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFirstPageContentItem extends CreateRecord
{
    protected static string $resource = FirstPageContentItemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}


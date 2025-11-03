<?php

namespace App\Filament\BrAdmin\Resources\MediaCollectionResource\Pages;

use App\Filament\BrAdmin\Resources\MediaCollectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaCollections extends ListRecords
{
    protected static string $resource = MediaCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

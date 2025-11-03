<?php

namespace App\Filament\BrAdmin\Resources\MediaTypeResource\Pages;

use App\Filament\BrAdmin\Resources\MediaTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMediaTypes extends ListRecords
{
    protected static string $resource = MediaTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

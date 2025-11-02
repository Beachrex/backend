<?php

namespace App\Filament\BrAdmin\Resources\WaterbodyResource\Pages;

use App\Filament\BrAdmin\Resources\WaterbodyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWaterbodies extends ListRecords
{
    protected static string $resource = WaterbodyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

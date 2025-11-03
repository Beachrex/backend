<?php

namespace App\Filament\BrAdmin\Resources\LocationLengthResource\Pages;

use App\Filament\BrAdmin\Resources\LocationLengthResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocationLengths extends ListRecords
{
    protected static string $resource = LocationLengthResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

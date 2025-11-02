<?php

namespace App\Filament\BrAdmin\Resources\BeachTypeResource\Pages;

use App\Filament\BrAdmin\Resources\BeachTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBeachTypes extends ListRecords
{
    protected static string $resource = BeachTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

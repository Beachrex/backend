<?php

namespace App\Filament\BrAdmin\Resources\BeachLocationTypeResource\Pages;

use App\Filament\BrAdmin\Resources\BeachLocationTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBeachLocationTypes extends ListRecords
{
    protected static string $resource = BeachLocationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

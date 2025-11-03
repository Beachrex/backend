<?php

namespace App\Filament\BrAdmin\Resources\BeachInclinationResource\Pages;

use App\Filament\BrAdmin\Resources\BeachInclinationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBeachInclinations extends ListRecords
{
    protected static string $resource = BeachInclinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

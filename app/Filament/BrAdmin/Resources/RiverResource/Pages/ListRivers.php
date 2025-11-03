<?php

namespace App\Filament\BrAdmin\Resources\RiverResource\Pages;

use App\Filament\BrAdmin\Resources\RiverResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRivers extends ListRecords
{
    protected static string $resource = RiverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


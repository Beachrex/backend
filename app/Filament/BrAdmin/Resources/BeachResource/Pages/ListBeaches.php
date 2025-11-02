<?php

namespace App\Filament\BrAdmin\Resources\BeachResource\Pages;

use App\Filament\BrAdmin\Resources\BeachResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBeaches extends ListRecords
{
    protected static string $resource = BeachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

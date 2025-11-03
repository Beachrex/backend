<?php

namespace App\Filament\BrAdmin\Resources\BayLengthResource\Pages;

use App\Filament\BrAdmin\Resources\BayLengthResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBayLengths extends ListRecords
{
    protected static string $resource = BayLengthResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

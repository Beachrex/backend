<?php

namespace App\Filament\BrAdmin\Resources\AccommodationAroundResource\Pages;

use App\Filament\BrAdmin\Resources\AccommodationAroundResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccommodationArounds extends ListRecords
{
    protected static string $resource = AccommodationAroundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

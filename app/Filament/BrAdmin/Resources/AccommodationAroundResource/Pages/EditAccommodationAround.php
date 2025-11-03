<?php

namespace App\Filament\BrAdmin\Resources\AccommodationAroundResource\Pages;

use App\Filament\BrAdmin\Resources\AccommodationAroundResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccommodationAround extends EditRecord
{
    protected static string $resource = AccommodationAroundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

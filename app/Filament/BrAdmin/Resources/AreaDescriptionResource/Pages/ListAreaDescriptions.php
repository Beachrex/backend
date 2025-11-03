<?php

namespace App\Filament\BrAdmin\Resources\AreaDescriptionResource\Pages;

use App\Filament\BrAdmin\Resources\AreaDescriptionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAreaDescriptions extends ListRecords
{
    protected static string $resource = AreaDescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

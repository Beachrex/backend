<?php

namespace App\Filament\BrAdmin\Resources\NaturalLocationConditionResource\Pages;

use App\Filament\BrAdmin\Resources\NaturalLocationConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNaturalLocationConditions extends ListRecords
{
    protected static string $resource = NaturalLocationConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

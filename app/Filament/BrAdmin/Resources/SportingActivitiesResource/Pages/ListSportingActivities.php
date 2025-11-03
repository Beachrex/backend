<?php

namespace App\Filament\BrAdmin\Resources\SportingActivitiesResource\Pages;

use App\Filament\BrAdmin\Resources\SportingActivitiesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSportingActivities extends ListRecords
{
    protected static string $resource = SportingActivitiesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\BrAdmin\Resources\SportingActivitiesResource\Pages;

use App\Filament\BrAdmin\Resources\SportingActivitiesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSportingActivities extends EditRecord
{
    protected static string $resource = SportingActivitiesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\BrAdmin\Resources\LocationInfrastructureResource\Pages;

use App\Filament\BrAdmin\Resources\LocationInfrastructureResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocationInfrastructure extends EditRecord
{
    protected static string $resource = LocationInfrastructureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

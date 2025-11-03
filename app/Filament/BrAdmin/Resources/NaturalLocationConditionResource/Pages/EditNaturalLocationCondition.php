<?php

namespace App\Filament\BrAdmin\Resources\NaturalLocationConditionResource\Pages;

use App\Filament\BrAdmin\Resources\NaturalLocationConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNaturalLocationCondition extends EditRecord
{
    protected static string $resource = NaturalLocationConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\BrAdmin\Resources\SwimmingDepthOfOneMResource\Pages;

use App\Filament\BrAdmin\Resources\SwimmingDepthOfOneMResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSwimmingDepthOfOneM extends EditRecord
{
    protected static string $resource = SwimmingDepthOfOneMResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

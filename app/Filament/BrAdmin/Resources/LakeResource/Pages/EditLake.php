<?php

namespace App\Filament\BrAdmin\Resources\LakeResource\Pages;

use App\Filament\BrAdmin\Resources\LakeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLake extends EditRecord
{
    protected static string $resource = LakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}


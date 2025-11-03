<?php

namespace App\Filament\BrAdmin\Resources\LakeResource\Pages;

use App\Filament\BrAdmin\Resources\LakeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLakes extends ListRecords
{
    protected static string $resource = LakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


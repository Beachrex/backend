<?php

namespace App\Filament\BrAdmin\Resources\FirstPageContentItemResource\Pages;

use App\Filament\BrAdmin\Resources\FirstPageContentItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFirstPageContentItems extends ListRecords
{
    protected static string $resource = FirstPageContentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}


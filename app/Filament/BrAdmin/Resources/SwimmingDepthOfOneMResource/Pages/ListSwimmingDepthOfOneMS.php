<?php

namespace App\Filament\BrAdmin\Resources\SwimmingDepthOfOneMResource\Pages;

use App\Filament\BrAdmin\Resources\SwimmingDepthOfOneMResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSwimmingDepthOfOneMS extends ListRecords
{
    protected static string $resource = SwimmingDepthOfOneMResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

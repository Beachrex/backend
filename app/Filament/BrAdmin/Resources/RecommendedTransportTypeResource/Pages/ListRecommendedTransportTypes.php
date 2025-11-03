<?php

namespace App\Filament\BrAdmin\Resources\RecommendedTransportTypeResource\Pages;

use App\Filament\BrAdmin\Resources\RecommendedTransportTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecommendedTransportTypes extends ListRecords
{
    protected static string $resource = RecommendedTransportTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

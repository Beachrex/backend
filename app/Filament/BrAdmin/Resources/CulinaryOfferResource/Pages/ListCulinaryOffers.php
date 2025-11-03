<?php

namespace App\Filament\BrAdmin\Resources\CulinaryOfferResource\Pages;

use App\Filament\BrAdmin\Resources\CulinaryOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCulinaryOffers extends ListRecords
{
    protected static string $resource = CulinaryOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

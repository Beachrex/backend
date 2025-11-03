<?php

namespace App\Filament\BrAdmin\Resources\CulinaryOfferResource\Pages;

use App\Filament\BrAdmin\Resources\CulinaryOfferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCulinaryOffer extends EditRecord
{
    protected static string $resource = CulinaryOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

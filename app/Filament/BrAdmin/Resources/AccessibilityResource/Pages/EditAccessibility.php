<?php

namespace App\Filament\BrAdmin\Resources\AccessibilityResource\Pages;

use App\Filament\BrAdmin\Resources\AccessibilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccessibility extends EditRecord
{
    protected static string $resource = AccessibilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

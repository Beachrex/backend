<?php

namespace App\Filament\BrAdmin\Resources\BeachResource\Pages;

use App\Filament\BrAdmin\Resources\BeachResource;
use App\Filament\BrAdmin\Resources\BeachResource\Widgets\BeachMapWidget;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBeach extends EditRecord
{
    protected static string $resource = BeachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        $beach = $this->getRecord();
        $beachName = $beach->translations()->where('locale', 'en')->first()?->name
            ?? $beach->translations()->first()?->name
            ?? 'Beach #' . $beach->id;

        return 'Edit Beach: ' . $beachName;
    }

    protected function getFooterWidgets(): array
    {
        return [
            BeachMapWidget::class,
        ];
    }
}

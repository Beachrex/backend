<?php

namespace App\Filament\BrAdmin\Resources\BeachResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class BeachMapWidget extends Widget
{
    protected static string $view = 'filament.widgets.beach-map-widget';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    public function getLatitude(): ?float
    {
        return $this->record?->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->record?->longitude;
    }

    public function getBeachName(): string
    {
        if (!$this->record) {
            return 'New Beach';
        }

        return $this->record->translations()->where('locale', 'en')->first()?->name 
            ?? $this->record->translations()->first()?->name 
            ?? 'Beach #' . $this->record->id;
    }

    public function hasCoordinates(): bool
    {
        return $this->record && $this->record->latitude && $this->record->longitude;
    }
}


<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Filament\BrAdmin\Resources\BeachResource;
use App\Models\Beach;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CoverageGapsWidget extends BaseWidget
{
    protected static ?int $sort = 8;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Coverage Gaps - Beaches Needing Attention')
            ->description('Beaches with missing critical data (no GPS, no media, or incomplete translations)')
            ->query(
                Beach::query()
                    ->with(['translations', 'country.translations', 'status'])
                    ->where(function ($query) {
                        $query->whereNull('latitude')
                            ->orWhereNull('longitude')
                            ->orWhereDoesntHave('media')
                            ->orWhereHas('translations', function ($q) {
                                $q->havingRaw('COUNT(*) < 14');
                            }, '<', 14);
                    })
                    ->limit(15)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Beach Name')
                    ->getStateUsing(function ($record) {
                        return $record->translations()->where('locale', 'en')->first()?->name 
                            ?? $record->translations()->first()?->name 
                            ?? 'Beach #' . $record->id;
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->getStateUsing(function ($record) {
                        return $record->country?->translations()->where('locale', 'en')->first()?->name 
                            ?? $record->country?->code 
                            ?? '';
                    }),
                Tables\Columns\IconColumn::make('has_gps')
                    ->label('GPS')
                    ->getStateUsing(fn ($record) => $record->latitude && $record->longitude)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('has_media')
                    ->label('Media')
                    ->getStateUsing(fn ($record) => $record->media()->count() > 0)
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('translations_count')
                    ->label('Translations')
                    ->getStateUsing(fn ($record) => $record->translations()->count() . '/14')
                    ->badge()
                    ->color(fn ($record) => match(true) {
                        $record->translations()->count() === 14 => 'success',
                        $record->translations()->count() >= 10 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('status.name')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Fix')
                    ->icon('heroicon-o-wrench')
                    ->color('warning')
                    ->url(fn ($record) => BeachResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}


<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Filament\BrAdmin\Resources\BeachResource;
use App\Models\Beach;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PopularBeachesWidget extends BaseWidget
{
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Most Popular Beaches')
            ->description('Beaches with the most media files and complete data')
            ->query(
                Beach::query()
                    ->with(['translations', 'country.translations', 'status', 'media'])
                    ->withCount('media')
                    ->whereHas('media')
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->orderByDesc('media_count')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Beach Name')
                    ->getStateUsing(function ($record) {
                        return $record->translations()->where('locale', 'en')->first()?->name 
                            ?? $record->translations()->first()?->name 
                            ?? 'Beach #' . $record->id;
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->getStateUsing(function ($record) {
                        return $record->country?->translations()->where('locale', 'en')->first()?->name 
                            ?? $record->country?->code 
                            ?? '';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('media_count')
                    ->label('Media Files')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('translations_count')
                    ->label('Translations')
                    ->getStateUsing(fn ($record) => $record->translations()->count() . '/14')
                    ->badge()
                    ->color(fn ($record) => $record->translations()->count() === 14 ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('status.name')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => BeachResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}


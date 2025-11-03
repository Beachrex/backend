<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\RegionResource\Pages;
use App\Filament\BrAdmin\Resources\RegionResource\RelationManagers;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Region Code')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Unique identifier for the region (e.g., dalmatia-split)'),
                Forms\Components\Select::make('country_code')
                    ->label('Country')
                    ->options(function () {
                        return \App\Models\Country::with('translations')
                            ->get()
                            ->mapWithKeys(function ($country) {
                                $name = $country->translations->where('locale', 'en')->first()?->name ?? $country->code;
                                return [$country->code => $name . ' (' . $country->code . ')'];
                            })
                            ->sort();
                    })
                    ->searchable()
                    ->required()
                    ->helperText('Select the country this region belongs to'),
                Forms\Components\Select::make('status_id')
                    ->relationship('status', 'name')
                    ->required()
                    ->default(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Region Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Region $record): string => $record->country?->name ?? ''),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('beaches_count')
                    ->label('Beaches')
                    ->counts('beaches')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('country_code')
                    ->label('Country')
                    ->options(function () {
                        return \App\Models\Country::with('translations')
                            ->get()
                            ->mapWithKeys(function ($country) {
                                $name = $country->translations->where('locale', 'en')->first()?->name ?? $country->code;
                                return [$country->code => $name];
                            })
                            ->sort();
                    })
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'translations',
                'country.translations',
                'status',
            ])
            ->withCount('beaches');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TranslationsRelationManager::class,
            RelationManagers\BadgesRelationManager::class,
            RelationManagers\ArticlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegions::route('/'),
            'create' => Pages\CreateRegion::route('/create'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }
}

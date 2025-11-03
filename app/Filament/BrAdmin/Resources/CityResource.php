<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\CityResource\Pages;
use App\Filament\BrAdmin\Resources\CityResource\RelationManagers;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $navigationGroup = 'Geography';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('City Code')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Unique identifier for the city (e.g., split, dubrovnik)'),
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
                    ->reactive()
                    ->helperText('Select the country this city belongs to'),
                Forms\Components\Select::make('region_id')
                    ->label('Region')
                    ->options(function (callable $get) {
                        $countryCode = $get('country_code');
                        if (!$countryCode) {
                            return [];
                        }
                        return \App\Models\Region::with('translations')
                            ->where('country_code', $countryCode)
                            ->get()
                            ->mapWithKeys(function ($region) {
                                $name = $region->translations->where('locale', 'en')->first()?->name ?? $region->code;
                                return [$region->id => $name];
                            })
                            ->sort();
                    })
                    ->searchable()
                    ->helperText('Select the region this city belongs to (optional)'),
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
                    ->label('City Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (City $record): string => $record->region?->name ?? $record->country?->name ?? ''),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region')
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
                Tables\Filters\SelectFilter::make('region_id')
                    ->label('Region')
                    ->relationship('region', 'code')
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
                'region.translations',
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
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}


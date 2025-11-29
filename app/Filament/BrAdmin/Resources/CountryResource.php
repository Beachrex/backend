<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\CountryResource\Pages;
use App\Filament\BrAdmin\Resources\CountryResource\RelationManagers;
use App\Models\Country;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(2),
                Forms\Components\TextInput::make('iso3')
                    ->maxLength(3),
                Forms\Components\TextInput::make('number')
                    ->maxLength(3),
                Forms\Components\TextInput::make('continent_code')
                    ->maxLength(2),
                Forms\Components\FileUpload::make('image_path')
                    ->label('Country Main Image')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    ->disk('spaces')
                    ->directory('public/img/countries')
                    ->visibility('public')
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->previewable()
                    ->helperText('Upload a representative image for this country (max 10MB). Will be saved to DigitalOcean Spaces.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder-country.png'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Country Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('iso3')
                    ->label('ISO3')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('number')
                    ->label('Number')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('continent_code')
                    ->label('Continent')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['translations', 'continent.translations']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'iso3', 'translations.name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->translations()->where('locale', 'en')->first()?->name ?? $record->code;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        $details = ['Code' => $record->code];

        if ($record->iso3) {
            $details['ISO3'] = $record->iso3;
        }

        if ($record->continent) {
            $continentName = $record->continent->translations()->where('locale', 'en')->first()?->name ?? $record->continent->code;
            $details['Continent'] = $continentName;
        }

        return $details;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['translations', 'continent.translations']);
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
            'index' => Pages\ListCountries::route('/'),
            'create' => Pages\CreateCountry::route('/create'),
            'edit' => Pages\EditCountry::route('/{record}/edit'),
        ];
    }
}

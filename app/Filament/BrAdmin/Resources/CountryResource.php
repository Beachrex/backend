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

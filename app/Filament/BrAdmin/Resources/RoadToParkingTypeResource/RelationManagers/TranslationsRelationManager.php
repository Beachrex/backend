<?php

namespace App\Filament\BrAdmin\Resources\RoadToParkingTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TranslationsRelationManager extends RelationManager
{
    protected static string $relationship = 'translations';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('locale')
                    ->label('Language')
                    ->options([
                        'en' => 'English',
                        'sl' => 'Slovenian',
                        'de' => 'German',
                        'hr' => 'Croatian',
                        'fr' => 'French',
                        'es' => 'Spanish',
                        'it' => 'Italian',
                        'ua' => 'Ukrainian',
                        'ru' => 'Russian',
                        'pl' => 'Polish',
                        'cs' => 'Czech',
                        'hu' => 'Hungarian',
                        'bg' => 'Bulgarian',
                        'sk' => 'Slovak',
                    ])
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug (URL-friendly)')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('locale')
                    ->label('Language')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'en' => 'success',
                        'sl' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'en' => '🇬🇧 English',
                        'sl' => '🇸🇮 Slovenian',
                        'de' => '🇩🇪 German',
                        'hr' => '🇭🇷 Croatian',
                        'fr' => '🇫🇷 French',
                        'es' => '🇪🇸 Spanish',
                        'it' => '🇮🇹 Italian',
                        'ua' => '🇺🇦 Ukrainian',
                        'ru' => '🇷🇺 Russian',
                        'pl' => '🇵🇱 Polish',
                        'cs' => '🇨🇿 Czech',
                        'hu' => '🇭🇺 Hungarian',
                        'bg' => '🇧🇬 Bulgarian',
                        'sk' => '🇸🇰 Slovak',
                        default => strtoupper($state),
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('locale', 'asc');
    }
}

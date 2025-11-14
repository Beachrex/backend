<?php

namespace App\Filament\BrAdmin\Resources\FirstPageContentResource\RelationManagers;

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
                
                Forms\Components\TextInput::make('title')
                    ->label('Title')
                    ->maxLength(255)
                    ->helperText('Main title/heading for this section'),
                
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(4)
                    ->maxLength(65535)
                    ->helperText('Detailed description or content'),
                
                Forms\Components\KeyValue::make('metadata')
                    ->label('Additional Metadata')
                    ->helperText('Optional additional translatable data')
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
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
                
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(60)
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


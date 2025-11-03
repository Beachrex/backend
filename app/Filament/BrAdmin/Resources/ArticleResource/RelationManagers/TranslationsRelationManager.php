<?php

namespace App\Filament\BrAdmin\Resources\ArticleResource\RelationManagers;

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
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug (URL-friendly)')
                    ->maxLength(255),
                Forms\Components\Textarea::make('excerpt')
                    ->label('Excerpt')
                    ->rows(3)
                    ->helperText('Short description/summary of the article'),
                Forms\Components\RichEditor::make('content')
                    ->label('Content')
                    ->helperText('Full article content'),
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('excerpt')
                    ->label('Excerpt')
                    ->limit(50)
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


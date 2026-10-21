<?php

namespace App\Filament\BrAdmin\Resources\BeachResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                        'da' => 'Danish',
                        'pt' => 'Portuguese',
                        'nl' => 'Dutch',
                        'el' => 'Greek',
                        'sq' => 'Albanian',
                    ])
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(5)
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug (URL-friendly)')
                    ->maxLength(255),
                // TASK-007: search snippet and FAQ (filled by content:ai where empty; edits here are kept).
                Forms\Components\Textarea::make('meta_description')
                    ->label('Search snippet (meta description)')
                    ->helperText('Shown in Google results. Up to about 155 characters.')
                    ->rows(2)
                    ->maxLength(320)
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('faq')
                    ->label('Frequently asked questions')
                    ->helperText('Shown on the beach page and to search engines. Leave empty to hide the section.')
                    ->schema([
                        Forms\Components\TextInput::make('q')->label('Question')->required()->maxLength(200),
                        Forms\Components\Textarea::make('a')->label('Answer')->required()->rows(2)->maxLength(700),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->maxItems(6)
                    ->defaultItems(0)
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),
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

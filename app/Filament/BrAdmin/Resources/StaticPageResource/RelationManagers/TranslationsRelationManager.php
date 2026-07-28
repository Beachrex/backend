<?php

namespace App\Filament\BrAdmin\Resources\StaticPageResource\RelationManagers;

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
        return $form->schema([
            Forms\Components\Select::make('locale')
                ->label('Language')
                ->options([
                    'en' => 'English', 'sl' => 'Slovenian', 'de' => 'German',
                    'hr' => 'Croatian', 'fr' => 'French', 'es' => 'Spanish',
                    'it' => 'Italian', 'ua' => 'Ukrainian', 'ru' => 'Russian',
                    'pl' => 'Polish', 'cs' => 'Czech', 'hu' => 'Hungarian',
                    'bg' => 'Bulgarian', 'sk' => 'Slovak', 'da' => 'Danish',
                    'pt' => 'Portuguese', 'nl' => 'Dutch', 'el' => 'Greek',
                    'sq' => 'Albanian',
                ])
                ->required()->searchable(),
            Forms\Components\TextInput::make('title')->label('Title')->required()->maxLength(255),
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255)
                ->helperText('URL-friendly slug. Localized for each language.'),
            Forms\Components\RichEditor::make('content')
                ->label('Content')
                ->toolbarButtons(['bold', 'italic', 'underline', 'heading', 'bulletList', 'orderedList', 'link', 'blockquote'])
                ->columnSpanFull(),
            Forms\Components\TextInput::make('meta_title')->label('Meta Title')->maxLength(255)->nullable(),
            Forms\Components\Textarea::make('meta_description')
                ->label('Meta Description')
                ->rows(2)
                ->maxLength(500)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('locale')
                    ->label('Language')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'en' => 'success', 'sl' => 'info', default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'en' => '🇬🇧 English', 'sl' => '🇸🇮 Slovenian', 'de' => '🇩🇪 German',
                        'hr' => '🇭🇷 Croatian', 'fr' => '🇫🇷 French', 'es' => '🇪🇸 Spanish',
                        'it' => '🇮🇹 Italian', 'ua' => '🇺🇦 Ukrainian', 'ru' => '🇷🇺 Russian',
                        'pl' => '🇵🇱 Polish', 'cs' => '🇨🇿 Czech', 'hu' => '🇭🇺 Hungarian',
                        'bg' => '🇧🇬 Bulgarian', 'sk' => '🇸🇰 Slovak', 'da' => '🇩🇰 Danish',
                        'pt' => '🇵🇹 Portuguese', 'nl' => '🇳🇱 Dutch', 'el' => '🇬🇷 Greek',
                        'sq' => '🇦🇱 Albanian', default => strtoupper($state),
                    })
                    ->sortable()->searchable(),
                Tables\Columns\TextColumn::make('title')->label('Title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->searchable()->toggleable(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('locale', 'asc');
    }
}

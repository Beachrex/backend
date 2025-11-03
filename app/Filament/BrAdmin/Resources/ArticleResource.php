<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\ArticleResource\Pages;
use App\Filament\BrAdmin\Resources\ArticleResource\RelationManagers;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Article Code')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Unique identifier for the article (e.g., krk-history, krk-cuisine)'),
                Forms\Components\Select::make('category')
                    ->label('Category')
                    ->options([
                        'History' => 'History',
                        'Culture' => 'Culture',
                        'Cuisine' => 'Cuisine',
                        'Sights' => 'Sights',
                        'Nature' => 'Nature',
                        'Activities' => 'Activities',
                    ])
                    ->searchable()
                    ->helperText('Article category'),
                Forms\Components\TextInput::make('feature_image')
                    ->label('Feature Image')
                    ->maxLength(255)
                    ->helperText('Feature image filename (upload images in admin)'),
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
                Tables\Columns\TextColumn::make('title')
                    ->label('Article Title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'History' => 'info',
                        'Culture' => 'success',
                        'Cuisine' => 'warning',
                        'Sights' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('translations_count')
                    ->label('Translations')
                    ->counts('translations')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 14 => 'success',
                        $state >= 10 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state): string => $state . '/14')
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
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'History' => 'History',
                        'Culture' => 'Culture',
                        'Cuisine' => 'Cuisine',
                        'Sights' => 'Sights',
                        'Nature' => 'Nature',
                        'Activities' => 'Activities',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name'),
                Tables\Filters\TernaryFilter::make('missing_translations')
                    ->label('Translations')
                    ->placeholder('All articles')
                    ->trueLabel('Complete (all 14 languages)')
                    ->falseLabel('Missing translations')
                    ->queries(
                        true: fn (Builder $query) => $query->has('translations', '=', 14),
                        false: fn (Builder $query) => $query->has('translations', '<', 14),
                        blank: fn (Builder $query) => $query,
                    ),
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
                'status',
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TranslationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}

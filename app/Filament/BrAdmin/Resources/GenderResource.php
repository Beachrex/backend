<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\GenderResource\Pages;
use App\Filament\BrAdmin\Resources\GenderResource\RelationManagers;
use App\Models\Gender;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GenderResource extends Resource
{
    protected static ?string $model = Gender::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Code')
                    ->required()
                    ->maxLength(20)
                    ->helperText('Internal identifier (e.g., male, female, other)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('translated_name')
                    ->label('English Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('translations_count')
                    ->label('Translations')
                    ->counts('translations')
                    ->badge()
                    ->color(fn (int $state): string => $state >= 14 ? 'success' : ($state > 0 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn (int $state): string => "$state/14"),
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
                Tables\Filters\TernaryFilter::make('missing_translations')
                    ->label('Translations')
                    ->placeholder('All genders')
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
            ->defaultSort('id', 'asc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['translations'])
            ->withCount('translations');
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
            'index' => Pages\ListGenders::route('/'),
            'create' => Pages\CreateGender::route('/create'),
            'edit' => Pages\EditGender::route('/{record}/edit'),
        ];
    }
}

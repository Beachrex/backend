<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\MediaCollectionResource\Pages;
use App\Models\MediaCollection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaCollectionResource extends Resource
{
    protected static ?string $model = MediaCollection::class;
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Media';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(100),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->maxLength(500),
            Forms\Components\TagsInput::make('allowed_media_types')->label('Allowed Media Type Codes'),
            Forms\Components\TextInput::make('max_items')->numeric()->label('Max Items per Entity'),
            Forms\Components\Select::make('status_id')->relationship('status', 'name')->required()->default(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('media_count')->counts('media')->sortable(),
                Tables\Columns\TextColumn::make('max_items')->sortable(),
                Tables\Columns\TextColumn::make('status.name')->badge()->color(fn (string $state): string => match ($state) {
                    'Active' => 'success', 'Inactive' => 'danger', default => 'gray',
                })->sortable(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->relationship('status', 'name')])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('code', 'asc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['status'])->withCount('media');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaCollections::route('/'),
            'create' => Pages\CreateMediaCollection::route('/create'),
            'edit' => Pages\EditMediaCollection::route('/{record}/edit'),
        ];
    }
}


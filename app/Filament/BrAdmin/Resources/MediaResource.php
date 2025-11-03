<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\MediaResource\Pages;
use App\Models\Media;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Media';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('file_name')->required()->maxLength(255),
            Forms\Components\TextInput::make('file_path')->required()->maxLength(500),
            Forms\Components\Select::make('media_type_id')->relationship('mediaType', 'name')->required(),
            Forms\Components\Select::make('media_collection_id')->relationship('mediaCollection', 'name')->required(),
            Forms\Components\TextInput::make('mediable_type')->label('Entity Type')->maxLength(255),
            Forms\Components\TextInput::make('mediable_id')->label('Entity ID')->numeric(),
            Forms\Components\TextInput::make('disk')->default('spaces')->maxLength(50),
            Forms\Components\TextInput::make('mime_type')->maxLength(100),
            Forms\Components\TextInput::make('size')->numeric()->label('Size (bytes)'),
            Forms\Components\TextInput::make('width')->numeric(),
            Forms\Components\TextInput::make('height')->numeric(),
            Forms\Components\Toggle::make('is_primary')->label('Primary Media'),
            Forms\Components\TextInput::make('order')->numeric()->default(0),
            Forms\Components\Select::make('status_id')->relationship('status', 'name')->required()->default(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('url')->label('Preview')->square(),
                Tables\Columns\TextColumn::make('file_name')->searchable()->sortable()->limit(30),
                Tables\Columns\TextColumn::make('mediaType.name')->label('Type')->sortable(),
                Tables\Columns\TextColumn::make('mediaCollection.name')->label('Collection')->sortable(),
                Tables\Columns\TextColumn::make('mediable_type')->label('Entity')->sortable(),
                Tables\Columns\IconColumn::make('is_primary')->boolean()->label('Primary'),
                Tables\Columns\TextColumn::make('status.name')->badge()->color(fn (string $state): string => match ($state) {
                    'Active' => 'success', 'Inactive' => 'danger', default => 'gray',
                })->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('media_type')->relationship('mediaType', 'name'),
                Tables\Filters\SelectFilter::make('media_collection')->relationship('mediaCollection', 'name'),
                Tables\Filters\SelectFilter::make('status')->relationship('status', 'name'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['mediaType', 'mediaCollection', 'status']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedia::route('/'),
            'create' => Pages\CreateMedia::route('/create'),
            'edit' => Pages\EditMedia::route('/{record}/edit'),
        ];
    }
}


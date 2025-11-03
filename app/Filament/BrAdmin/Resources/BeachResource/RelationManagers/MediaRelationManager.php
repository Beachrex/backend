<?php

namespace App\Filament\BrAdmin\Resources\BeachResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('media_type_id')
                    ->relationship('mediaType', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? $record->code)
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('media_collection_id')
                    ->relationship('mediaCollection', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('file_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('file_path')
                    ->required()
                    ->maxLength(500),
                Forms\Components\Select::make('disk')
                    ->options([
                        'local' => 'Local',
                        'public' => 'Public',
                        'spaces' => 'DigitalOcean Spaces',
                    ])
                    ->default('spaces')
                    ->required(),
                Forms\Components\TextInput::make('mime_type')
                    ->maxLength(100),
                Forms\Components\TextInput::make('size')
                    ->numeric(),
                Forms\Components\TextInput::make('width')
                    ->numeric(),
                Forms\Components\TextInput::make('height')
                    ->numeric(),
                Forms\Components\TextInput::make('order')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary Image'),
                Forms\Components\Select::make('status_id')
                    ->relationship('status', 'name')
                    ->default(1)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('file_name')
            ->columns([
                Tables\Columns\ImageColumn::make('url')
                    ->label('Preview')
                    ->size(80)
                    ->defaultImageUrl(fn ($record) => $record->url ?? null),
                Tables\Columns\TextColumn::make('file_name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('mediaType.name')
                    ->label('Type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('mediaCollection.name')
                    ->label('Collection')
                    ->sortable(),
                Tables\Columns\TextColumn::make('width')
                    ->label('Dimensions')
                    ->formatStateUsing(fn ($record) => $record->width && $record->height ? "{$record->width}x{$record->height}" : '-'),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $this->formatFileSize($state)),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),
                Tables\Columns\TextColumn::make('order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_id === 1 ? 'success' : 'danger'),
            ])
            ->defaultSort('order')
            ->filters([
                Tables\Filters\SelectFilter::make('media_type_id')
                    ->relationship('mediaType', 'name')
                    ->label('Type'),
                Tables\Filters\SelectFilter::make('media_collection_id')
                    ->relationship('mediaCollection', 'name')
                    ->label('Collection'),
                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label('Primary Only'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn ($record) => view('filament.resources.media.view-modal', ['record' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['mediaType', 'mediaCollection', 'status']));
    }

    protected function formatFileSize(?int $bytes): string
    {
        if (!$bytes) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}


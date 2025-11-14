<?php

namespace App\Filament\BrAdmin\Resources\FirstPageContentItemResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('File Information')
                    ->schema([
                        Forms\Components\TextInput::make('file_name')
                            ->label('File Name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('file_path')
                            ->label('File Path')
                            ->required()
                            ->maxLength(500)
                            ->helperText('Path relative to CDN (e.g., img/first-page/icon.svg)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Media Details')
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
                            ->preload()
                            ->helperText('Optional: icon, main_image, etc.'),
                        
                        Forms\Components\Select::make('disk')
                            ->options([
                                'local' => 'Local',
                                'public' => 'Public',
                                'spaces' => 'DigitalOcean Spaces',
                            ])
                            ->default('spaces')
                            ->required(),
                        
                        Forms\Components\Select::make('status_id')
                            ->relationship('status', 'name')
                            ->default(1)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Image Properties')
                    ->schema([
                        Forms\Components\TextInput::make('mime_type')
                            ->label('MIME Type')
                            ->maxLength(100)
                            ->placeholder('e.g., image/svg+xml'),
                        
                        Forms\Components\TextInput::make('size')
                            ->label('File Size (bytes)')
                            ->numeric(),
                        
                        Forms\Components\TextInput::make('width')
                            ->label('Width (px)')
                            ->numeric(),
                        
                        Forms\Components\TextInput::make('height')
                            ->label('Height (px)')
                            ->numeric(),
                        
                        Forms\Components\TextInput::make('order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first'),
                        
                        Forms\Components\Toggle::make('is_primary')
                            ->label('Set as Primary')
                            ->helperText('Only one media should be marked as primary'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
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
                    ->sortable()
                    ->toggleable(),
                
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
}


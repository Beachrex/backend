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
                Forms\Components\FileUpload::make('file_upload')
                    ->label('Upload Image')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    ->maxSize(10240) // 10MB
                    ->disk('public')
                    ->directory('beach-uploads')
                    ->visibility('public')
                    ->downloadable()
                    ->openable()
                    ->previewable()
                    ->columnSpanFull()
                    ->helperText('Upload an image file (max 10MB). Supported formats: JPG, PNG, GIF, WebP')
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            // Auto-fill file_name from uploaded file
                            $set('file_name', $state->getClientOriginalName());
                        }
                    })
                    ->live()
                    ->hiddenOn('edit'),

                Forms\Components\Section::make('File Information')
                    ->schema([
                        Forms\Components\TextInput::make('file_name')
                            ->label('File Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('file_path')
                            ->label('File Path')
                            ->required()
                            ->maxLength(500)
                            ->helperText('Path to the file in storage (e.g., img/beaches/beach-name/image.jpg)')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Media Details')
                    ->schema([
                        Forms\Components\Select::make('media_type_id')
                            ->relationship('mediaType', 'code')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? $record->code)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                        Forms\Components\Select::make('media_collection_id')
                            ->relationship('mediaCollection', 'code')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? $record->code)
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                        Forms\Components\Select::make('disk')
                            ->options([
                                'local' => 'Local',
                                'public' => 'Public',
                                'spaces' => 'DigitalOcean Spaces',
                            ])
                            ->default('spaces')
                            ->required()
                            ->columnSpan(1),
                        Forms\Components\Select::make('status_id')
                            ->relationship('status', 'name')
                            ->default(1)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Image Properties')
                    ->schema([
                        Forms\Components\TextInput::make('mime_type')
                            ->label('MIME Type')
                            ->maxLength(100)
                            ->placeholder('e.g., image/jpeg')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('size')
                            ->label('File Size (bytes)')
                            ->numeric()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('width')
                            ->label('Width (px)')
                            ->numeric()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('height')
                            ->label('Height (px)')
                            ->numeric()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first')
                            ->columnSpan(1),
                        Forms\Components\Toggle::make('is_primary')
                            ->label('Set as Primary Image')
                            ->helperText('Only one image should be marked as primary')
                            ->columnSpan(1),
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
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // If a file was uploaded, process it
                        if (isset($data['file_upload']) && $data['file_upload']) {
                            $uploadedFile = $data['file_upload'];

                            // Get file information
                            $fileName = basename($uploadedFile);
                            $filePath = 'beach-uploads/' . $fileName;

                            // Get image dimensions if it's an image
                            $fullPath = storage_path('app/public/' . $uploadedFile);
                            if (file_exists($fullPath) && @getimagesize($fullPath)) {
                                $imageInfo = getimagesize($fullPath);
                                $data['width'] = $imageInfo[0] ?? null;
                                $data['height'] = $imageInfo[1] ?? null;
                                $data['mime_type'] = $imageInfo['mime'] ?? null;
                            }

                            // Get file size
                            if (file_exists($fullPath)) {
                                $data['size'] = filesize($fullPath);
                            }

                            // Set file_path to the uploaded file path
                            $data['file_path'] = $uploadedFile;

                            // Set file_name if not already set
                            if (empty($data['file_name'])) {
                                $data['file_name'] = $fileName;
                            }

                            // Set disk to public since we uploaded to public disk
                            $data['disk'] = 'public';
                        }

                        // Remove the file_upload field as it's not in the database
                        unset($data['file_upload']);

                        return $data;
                    }),
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


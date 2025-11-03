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
                    ->disk('spaces')
                    ->directory(function ($livewire) {
                        $beach = $livewire->getOwnerRecord();
                        $folderName = $beach->folder_name ?? 'beach-' . $beach->id;
                        return 'public/img/beaches/' . $folderName;
                    })
                    ->visibility('public')
                    ->downloadable()
                    ->openable()
                    ->previewable()
                    ->columnSpanFull()
                    ->helperText(function ($livewire) {
                        $beach = $livewire->getOwnerRecord();
                        $folderName = $beach->folder_name ?? 'beach-' . $beach->id;
                        return 'Upload an image file (max 10MB). Will be saved to: public/img/beaches/' . $folderName . '/';
                    })
                    ->hiddenOn('edit'),

                Forms\Components\Section::make('File Information')
                    ->schema([
                        Forms\Components\TextInput::make('file_name')
                            ->label('File Name')
                            ->maxLength(255)
                            ->helperText('Automatically set from upload')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('file_path')
                            ->label('File Path')
                            ->maxLength(500)
                            ->helperText('Automatically set from upload, or enter manually for existing images')
                            ->required(fn ($get) => empty($get('file_upload')))
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
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        // If a file was uploaded, process it
                        if (isset($data['file_upload']) && $data['file_upload']) {
                            $uploadedFilePath = $data['file_upload'];

                            // The uploaded file path includes 'public/' prefix (e.g., public/img/beaches/hr-69-o-pag/filename.jpg)
                            // But we store it in the database without the 'public/' prefix (e.g., img/beaches/hr-69-o-pag/filename.jpg)
                            // This matches the existing media records
                            $data['file_path'] = str_replace('public/', '', $uploadedFilePath);

                            // Get file name from the path
                            $fileName = basename($uploadedFilePath);

                            // Set file_name if not already set
                            if (empty($data['file_name'])) {
                                $data['file_name'] = $fileName;
                            }

                            // Try to get image dimensions and metadata from DigitalOcean Spaces
                            try {
                                $disk = \Storage::disk('spaces');

                                // Get file size (use the full path with 'public/' prefix)
                                if ($disk->exists($uploadedFilePath)) {
                                    $data['size'] = $disk->size($uploadedFilePath);

                                    // Try to get image dimensions
                                    $tempFile = tempnam(sys_get_temp_dir(), 'beach_img_');
                                    file_put_contents($tempFile, $disk->get($uploadedFilePath));

                                    if (@getimagesize($tempFile)) {
                                        $imageInfo = getimagesize($tempFile);
                                        $data['width'] = $imageInfo[0] ?? null;
                                        $data['height'] = $imageInfo[1] ?? null;
                                        $data['mime_type'] = $imageInfo['mime'] ?? null;
                                    }

                                    unlink($tempFile);
                                }
                            } catch (\Exception $e) {
                                // If we can't get metadata, continue without it
                                \Log::warning('Could not extract image metadata: ' . $e->getMessage());
                            }

                            // Set disk to spaces since we uploaded to DigitalOcean Spaces
                            $data['disk'] = 'spaces';
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


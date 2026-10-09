<?php

namespace App\Filament\BrAdmin\Resources;

use App\Support\AsciiPath;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\BrAdmin\Resources\IslandResource\Pages;
use App\Filament\BrAdmin\Resources\IslandResource\RelationManagers;
use App\Models\Island;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IslandResource extends Resource
{
    protected static ?string $model = Island::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Geography';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Island Code')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Unique identifier for the island (e.g., krk, brac, hvar)'),
                Forms\Components\Select::make('status_id')
                    ->relationship('status', 'name')
                    ->required()
                    ->default(1)
                    ->searchable(),
                Forms\Components\FileUpload::make('main_image')
                    ->label('Island Main Image')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        null,
                        '16:9',
                        '4:3',
                        '1:1',
                    ])
                    ->disk('spaces')
                    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => AsciiPath::uploadFileName($file->getClientOriginalName()))
                    ->directory('public/img/islands')
                    ->visibility('public')
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->previewable()
                    ->helperText('Upload a representative image for this island (max 10MB). Will be saved to DigitalOcean Spaces.'),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Island Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('beaches_count')
                    ->label('Beaches')
                    ->counts('beaches')
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
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name'),
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
            ])
            ->withCount('beaches');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'translations.name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->translations()->where('locale', 'en')->first()?->name ?? $record->code;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        $details = ['Code' => $record->code];

        if ($record->beaches_count > 0) {
            $details['Beaches'] = $record->beaches_count;
        }

        return $details;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['translations'])->withCount('beaches');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TranslationsRelationManager::class,
            RelationManagers\BadgesRelationManager::class,
            RelationManagers\ArticlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIslands::route('/'),
            'create' => Pages\CreateIsland::route('/create'),
            'edit' => Pages\EditIsland::route('/{record}/edit'),
        ];
    }
}


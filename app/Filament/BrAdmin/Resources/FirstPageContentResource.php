<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\FirstPageContentResource\Pages;
use App\Filament\BrAdmin\Resources\FirstPageContentResource\RelationManagers;
use App\Models\FirstPageContent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FirstPageContentResource extends Resource
{
    protected static ?string $model = FirstPageContent::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'First Page Content';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique identifier (e.g., hero_banner, browse_map)'),
                        
                        Forms\Components\Select::make('section_type')
                            ->label('Section Type')
                            ->required()
                            ->options([
                                'banner' => 'Banner',
                                'text_image' => 'Text with Image',
                                'repeater' => 'Repeater (Multiple Items)',
                                'text_only' => 'Text Only',
                            ])
                            ->helperText('Type of content section'),
                        
                        Forms\Components\TextInput::make('order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Order in which this section appears on the page'),
                        
                        Forms\Components\Select::make('status_id')
                            ->relationship('status', 'name')
                            ->required()
                            ->default(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('config')
                            ->label('Additional Configuration')
                            ->helperText('Optional JSON configuration for layout, styling, etc.')
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\Placeholder::make('media_info')
                            ->label('')
                            ->content('Manage media files in the "Media" tab after creating this content section.'),
                    ])
                    ->visible(fn ($record) => $record !== null),
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
                    ->label('Title (EN)')
                    ->getStateUsing(function ($record) {
                        return $record->translations()->where('locale', 'en')->first()?->title ?? '-';
                    })
                    ->searchable()
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('section_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'banner' => 'success',
                        'text_image' => 'info',
                        'repeater' => 'warning',
                        'text_only' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
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
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('section_type')
                    ->label('Section Type')
                    ->options([
                        'banner' => 'Banner',
                        'text_image' => 'Text with Image',
                        'repeater' => 'Repeater',
                        'text_only' => 'Text Only',
                    ]),
                
                Tables\Filters\SelectFilter::make('status_id')
                    ->label('Status')
                    ->relationship('status', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['translations', 'status'])
            ->withCount('items');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'translations.title'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->translations()->where('locale', 'en')->first()?->title ?? $record->code;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        $details = ['Code' => $record->code];

        if ($record->section_type) {
            $details['Type'] = ucfirst(str_replace('_', ' ', $record->section_type));
        }

        return $details;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['translations']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TranslationsRelationManager::class,
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\MediaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFirstPageContents::route('/'),
            'create' => Pages\CreateFirstPageContent::route('/create'),
            'edit' => Pages\EditFirstPageContent::route('/{record}/edit'),
        ];
    }
}


<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\FirstPageContentItemResource\Pages;
use App\Filament\BrAdmin\Resources\FirstPageContentItemResource\RelationManagers;
use App\Models\FirstPageContentItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FirstPageContentItemResource extends Resource
{
    protected static ?string $model = FirstPageContentItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'First Page Items';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('first_page_content_id')
                            ->relationship('firstPageContent', 'code')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->title ?? $record->code)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Parent section this item belongs to'),
                        
                        Forms\Components\TextInput::make('order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Order in which this item appears within the section'),
                        
                        Forms\Components\Select::make('status_id')
                            ->relationship('status', 'name')
                            ->required()
                            ->default(1),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('config')
                            ->label('Additional Configuration')
                            ->helperText('Optional JSON configuration for this item')
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('firstPageContent.code')
                    ->label('Section')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('Title (EN)')
                    ->getStateUsing(function ($record) {
                        return $record->translations()->where('locale', 'en')->first()?->title ?? '-';
                    })
                    ->searchable()
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('translations_count')
                    ->label('Translations')
                    ->counts('translations')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('media_count')
                    ->label('Media')
                    ->counts('media')
                    ->sortable(),
                
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
            ])
            ->defaultSort('order', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('first_page_content_id')
                    ->label('Section')
                    ->relationship('firstPageContent', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->title ?? $record->code),
                
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
            ->with(['firstPageContent', 'translations', 'status'])
            ->withCount(['translations', 'media']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TranslationsRelationManager::class,
            RelationManagers\MediaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFirstPageContentItems::route('/'),
            'create' => Pages\CreateFirstPageContentItem::route('/create'),
            'edit' => Pages\EditFirstPageContentItem::route('/{record}/edit'),
        ];
    }
}


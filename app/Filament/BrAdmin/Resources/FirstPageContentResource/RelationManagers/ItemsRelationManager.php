<?php

namespace App\Filament\BrAdmin\Resources\FirstPageContentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
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
                    ->columns(2),

                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('config')
                            ->label('Additional Configuration')
                            ->helperText('Optional JSON configuration for this item')
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Translations')
                    ->schema([
                        Forms\Components\Placeholder::make('translations_info')
                            ->label('')
                            ->content('Manage translations in the "Translations" tab after creating this item.'),
                    ])
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('Order')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('Title (EN)')
                    ->getStateUsing(function ($record) {
                        return $record->translations()->where('locale', 'en')->first()?->title ?? '-';
                    })
                    ->searchable()
                    ->limit(50),
                
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
            ])
            ->defaultSort('order', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status_id')
                    ->label('Status')
                    ->relationship('status', 'name'),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'status'])->withCount(['translations', 'media']));
    }
}


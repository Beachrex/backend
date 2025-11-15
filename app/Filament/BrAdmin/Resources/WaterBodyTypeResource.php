<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\WaterBodyTypeResource\Pages;
use App\Filament\BrAdmin\Resources\WaterBodyTypeResource\RelationManagers;
use App\Models\WaterBodyType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WaterBodyTypeResource extends Resource
{
    protected static ?string $model = WaterBodyType::class;
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Beach Characteristics';
    protected static ?int $navigationSort = 13;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(100),
            Forms\Components\Select::make('status_id')->relationship('status', 'name')->required()->default(1)->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('beaches_count')->counts('beaches')->sortable(),
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
        return parent::getEloquentQuery()->with(['translations', 'status'])->withCount('beaches');
    }

    public static function getRelations(): array
    {
        return [RelationManagers\TranslationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaterBodyTypes::route('/'),
            'create' => Pages\CreateWaterBodyType::route('/create'),
            'edit' => Pages\EditWaterBodyType::route('/{record}/edit'),
        ];
    }
}


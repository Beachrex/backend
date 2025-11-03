<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\MediaTypeResource\Pages;
use App\Models\MediaType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaTypeResource extends Resource
{
    protected static ?string $model = MediaType::class;
    protected static ?string $navigationIcon = 'heroicon-o-film';
    protected static ?string $navigationGroup = 'Media';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->maxLength(100),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TagsInput::make('mime_types')->label('Allowed MIME Types')->helperText('e.g., image/jpeg, image/png'),
            Forms\Components\TextInput::make('max_file_size')->numeric()->label('Max File Size (bytes)'),
            Forms\Components\Select::make('status_id')->relationship('status', 'name')->required()->default(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('media_count')->counts('media')->sortable(),
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
        return parent::getEloquentQuery()->with(['status'])->withCount('media');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaTypes::route('/'),
            'create' => Pages\CreateMediaType::route('/create'),
            'edit' => Pages\EditMediaType::route('/{record}/edit'),
        ];
    }
}


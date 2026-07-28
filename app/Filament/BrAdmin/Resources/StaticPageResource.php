<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\StaticPageResource\Pages;
use App\Filament\BrAdmin\Resources\StaticPageResource\RelationManagers;
use App\Models\StaticPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StaticPageResource extends Resource
{
    protected static ?string $model = StaticPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Static Pages';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Page Settings')->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Page Code')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->helperText('Unique identifier (e.g., privacy-policy, terms-of-use)'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Footer Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first in the footer'),
                Forms\Components\Toggle::make('is_in_footer')
                    ->label('Show in Footer')
                    ->default(true)
                    ->helperText('Disable to hide this page from the footer'),
                Forms\Components\Select::make('status_id')
                    ->relationship('status', 'name')
                    ->required()
                    ->default(1)
                    ->searchable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title (EN)')
                    ->getStateUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->title ?? $record->code)
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')->sortable(),
                Tables\Columns\IconColumn::make('is_in_footer')
                    ->label('Footer')->boolean(),
                Tables\Columns\TextColumn::make('translations_count')
                    ->label('Translations')->counts('translations')->badge()
                    ->color(fn (int $state) => match (true) {
                        $state === 19 => 'success',
                        $state >= 10 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state) => $state . '/19')
                    ->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Active' => 'success', 'Disabled' => 'danger', default => 'gray',
                    })->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->relationship('status', 'name'),
                Tables\Filters\TernaryFilter::make('is_in_footer')->label('In Footer'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['translations', 'status']);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\TranslationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaticPages::route('/'),
            'create' => Pages\CreateStaticPage::route('/create'),
            'edit' => Pages\EditStaticPage::route('/{record}/edit'),
        ];
    }
}

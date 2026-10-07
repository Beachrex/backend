<?php

namespace App\Filament\BrAdmin\Resources;

use App\Support\AsciiPath;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\BrAdmin\Resources\ContinentResource\Pages;
use App\Filament\BrAdmin\Resources\ContinentResource\RelationManagers;
use App\Models\Continent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContinentResource extends Resource
{
    protected static ?string $model = Continent::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(2),
                Forms\Components\FileUpload::make('main_image')
                    ->label('Continent Main Image')
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
                    ->directory('public/img/continents')
                    ->visibility('public')
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->previewable()
                    ->helperText('Upload a representative image for this continent (max 10MB). Will be saved to DigitalOcean Spaces.'),
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
                    ->label('Continent Name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TranslationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContinents::route('/'),
            'create' => Pages\CreateContinent::route('/create'),
            'edit' => Pages\EditContinent::route('/{record}/edit'),
        ];
    }
}

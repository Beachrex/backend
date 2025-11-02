<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\BeachResource\Pages;
use App\Filament\BrAdmin\Resources\BeachResource\RelationManagers;
use App\Models\Beach;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BeachResource extends Resource
{
    protected static ?string $model = Beach::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
                Forms\Components\TextInput::make('name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('latitude')
                    ->numeric(),
                Forms\Components\TextInput::make('longitude')
                    ->numeric(),
                Forms\Components\Select::make('status_id')
                    ->relationship('status', 'name')
                    ->required()
                    ->default(1),
                Forms\Components\Select::make('country_code')
                    ->relationship('country', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('continent_code')
                    ->relationship('continent', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('location')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('PostGIS geometry field - not editable'),
                Forms\Components\Select::make('waterbody_id')
                    ->relationship('waterbody', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('beach_type_id')
                    ->relationship('beachType', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('area_description_id')
                    ->relationship('areaDescription', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('accessibility_id')
                    ->relationship('accessibility', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('road_to_parking_type_id')
                    ->relationship('roadToParkingType', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('parking')
                    ->options([
                        null => 'Undefined',
                        1 => 'No',
                        2 => 'Yes',
                    ])
                    ->placeholder('Select parking availability'),
                Forms\Components\Select::make('parking_capacity_id')
                    ->relationship('parkingCapacity', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('parking_beach_distance_id')
                    ->relationship('parkingBeachDistance', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('parking_beach_height_id')
                    ->relationship('parkingBeachHeight', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('location_length_id')
                    ->relationship('locationLength', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('bay_length_id')
                    ->relationship('bayLength', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('beach_inclination_id')
                    ->relationship('beachInclination', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('swimming_depth_of_one_m_id')
                    ->relationship('swimmingDepthOfOneM', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('occupancy_rate_id')
                    ->relationship('occupancyRate', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('water_body_type_id')
                    ->relationship('waterBodyType', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('island_id')
                    ->relationship('island', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('indoor_pools_count')
                    ->numeric(),
                Forms\Components\TextInput::make('outdoor_pools_count')
                    ->numeric(),
                Forms\Components\TextInput::make('indoor_area_m2')
                    ->numeric(),
                Forms\Components\TextInput::make('outdoor_area_m2')
                    ->numeric(),
                Forms\Components\Toggle::make('has_swimming_pool'),
                Forms\Components\Toggle::make('has_indoor_swimming_lanes'),
                Forms\Components\Toggle::make('has_outdoor_swimming_lanes'),
                Forms\Components\Toggle::make('has_indoor_children_pool'),
                Forms\Components\Toggle::make('has_outdoor_children_pool'),
                Forms\Components\Toggle::make('has_indoor_diving_board'),
                Forms\Components\Toggle::make('has_outdoor_diving_board'),
                Forms\Components\TextInput::make('indoor_water_slides_count')
                    ->numeric(),
                Forms\Components\TextInput::make('outdoor_water_slides_count')
                    ->numeric(),
                Forms\Components\Toggle::make('has_thermal_pool'),
                Forms\Components\Toggle::make('has_indoor_climbing_wall'),
                Forms\Components\Toggle::make('has_outdoor_climbing_wall'),
                Forms\Components\Toggle::make('has_indoor_lazy_river'),
                Forms\Components\Toggle::make('has_outdoor_lazy_river'),
                Forms\Components\Toggle::make('has_indoor_wave_pool'),
                Forms\Components\Toggle::make('has_outdoor_wave_pool'),
                Forms\Components\Toggle::make('has_indoor_whirlpool'),
                Forms\Components\Toggle::make('has_outdoor_whirlpool'),
                Forms\Components\Toggle::make('has_indoor_surfing'),
                Forms\Components\Toggle::make('has_outdoor_surfing'),
                Forms\Components\Toggle::make('has_sauna'),
                Forms\Components\Toggle::make('has_wellness'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Beach Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('continent.name')
                    ->label('Continent')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('waterbody.name')
                    ->label('Waterbody')
                    ->sortable(),
                Tables\Columns\TextColumn::make('beachType.name')
                    ->label('Beach Type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'country.translations',
                'continent.translations',
                'waterbody.translations',
                'beachType.translations',
                'status',
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
            'index' => Pages\ListBeaches::route('/'),
            'create' => Pages\CreateBeach::route('/create'),
            'edit' => Pages\EditBeach::route('/{record}/edit'),
        ];
    }
}

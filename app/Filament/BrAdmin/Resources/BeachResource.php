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
                Forms\Components\TextInput::make('location'),
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
                Forms\Components\TextInput::make('parking')
                    ->numeric(),
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
                Forms\Components\Toggle::make('has_swimming_pool')
                    ->required(),
                Forms\Components\Toggle::make('has_indoor_swimming_lanes')
                    ->required(),
                Forms\Components\Toggle::make('has_outdoor_swimming_lanes')
                    ->required(),
                Forms\Components\Toggle::make('has_indoor_children_pool')
                    ->required(),
                Forms\Components\Toggle::make('has_outdoor_children_pool')
                    ->required(),
                Forms\Components\Toggle::make('has_indoor_diving_board')
                    ->required(),
                Forms\Components\Toggle::make('has_outdoor_diving_board')
                    ->required(),
                Forms\Components\TextInput::make('indoor_water_slides_count')
                    ->numeric(),
                Forms\Components\TextInput::make('outdoor_water_slides_count')
                    ->numeric(),
                Forms\Components\Toggle::make('has_thermal_pool')
                    ->required(),
                Forms\Components\Toggle::make('has_indoor_climbing_wall')
                    ->required(),
                Forms\Components\Toggle::make('has_outdoor_climbing_wall')
                    ->required(),
                Forms\Components\Toggle::make('has_indoor_lazy_river')
                    ->required(),
                Forms\Components\Toggle::make('has_outdoor_lazy_river')
                    ->required(),
                Forms\Components\Toggle::make('has_indoor_wave_pool')
                    ->required(),
                Forms\Components\Toggle::make('has_outdoor_wave_pool')
                    ->required(),
                Forms\Components\Toggle::make('has_indoor_whirlpool')
                    ->required(),
                Forms\Components\Toggle::make('has_outdoor_whirlpool')
                    ->required(),
                Forms\Components\Toggle::make('has_indoor_surfing')
                    ->required(),
                Forms\Components\Toggle::make('has_outdoor_surfing')
                    ->required(),
                Forms\Components\Toggle::make('has_sauna')
                    ->required(),
                Forms\Components\Toggle::make('has_wellness')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('country_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('continent_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('location'),
                Tables\Columns\TextColumn::make('waterbody.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('beachType.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('areaDescription.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('accessibility.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roadToParkingType.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parking')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parkingCapacity.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parkingBeachDistance.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parkingBeachHeight.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('locationLength.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bayLength.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('beachInclination.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('swimmingDepthOfOneM.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('occupancyRate.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('waterBodyType.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('island.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('indoor_pools_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('outdoor_pools_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('indoor_area_m2')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('outdoor_area_m2')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_swimming_pool')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_indoor_swimming_lanes')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_outdoor_swimming_lanes')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_indoor_children_pool')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_outdoor_children_pool')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_indoor_diving_board')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_outdoor_diving_board')
                    ->boolean(),
                Tables\Columns\TextColumn::make('indoor_water_slides_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('outdoor_water_slides_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_thermal_pool')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_indoor_climbing_wall')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_outdoor_climbing_wall')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_indoor_lazy_river')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_outdoor_lazy_river')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_indoor_wave_pool')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_outdoor_wave_pool')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_indoor_whirlpool')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_outdoor_whirlpool')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_indoor_surfing')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_outdoor_surfing')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_sauna')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_wellness')
                    ->boolean(),
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
            'index' => Pages\ListBeaches::route('/'),
            'create' => Pages\CreateBeach::route('/create'),
            'edit' => Pages\EditBeach::route('/{record}/edit'),
        ];
    }
}

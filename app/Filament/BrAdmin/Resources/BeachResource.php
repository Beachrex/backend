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
                Forms\Components\Placeholder::make('beach_name_display')
                    ->label('Beach Name')
                    ->content(fn ($record) => $record ? ($record->translations()->where('locale', 'en')->first()?->name ?? $record->translations()->first()?->name ?? 'No name set') : 'New Beach')
                    ->helperText('To edit the beach name, use the "Translations" tab below'),
                Forms\Components\TextInput::make('folder_name')
                    ->label('Folder Name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Used for organizing beach images. Format: country-id-region-city (e.g., hr-69-o-pag)')
                    ->placeholder('e.g., hr-69-o-pag'),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name'),
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
                Forms\Components\Select::make('region_id')
                    ->label('Region')
                    ->options(function () {
                        return \App\Models\Region::with('translations', 'country')
                            ->get()
                            ->mapWithKeys(function ($region) {
                                $name = $region->translations->where('locale', 'en')->first()?->name ?? $region->code;
                                $countryCode = $region->country_code ?? '';
                                return [$region->id => $name . ' (' . $countryCode . ')'];
                            })
                            ->sort();
                    })
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
                Tables\Columns\ImageColumn::make('primaryMediaRelation.url')
                    ->label('Image')
                    ->size(60)
                    ->defaultImageUrl('/images/placeholder-beach.jpg'),
                Tables\Columns\TextColumn::make('translations.name')
                    ->label('Beach Name')
                    ->getStateUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->translations()->first()?->name ?? 'N/A')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country.name')
                    ->label('Country')
                    ->getStateUsing(fn ($record) => $record->country?->translations()->where('locale', 'en')->first()?->name ?? $record->country?->code ?? 'N/A')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region')
                    ->getStateUsing(fn ($record) => $record->region?->translations()->where('locale', 'en')->first()?->name ?? $record->region?->code ?? 'N/A')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('continent.name')
                    ->label('Continent')
                    ->getStateUsing(fn ($record) => $record->continent?->translations()->where('locale', 'en')->first()?->name ?? $record->continent?->code ?? 'N/A')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('waterbody.name')
                    ->label('Waterbody')
                    ->getStateUsing(fn ($record) => $record->waterbody?->translations()->where('locale', 'en')->first()?->name ?? $record->waterbody?->code ?? 'N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('beachType.name')
                    ->label('Beach Type')
                    ->getStateUsing(fn ($record) => $record->beachType?->translations()->where('locale', 'en')->first()?->name ?? $record->beachType?->code ?? 'N/A')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('location')
                    ->label('GPS')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->location !== null)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_media')
                    ->label('Media')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->media()->exists())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('translations_count')
                    ->label('Translations')
                    ->counts('translations')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 14 => 'success',
                        $state >= 10 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (int $state): string => $state . '/14')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'warning',
                        'Deleted' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name')
                    ->label('Status'),

                Tables\Filters\TernaryFilter::make('has_location')
                    ->label('GPS Location')
                    ->placeholder('All beaches')
                    ->trueLabel('Has GPS coordinates')
                    ->falseLabel('Missing GPS coordinates')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('location'),
                        false: fn (Builder $query) => $query->whereNull('location'),
                        blank: fn (Builder $query) => $query,
                    ),

                Tables\Filters\TernaryFilter::make('has_media')
                    ->label('Media')
                    ->placeholder('All beaches')
                    ->trueLabel('Has photos/videos')
                    ->falseLabel('Missing photos/videos')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('media'),
                        false: fn (Builder $query) => $query->whereDoesntHave('media'),
                        blank: fn (Builder $query) => $query,
                    ),

                Tables\Filters\TernaryFilter::make('missing_translations')
                    ->label('Translations')
                    ->placeholder('All beaches')
                    ->trueLabel('Complete (all 14 languages)')
                    ->falseLabel('Missing translations')
                    ->queries(
                        true: fn (Builder $query) => $query->has('translations', '=', 14),
                        false: fn (Builder $query) => $query->has('translations', '<', 14),
                        blank: fn (Builder $query) => $query,
                    ),

                Tables\Filters\SelectFilter::make('country_code')
                    ->relationship('country', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload()
                    ->label('Country'),

                Tables\Filters\SelectFilter::make('region_id')
                    ->relationship('region', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload()
                    ->label('Region'),

                Tables\Filters\SelectFilter::make('continent_code')
                    ->relationship('continent', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->translations()->where('locale', 'en')->first()?->name ?? $record->code)
                    ->searchable()
                    ->preload()
                    ->label('Continent'),
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
                'translations',
                'country.translations',
                'region.translations',
                'continent.translations',
                'waterbody.translations',
                'beachType.translations',
                'status',
                'primaryMediaRelation',
            ]);
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
            'index' => Pages\ListBeaches::route('/'),
            'create' => Pages\CreateBeach::route('/create'),
            'edit' => Pages\EditBeach::route('/{record}/edit'),
        ];
    }
}

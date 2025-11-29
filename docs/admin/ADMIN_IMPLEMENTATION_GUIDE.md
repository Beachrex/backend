# Beachrex Admin Panel - Implementation Guide

## Overview
This guide provides step-by-step instructions to complete the admin panel upgrade by creating all missing Filament resources.

## Quick Start - Resource Generation Command

For each missing resource, use this Artisan command pattern:
```bash
php artisan make:filament-resource ResourceName --generate --panel=admin
```

However, since database connection may not be available, manual creation is recommended using the templates below.

## Resource Templates

### Template 1: Simple Translatable Entity (Island, River, Lake, etc.)

**Use for:** Island, River, Lake, and all beach characteristics

**Files to create:**
1. Main Resource: `app/Filament/BrAdmin/Resources/XxxResource.php`
2. Pages (3): CreateXxx.php, EditXxx.php, ListXxx.php
3. RelationManager (1): TranslationsRelationManager.php

**Main Resource Template:**
```php
<?php

namespace App\Filament\BrAdmin\Resources;

use App\Filament\BrAdmin\Resources\XxxResource\Pages;
use App\Filament\BrAdmin\Resources\XxxResource\RelationManagers;
use App\Models\Xxx;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class XxxResource extends Resource
{
    protected static ?string $model = Xxx::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'GROUP_NAME';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(100),
            Forms\Components\Select::make('status_id')
                ->relationship('status', 'name')
                ->required()
                ->default(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Inactive' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->relationship('status', 'name'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['translations', 'status']);
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
            'index' => Pages\ListXxxs::route('/'),
            'create' => Pages\CreateXxx::route('/create'),
            'edit' => Pages\EditXxx::route('/{record}/edit'),
        ];
    }
}
```

### Template 2: Geographic Entity with Badges & Articles

**Use for:** Island (with badges/articles)

Add to getRelations():
```php
public static function getRelations(): array
{
    return [
        RelationManagers\TranslationsRelationManager::class,
        RelationManagers\BadgesRelationManager::class,
        RelationManagers\ArticlesRelationManager::class,
    ];
}
```

## Complete Resource List with Commands

### Geographic Entities

```bash
# Island (CREATED - needs pages and relation managers)
# River
php artisan make:filament-resource River --panel=admin
# Lake
php artisan make:filament-resource Lake --panel=admin
```

### Beach Characteristics (13 resources)

```bash
php artisan make:filament-resource AreaDescription --panel=admin
php artisan make:filament-resource Accessibility --panel=admin
php artisan make:filament-resource RoadToParkingType --panel=admin
php artisan make:filament-resource ParkingCapacity --panel=admin
php artisan make:filament-resource ParkingBeachDistance --panel=admin
php artisan make:filament-resource ParkingBeachHeight --panel=admin
php artisan make:filament-resource LocationLength --panel=admin
php artisan make:filament-resource BayLength --panel=admin
php artisan make:filament-resource BeachInclination --panel=admin
php artisan make:filament-resource SwimmingDepthOfOneM --panel=admin
php artisan make:filament-resource OccupancyRate --panel=admin
php artisan make:filament-resource BeachLocationType --panel=admin
php artisan make:filament-resource WaterBodyType --panel=admin
```

### Beach Features (8 resources)

```bash
php artisan make:filament-resource AccommodationAround --panel=admin
php artisan make:filament-resource CulinaryOffer --panel=admin
php artisan make:filament-resource LocationInfrastructure --panel=admin
php artisan make:filament-resource NaturalLocationCondition --panel=admin
php artisan make:filament-resource RecommendedTransportType --panel=admin
php artisan make:filament-resource SportingActivities --panel=admin
php artisan make:filament-resource UnderwaterCharacteristic --panel=admin
php artisan make:filament-resource UserType --panel=admin
```

### Media & System

```bash
php artisan make:filament-resource Media --panel=admin
php artisan make:filament-resource MediaType --panel=admin
php artisan make:filament-resource MediaCollection --panel=admin
php artisan make:filament-resource Status --panel=admin
```

## Navigation Groups Configuration

Add to each resource's `$navigationGroup` property:

- **Geography**: Continent, Country, Region, City, Island, River, Lake
- **Beaches**: Beach, BeachType, Waterbody
- **Beach Characteristics**: All characteristic resources
- **Beach Features**: All feature resources
- **Content**: Article, Badge
- **Media**: Media, MediaType, MediaCollection
- **System**: User, Status

## Icon Recommendations

```php
// Geography
'heroicon-o-globe-alt' // Continent
'heroicon-o-flag' // Country
'heroicon-o-map-pin' // Region
'heroicon-o-building-office-2' // City
'heroicon-o-map' // Island
'heroicon-o-arrow-trending-up' // River
'heroicon-o-circle-stack' // Lake

// Beach Characteristics
'heroicon-o-adjustments-horizontal' // General characteristics
'heroicon-o-chart-bar' // Measurements
'heroicon-o-scale' // Ratings

// Beach Features
'heroicon-o-home' // Accommodation
'heroicon-o-cake' // Culinary
'heroicon-o-wrench-screwdriver' // Infrastructure
'heroicon-o-sparkles' // Natural conditions
'heroicon-o-truck' // Transport
'heroicon-o-trophy' // Sports
'heroicon-o-eye' // Underwater
'heroicon-o-users' // User types

// Media
'heroicon-o-photo' // Media
'heroicon-o-film' // Media Type
'heroicon-o-folder' // Media Collection

// System
'heroicon-o-user-group' // Users
'heroicon-o-signal' // Status
```

## Batch Creation Script

Create this script as `create_all_resources.sh`:

```bash
#!/bin/bash

# Geographic
php artisan make:filament-resource River --panel=admin
php artisan make:filament-resource Lake --panel=admin

# Beach Characteristics
for resource in AreaDescription Accessibility RoadToParkingType ParkingCapacity \
                ParkingBeachDistance ParkingBeachHeight LocationLength BayLength \
                BeachInclination SwimmingDepthOfOneM OccupancyRate BeachLocationType \
                WaterBodyType; do
    php artisan make:filament-resource $resource --panel=admin
done

# Beach Features
for resource in AccommodationAround CulinaryOffer LocationInfrastructure \
                NaturalLocationCondition RecommendedTransportType SportingActivities \
                UnderwaterCharacteristic UserType; do
    php artisan make:filament-resource $resource --panel=admin
done

# Media & System
php artisan make:filament-resource Media --panel=admin
php artisan make:filament-resource MediaType --panel=admin
php artisan make:filament-resource MediaCollection --panel=admin
php artisan make:filament-resource Status --panel=admin

echo "All resources created!"
```

## Post-Creation Checklist

For each created resource:

- [ ] Update `$navigationGroup` property
- [ ] Update `$navigationIcon` property
- [ ] Update `$navigationSort` property
- [ ] Add proper form fields
- [ ] Add table columns with badges for status
- [ ] Add filters
- [ ] Add eager loading in `getEloquentQuery()`
- [ ] Create TranslationsRelationManager
- [ ] For geographic entities: Add BadgesRelationManager and ArticlesRelationManager
- [ ] Test CRUD operations

## BeachResource Enhancements

Add these RelationManagers to BeachResource:

```php
public static function getRelations(): array
{
    return [
        RelationManagers\TranslationsRelationManager::class,
        RelationManagers\MediaRelationManager::class,
        // ADD THESE:
        RelationManagers\AccommodationAroundRelationManager::class,
        RelationManagers\CulinaryOfferRelationManager::class,
        RelationManagers\LocationInfrastructureRelationManager::class,
        RelationManagers\NaturalLocationConditionRelationManager::class,
        RelationManagers\RecommendedTransportTypeRelationManager::class,
        RelationManagers\SportingActivitiesRelationManager::class,
        RelationManagers\UnderwaterCharacteristicRelationManager::class,
        RelationManagers\UserTypeRelationManager::class,
        RelationManagers\BeachLocationTypeRelationManager::class,
    ];
}
```

## Testing

After creating all resources:

1. Clear cache: `php artisan cache:clear`
2. Clear config: `php artisan config:clear`
3. Access admin panel: `/admin`
4. Test each resource:
   - Create new record
   - Edit existing record
   - Add translations
   - Delete record
   - Test filters and search

## Summary

**Total Resources to Create:** 29
**Resources Created:** 2 (City, Island)
**Remaining:** 27

**Estimated Time:** 16-18 hours for complete implementation

**Priority Order:**
1. Geographic entities (2 remaining): River, Lake
2. Beach characteristics (13): Most frequently used in beach forms
3. Beach features (8): Enable full beach feature management
4. Media & System (4): Complete the admin panel

## Files Created So Far

### CityResource ✅
- app/Filament/BrAdmin/Resources/CityResource.php
- app/Filament/BrAdmin/Resources/CityResource/Pages/CreateCity.php
- app/Filament/BrAdmin/Resources/CityResource/Pages/EditCity.php
- app/Filament/BrAdmin/Resources/CityResource/Pages/ListCities.php
- app/Filament/BrAdmin/Resources/CityResource/RelationManagers/TranslationsRelationManager.php
- app/Filament/BrAdmin/Resources/CityResource/RelationManagers/BadgesRelationManager.php
- app/Filament/BrAdmin/Resources/CityResource/RelationManagers/ArticlesRelationManager.php

### IslandResource ✅ (Partial)
- app/Filament/BrAdmin/Resources/IslandResource.php
- **TODO:** Create Pages and RelationManagers

## Next Steps

1. Complete IslandResource (pages + relation managers)
2. Create RiverResource and LakeResource
3. Create all 13 beach characteristic resources
4. Create all 8 beach feature resources
5. Create media management resources
6. Create StatusResource
7. Add missing RelationManagers to BeachResource
8. Organize navigation groups
9. Test all functionality


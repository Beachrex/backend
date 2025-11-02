<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed the status table
        $this->call([
            StatusSeeder::class,
            ContinentSeeder::class,
            CountrySeeder::class,
            DemoUserSeeder::class,
            WaterbodySeeder::class,
            BeachTypeSeeder::class,
            AreaDescriptionSeeder::class,
            AccessibilitySeeder::class,
            RoadToParkingTypeSeeder::class,
            ParkingCapacitySeeder::class,
            ParkingBeachDistanceSeeder::class,
            ParkingBeachHeightSeeder::class,
            LocationLengthSeeder::class,
            BayLengthSeeder::class,
            BeachInclinationSeeder::class,
            SwimmingDepthOfOneMSeeder::class,
            OccupancyRateSeeder::class,
            AccommodationAroundSeeder::class,
            CulinaryOfferSeeder::class,
            LocationInfrastructureSeeder::class,
            NaturalLocationConditionSeeder::class,
            RecommendedTransportTypeSeeder::class,
            SportingActivitiesSeeder::class,
            UnderwaterCharacteristicSeeder::class,
            UserTypeSeeder::class,
            MediaTypeSeeder::class,
            MediaCollectionSeeder::class,
            BeachLocationTypeSeeder::class,
            WaterBodyTypeSeeder::class,
            IslandSeeder::class,
//            BeachSeeder::class, // do not run since we have old DB migration
        ]);
    }
}

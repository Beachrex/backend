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
            BeachSeeder::class,
            WaterbodySeeder::class,
            BeachTypeSeeder::class,
            AreaDescriptionSeeder::class,
            AccessibilitySeeder::class,
            RoadToParkingTypeSeeder::class,
            ParkingCapacitySeeder::class,
        ]);
    }
}

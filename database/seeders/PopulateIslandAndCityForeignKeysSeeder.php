<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PopulateIslandAndCityForeignKeysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder populates the new foreign keys:
     * - island.country_code (from beaches on that island)
     * - island.region_id (from beaches on that island)
     * - city.island_id (from beaches in that city)
     */
    public function run(): void
    {
        $this->command->info('Starting to populate island and city foreign keys...');

        // Step 1: Populate island.country_code and island.region_id
        $this->populateIslandForeignKeys();

        // Step 2: Populate city.island_id
        $this->populateCityIslandId();

        $this->command->info('✅ Successfully populated all foreign keys!');
    }

    /**
     * Populate island.country_code and island.region_id from beaches
     */
    private function populateIslandForeignKeys(): void
    {
        $this->command->info('Populating island.country_code and island.region_id...');

        // Get all islands that have beaches
        $islands = DB::table('island')
            ->whereNotNull('id')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($islands as $island) {
            // Get the first beach on this island to get country_code and region_id
            $beach = DB::table('beach')
                ->where('island_id', $island->id)
                ->where('status_id', 1)
                ->select('country_code', 'region_id')
                ->first();

            if ($beach) {
                DB::table('island')
                    ->where('id', $island->id)
                    ->update([
                        'country_code' => $beach->country_code,
                        'region_id' => $beach->region_id,
                    ]);
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->command->line("  ✓ Updated: $updated islands");
        $this->command->line("  ⊘ Skipped (no beaches): $skipped islands");
    }

    /**
     * Populate city.island_id from beaches
     */
    private function populateCityIslandId(): void
    {
        $this->command->info('Populating city.island_id...');

        // Get all cities that have beaches
        $cities = DB::table('city')
            ->whereNotNull('id')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($cities as $city) {
            // Get the first beach in this city that has an island
            $beach = DB::table('beach')
                ->where('city_id', $city->id)
                ->where('status_id', 1)
                ->whereNotNull('island_id')
                ->select('island_id')
                ->first();

            if ($beach) {
                DB::table('city')
                    ->where('id', $city->id)
                    ->update([
                        'island_id' => $beach->island_id,
                    ]);
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->command->line("  ✓ Updated: $updated cities");
        $this->command->line("  ⊘ Skipped (no beaches with island): $skipped cities");
    }
}

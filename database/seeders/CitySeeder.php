<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Region;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (City::count() > 0) {
            $this->command->info('City table already seeded. Skipping.');
            return;
        }

        // Get first 4 regions to assign cities to
        $regions = Region::limit(4)->get();

        if ($regions->isEmpty()) {
            $this->command->warn('No regions found. Please run RegionSeeder first.');
            return;
        }

        $cities = [
            [
                'code' => 'RIJEKA',
                'country_code' => 'HR',
                'region_id' => $regions[0]->id ?? null,
                'status_id' => 1,
                'translations' => [
                    ['locale' => 'en', 'name' => 'Rijeka', 'slug' => 'rijeka'],
                    ['locale' => 'hr', 'name' => 'Rijeka', 'slug' => 'rijeka'],
                    ['locale' => 'sl', 'name' => 'Reka', 'slug' => 'reka'],
                ],
            ],
            [
                'code' => 'SPLIT',
                'country_code' => 'HR',
                'region_id' => $regions[1]->id ?? null,
                'status_id' => 1,
                'translations' => [
                    ['locale' => 'en', 'name' => 'Split', 'slug' => 'split'],
                    ['locale' => 'hr', 'name' => 'Split', 'slug' => 'split'],
                    ['locale' => 'it', 'name' => 'Spalato', 'slug' => 'spalato'],
                ],
            ],
            [
                'code' => 'ZADAR',
                'country_code' => 'HR',
                'region_id' => $regions[2]->id ?? null,
                'status_id' => 1,
                'translations' => [
                    ['locale' => 'en', 'name' => 'Zadar', 'slug' => 'zadar'],
                    ['locale' => 'hr', 'name' => 'Zadar', 'slug' => 'zadar'],
                    ['locale' => 'it', 'name' => 'Zara', 'slug' => 'zara'],
                ],
            ],
            [
                'code' => 'PULA',
                'country_code' => 'HR',
                'region_id' => $regions[3]->id ?? null,
                'status_id' => 1,
                'translations' => [
                    ['locale' => 'en', 'name' => 'Pula', 'slug' => 'pula'],
                    ['locale' => 'hr', 'name' => 'Pula', 'slug' => 'pula'],
                    ['locale' => 'it', 'name' => 'Pola', 'slug' => 'pola'],
                ],
            ],
        ];

        foreach ($cities as $cityData) {
            $translations = $cityData['translations'];
            unset($cityData['translations']);

            $city = City::create($cityData);

            foreach ($translations as $translation) {
                CityTranslation::create([
                    'city_id' => $city->id,
                    'locale' => $translation['locale'],
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                ]);
            }

            $this->command->info("Created city: {$city->code}");
        }

        $this->command->info('City seeding completed!');
    }
}

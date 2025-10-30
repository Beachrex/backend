<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationLengthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locationLengths = [
            ['id' => 1, 'code' => '5m'],
            ['id' => 2, 'code' => '10m'],
            ['id' => 3, 'code' => '20m'],
            ['id' => 4, 'code' => '30m'],
            ['id' => 5, 'code' => '40m'],
            ['id' => 6, 'code' => '50m'],
            ['id' => 7, 'code' => '75m'],
            ['id' => 8, 'code' => '100m'],
            ['id' => 9, 'code' => '150m'],
            ['id' => 10, 'code' => '200m'],
            ['id' => 11, 'code' => '250m'],
            ['id' => 12, 'code' => '300m'],
            ['id' => 13, 'code' => '400m'],
            ['id' => 14, 'code' => '500m'],
            ['id' => 15, 'code' => '750m'],
            ['id' => 16, 'code' => '1000m'],
            ['id' => 17, 'code' => 'more_km'],
        ];

        foreach ($locationLengths as $lengthData) {
            // Insert location length with explicit ID
            DB::table('location_length')->insert([
                'id' => $lengthData['id'],
                'code' => $lengthData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Determine display name based on code
            $displayName = $lengthData['code'];
            $slug = $lengthData['code'];
            
            // Special handling for more_km
            if ($lengthData['code'] === 'more_km') {
                $translations = [
                    'en' => ['name' => '>1km', 'slug' => 'more-than-1km'],
                    'sl' => ['name' => '>1km', 'slug' => 'vec-kot-1km'],
                    'de' => ['name' => '>1km', 'slug' => 'mehr-als-1km'],
                    'hr' => ['name' => '>1km', 'slug' => 'vise-od-1km'],
                    'fr' => ['name' => '>1km', 'slug' => 'plus-de-1km'],
                    'es' => ['name' => '>1km', 'slug' => 'mas-de-1km'],
                    'it' => ['name' => '>1km', 'slug' => 'piu-di-1km'],
                    'ua' => ['name' => '>1км', 'slug' => 'bilshe-1km'],
                    'ru' => ['name' => '>1км', 'slug' => 'bolshe-1km'],
                    'pl' => ['name' => '>1km', 'slug' => 'wiecej-niz-1km'],
                    'cs' => ['name' => '>1km', 'slug' => 'vice-nez-1km'],
                    'hu' => ['name' => '>1km', 'slug' => 'tobb-mint-1km'],
                    'bg' => ['name' => '>1км', 'slug' => 'poveche-ot-1km'],
                    'sk' => ['name' => '>1km', 'slug' => 'viac-ako-1km'],

                ];
            } else {
                // For numeric lengths, use the same value for all languages
                $translations = [
                    'en' => ['name' => $displayName, 'slug' => $slug],
                    'sl' => ['name' => $displayName, 'slug' => $slug],
                    'de' => ['name' => $displayName, 'slug' => $slug],
                    'hr' => ['name' => $displayName, 'slug' => $slug],
                    'fr' => ['name' => $displayName, 'slug' => $slug],
                    'es' => ['name' => $displayName, 'slug' => $slug],
                    'it' => ['name' => $displayName, 'slug' => $slug],
                    'ua' => ['name' => str_replace('m', 'м', $displayName), 'slug' => $slug],
                    'ru' => ['name' => str_replace('m', 'м', $displayName), 'slug' => $slug],
                    'pl' => ['name' => $displayName, 'slug' => $slug],
                    'cs' => ['name' => $displayName, 'slug' => $slug],
                    'hu' => ['name' => $displayName, 'slug' => $slug],
                    'bg' => ['name' => str_replace('m', 'м', $displayName), 'slug' => $slug],
                    'sk' => ['name' => $displayName, 'slug' => $slug],

                ];
            }

            // Insert translations
            foreach ($translations as $locale => $translation) {
                DB::table('location_length_translation')->insert([
                    'location_length_id' => $lengthData['id'],
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Location lengths seeded successfully with translations in 14 languages!');
    }
}


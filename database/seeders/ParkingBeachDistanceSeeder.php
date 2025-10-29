<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParkingBeachDistanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parkingBeachDistances = [
            ['id' => 1, 'code' => '10m'],
            ['id' => 2, 'code' => '30m'],
            ['id' => 3, 'code' => '50m'],
            ['id' => 4, 'code' => '100m'],
            ['id' => 5, 'code' => '200m'],
            ['id' => 6, 'code' => '300m'],
            ['id' => 7, 'code' => '400m'],
            ['id' => 8, 'code' => '500m'],
            ['id' => 9, 'code' => '750m'],
            ['id' => 10, 'code' => '1000m'],
            ['id' => 11, 'code' => 'more_than_1000m'],
            ['id' => 12, 'code' => 'no_parking'],
            ['id' => 13, 'code' => '760m'],
            ['id' => 14, 'code' => '650m'],
            ['id' => 15, 'code' => '380m'],
            ['id' => 16, 'code' => '800m'],
            ['id' => 17, 'code' => '1380m'],
        ];

        foreach ($parkingBeachDistances as $distanceData) {
            // Insert parking beach distance with explicit ID
            DB::table('parking_beach_distance')->insert([
                'id' => $distanceData['id'],
                'code' => $distanceData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Determine display name based on code
            $displayName = $distanceData['code'];
            $slug = $distanceData['code'];
            
            // Special handling for specific codes
            if ($distanceData['code'] === 'more_than_1000m') {
                $translations = [
                    'en' => ['name' => '>1000m', 'slug' => 'more-than-1000m'],
                    'sl' => ['name' => '>1000m', 'slug' => 'vec-kot-1000m'],
                    'de' => ['name' => '>1000m', 'slug' => 'mehr-als-1000m'],
                    'hr' => ['name' => '>1000m', 'slug' => 'vise-od-1000m'],
                    'fr' => ['name' => '>1000m', 'slug' => 'plus-de-1000m'],
                    'es' => ['name' => '>1000m', 'slug' => 'mas-de-1000m'],
                    'it' => ['name' => '>1000m', 'slug' => 'piu-di-1000m'],
                    'ua' => ['name' => '>1000м', 'slug' => 'bilshe-1000m'],
                    'ru' => ['name' => '>1000м', 'slug' => 'bolshe-1000m'],
                ];
            } elseif ($distanceData['code'] === 'no_parking') {
                $translations = [
                    'en' => ['name' => 'No Parking', 'slug' => 'no-parking'],
                    'sl' => ['name' => 'Brez parkirišča', 'slug' => 'brez-parkirisca'],
                    'de' => ['name' => 'Kein Parkplatz', 'slug' => 'kein-parkplatz'],
                    'hr' => ['name' => 'Bez parkinga', 'slug' => 'bez-parkinga'],
                    'fr' => ['name' => 'Pas de parking', 'slug' => 'pas-de-parking'],
                    'es' => ['name' => 'Sin estacionamiento', 'slug' => 'sin-estacionamiento'],
                    'it' => ['name' => 'Nessun parcheggio', 'slug' => 'nessun-parcheggio'],
                    'ua' => ['name' => 'Без парковки', 'slug' => 'bez-parkovky'],
                    'ru' => ['name' => 'Без парковки', 'slug' => 'bez-parkovki'],
                ];
            } else {
                // For numeric distances, use the same value for all languages
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
                ];
            }

            // Insert translations
            foreach ($translations as $locale => $translation) {
                DB::table('parking_beach_distance_translation')->insert([
                    'parking_beach_distance_id' => $distanceData['id'],
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParkingBeachHeightSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parkingBeachHeights = [
            ['id' => 1, 'code' => '0m'],
            ['id' => 2, 'code' => '5m'],
            ['id' => 3, 'code' => '10m'],
            ['id' => 4, 'code' => '15m'],
            ['id' => 5, 'code' => '20m'],
            ['id' => 6, 'code' => '25m'],
            ['id' => 7, 'code' => '30m'],
            ['id' => 8, 'code' => '35m'],
            ['id' => 9, 'code' => '40m'],
            ['id' => 10, 'code' => '50m'],
            ['id' => 11, 'code' => '75m'],
            ['id' => 12, 'code' => '100m'],
            ['id' => 13, 'code' => '125m'],
            ['id' => 14, 'code' => '150m'],
            ['id' => 15, 'code' => '200m'],
            ['id' => 16, 'code' => '250m'],
            ['id' => 17, 'code' => '300m'],
            ['id' => 18, 'code' => 'no_parking'],
        ];

        foreach ($parkingBeachHeights as $heightData) {
            // Insert parking beach height with explicit ID
            DB::table('parking_beach_height')->insert([
                'id' => $heightData['id'],
                'code' => $heightData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Determine display name based on code
            $displayName = $heightData['code'];
            $slug = $heightData['code'];

            // Special handling for no_parking
            if ($heightData['code'] === 'no_parking') {
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
                    'pl' => ['name' => 'Brak parkingu', 'slug' => 'brak-parkingu'],
                    'cs' => ['name' => 'Bez parkování', 'slug' => 'bez-parkovani'],
                    'hu' => ['name' => 'Nincs parkoló', 'slug' => 'nincs-parkolo'],
                    'bg' => ['name' => 'Без паркинг', 'slug' => 'bez-parking'],
                    'sk' => ['name' => 'Bez parkovania', 'slug' => 'bez-parkovania'],

                ];
            } else {
                // For numeric heights, use the same value for all languages
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
                DB::table('parking_beach_height_translation')->insert([
                    'parking_beach_height_id' => $heightData['id'],
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Parking beach heights seeded successfully with translations in 14 languages!');
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoadToParkingTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roadToParkingTypes = [
            [
                'id' => 1,
                'code' => 'asphalt',
                'status_id' => 1,
                'show_filter' => true,
                'translations' => [
                    'en' => ['name' => 'Asphalt', 'slug' => 'asphalt'],
                    'sl' => ['name' => 'Asfalt', 'slug' => 'asfalt'],
                    'de' => ['name' => 'Asphalt', 'slug' => 'asphalt'],
                    'hr' => ['name' => 'Asfalt', 'slug' => 'asfalt'],
                    'fr' => ['name' => 'Asphalte', 'slug' => 'asphalte'],
                    'es' => ['name' => 'Asfalto', 'slug' => 'asfalto'],
                    'it' => ['name' => 'Asfalto', 'slug' => 'asfalto'],
                    'ua' => ['name' => 'Асфальт', 'slug' => 'asfalt'],
                    'ru' => ['name' => 'Асфальт', 'slug' => 'asfalt'],
                ],
            ],
            [
                'id' => 2,
                'code' => 'concrete_path',
                'status_id' => 1,
                'show_filter' => true,
                'translations' => [
                    'en' => ['name' => 'Concrete Path', 'slug' => 'concrete-path'],
                    'sl' => ['name' => 'Betonska pot', 'slug' => 'betonska-pot'],
                    'de' => ['name' => 'Betonweg', 'slug' => 'betonweg'],
                    'hr' => ['name' => 'Betonski put', 'slug' => 'betonski-put'],
                    'fr' => ['name' => 'Chemin en béton', 'slug' => 'chemin-en-beton'],
                    'es' => ['name' => 'Camino de hormigón', 'slug' => 'camino-de-hormigon'],
                    'it' => ['name' => 'Sentiero in cemento', 'slug' => 'sentiero-in-cemento'],
                    'ua' => ['name' => 'Бетонна доріжка', 'slug' => 'betonna-dorizhka'],
                    'ru' => ['name' => 'Бетонная дорожка', 'slug' => 'betonnaya-dorozhka'],
                ],
            ],
            [
                'id' => 3,
                'code' => 'dirt',
                'status_id' => 1,
                'show_filter' => true,
                'translations' => [
                    'en' => ['name' => 'Dirt', 'slug' => 'dirt'],
                    'sl' => ['name' => 'Zemlja', 'slug' => 'zemlja'],
                    'de' => ['name' => 'Erde', 'slug' => 'erde'],
                    'hr' => ['name' => 'Zemlja', 'slug' => 'zemlja'],
                    'fr' => ['name' => 'Terre', 'slug' => 'terre'],
                    'es' => ['name' => 'Tierra', 'slug' => 'tierra'],
                    'it' => ['name' => 'Terra', 'slug' => 'terra'],
                    'ua' => ['name' => 'Земля', 'slug' => 'zemlya'],
                    'ru' => ['name' => 'Земля', 'slug' => 'zemlya'],
                ],
            ],
            [
                'id' => 4,
                'code' => 'dirt_and_rocks',
                'status_id' => 1,
                'show_filter' => true,
                'translations' => [
                    'en' => ['name' => 'Dirt and Rocks', 'slug' => 'dirt-and-rocks'],
                    'sl' => ['name' => 'Zemlja in kamni', 'slug' => 'zemlja-in-kamni'],
                    'de' => ['name' => 'Erde und Steine', 'slug' => 'erde-und-steine'],
                    'hr' => ['name' => 'Zemlja i kamenje', 'slug' => 'zemlja-i-kamenje'],
                    'fr' => ['name' => 'Terre et rochers', 'slug' => 'terre-et-rochers'],
                    'es' => ['name' => 'Tierra y rocas', 'slug' => 'tierra-y-rocas'],
                    'it' => ['name' => 'Terra e rocce', 'slug' => 'terra-e-rocce'],
                    'ua' => ['name' => 'Земля і камені', 'slug' => 'zemlya-i-kameni'],
                    'ru' => ['name' => 'Земля и камни', 'slug' => 'zemlya-i-kamni'],
                ],
            ],
            [
                'id' => 5,
                'code' => 'no_parking',
                'status_id' => 1,
                'show_filter' => false,
                'translations' => [
                    'en' => ['name' => 'No Parking', 'slug' => 'no-parking'],
                    'sl' => ['name' => 'Brez parkirišča', 'slug' => 'brez-parkirisca'],
                    'de' => ['name' => 'Kein Parkplatz', 'slug' => 'kein-parkplatz'],
                    'hr' => ['name' => 'Bez parkinga', 'slug' => 'bez-parkinga'],
                    'fr' => ['name' => 'Pas de parking', 'slug' => 'pas-de-parking'],
                    'es' => ['name' => 'Sin estacionamiento', 'slug' => 'sin-estacionamiento'],
                    'it' => ['name' => 'Nessun parcheggio', 'slug' => 'nessun-parcheggio'],
                    'ua' => ['name' => 'Без парковки', 'slug' => 'bez-parkovky'],
                    'ru' => ['name' => 'Без парковки', 'slug' => 'bez-parkovki'],
                ],
            ],
        ];

        foreach ($roadToParkingTypes as $typeData) {
            // Insert road to parking type with explicit ID
            DB::table('road_to_parking_type')->insert([
                'id' => $typeData['id'],
                'code' => $typeData['code'],
                'status_id' => $typeData['status_id'],
                'show_filter' => $typeData['show_filter'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($typeData['translations'] as $locale => $translation) {
                DB::table('road_to_parking_type_translation')->insert([
                    'road_to_parking_type_id' => $typeData['id'],
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


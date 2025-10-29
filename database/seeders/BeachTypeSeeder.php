<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BeachType;
use App\Models\BeachTypeTranslation;

class BeachTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Order and IDs must match old database for migration compatibility:
        // 1 = sand, 2 = brown_sand, 3 = black_sand, 4 = small_stones,
        // 5 = big_stones, 6 = rock_beach, 7 = grass_beach, 8 = concrete
        $beachTypes = [
            [
                'id' => 1,
                'code' => 'sand',
                'status_id' => 1,
                'show_filter' => true, // show_filter = 1 in old DB
                'translations' => [
                    'en' => ['name' => 'Sand', 'slug' => 'sand-beaches'],
                    'sl' => ['name' => 'Pesek', 'slug' => 'peskene-plaze'],
                    'de' => ['name' => 'Sand', 'slug' => 'sandstrande'],
                    'hr' => ['name' => 'Pijesak', 'slug' => 'pjescane-plaze'],
                    'fr' => ['name' => 'Sable', 'slug' => 'plages-de-sable'],
                    'es' => ['name' => 'Arena', 'slug' => 'playas-de-arena'],
                    'it' => ['name' => 'Sabbia', 'slug' => 'spiagge-di-sabbia'],
                    'ua' => ['name' => 'Пісок', 'slug' => 'pischani-plyazhi'],
                    'ru' => ['name' => 'Песок', 'slug' => 'peschanye-plyazhi'],
                ],
            ],
            [
                'id' => 2,
                'code' => 'brown_sand',
                'status_id' => 1,
                'show_filter' => false, // show_filter = 0 in old DB
                'translations' => [
                    'en' => ['name' => 'Brown Sand', 'slug' => 'sand-beaches'],
                    'sl' => ['name' => 'Rjav Pesek', 'slug' => 'peskene-plaze'],
                    'de' => ['name' => 'Brauner Sand', 'slug' => 'sandstrande'],
                    'hr' => ['name' => 'Smeđi Pijesak', 'slug' => 'pjescane-plaze'],
                    'fr' => ['name' => 'Sable Brun', 'slug' => 'plages-de-sable'],
                    'es' => ['name' => 'Arena Marrón', 'slug' => 'playas-de-arena'],
                    'it' => ['name' => 'Sabbia Marrone', 'slug' => 'spiagge-di-sabbia'],
                    'ua' => ['name' => 'Коричневий Пісок', 'slug' => 'pischani-plyazhi'],
                    'ru' => ['name' => 'Коричневый Песок', 'slug' => 'peschanye-plyazhi'],
                ],
            ],
            [
                'id' => 3,
                'code' => 'black_sand',
                'status_id' => 1,
                'show_filter' => false, // show_filter = 0 in old DB
                'translations' => [
                    'en' => ['name' => 'Black Sand', 'slug' => 'sand-beaches'],
                    'sl' => ['name' => 'Črn Pesek', 'slug' => 'peskene-plaze'],
                    'de' => ['name' => 'Schwarzer Sand', 'slug' => 'sandstrande'],
                    'hr' => ['name' => 'Crni Pijesak', 'slug' => 'pjescane-plaze'],
                    'fr' => ['name' => 'Sable Noir', 'slug' => 'plages-de-sable'],
                    'es' => ['name' => 'Arena Negra', 'slug' => 'playas-de-arena'],
                    'it' => ['name' => 'Sabbia Nera', 'slug' => 'spiagge-di-sabbia'],
                    'ua' => ['name' => 'Чорний Пісок', 'slug' => 'pischani-plyazhi'],
                    'ru' => ['name' => 'Черный Песок', 'slug' => 'peschanye-plyazhi'],
                ],
            ],
            [
                'id' => 4,
                'code' => 'small_stones',
                'status_id' => 1,
                'show_filter' => true, // show_filter = 1 in old DB
                'translations' => [
                    'en' => ['name' => 'Small Stones', 'slug' => 'gravel-beaches'],
                    'sl' => ['name' => 'Drobno Kamenje', 'slug' => 'prodne-plaze'],
                    'de' => ['name' => 'Kleine Steine', 'slug' => 'kiesstrande'],
                    'hr' => ['name' => 'Sitno Kamenje', 'slug' => 'sljuncane-plaze'],
                    'fr' => ['name' => 'Petits Cailloux', 'slug' => 'plages-de-gravier'],
                    'es' => ['name' => 'Piedras Pequeñas', 'slug' => 'playas-de-grava'],
                    'it' => ['name' => 'Sassolini', 'slug' => 'spiagge-di-ghiaia'],
                    'ua' => ['name' => 'Дрібне Каміння', 'slug' => 'galkovі-plyazhi'],
                    'ru' => ['name' => 'Мелкая Галька', 'slug' => 'galechnye-plyazhi'],
                ],
            ],
            [
                'id' => 5,
                'code' => 'big_stones',
                'status_id' => 1,
                'show_filter' => true, // show_filter = 1 in old DB
                'translations' => [
                    'en' => ['name' => 'Big Stones', 'slug' => 'pebbles-beaches'],
                    'sl' => ['name' => 'Veliko Kamenje', 'slug' => 'kamnite-plaze'],
                    'de' => ['name' => 'Große Steine', 'slug' => 'kieselstrande'],
                    'hr' => ['name' => 'Krupno Kamenje', 'slug' => 'kamenite-plaze'],
                    'fr' => ['name' => 'Gros Galets', 'slug' => 'plages-de-galets'],
                    'es' => ['name' => 'Piedras Grandes', 'slug' => 'playas-de-guijarros'],
                    'it' => ['name' => 'Ciottoli Grandi', 'slug' => 'spiagge-di-ciottoli'],
                    'ua' => ['name' => 'Велике Каміння', 'slug' => 'galkovі-plyazhi'],
                    'ru' => ['name' => 'Крупная Галька', 'slug' => 'galechnye-plyazhi'],
                ],
            ],
            [
                'id' => 6,
                'code' => 'rock_beach',
                'status_id' => 1,
                'show_filter' => true, // show_filter = 1 in old DB
                'translations' => [
                    'en' => ['name' => 'Rock Beach', 'slug' => 'rocks-beaches'],
                    'sl' => ['name' => 'Skalnata Plaža', 'slug' => 'skalnate-plaze'],
                    'de' => ['name' => 'Felsenstrand', 'slug' => 'felsenstrande'],
                    'hr' => ['name' => 'Stjenovita Plaža', 'slug' => 'stjenovite-plaze'],
                    'fr' => ['name' => 'Plage Rocheuse', 'slug' => 'plages-rocheuses'],
                    'es' => ['name' => 'Playa Rocosa', 'slug' => 'playas-rocosas'],
                    'it' => ['name' => 'Spiaggia Rocciosa', 'slug' => 'spiagge-rocciose'],
                    'ua' => ['name' => 'Скелястий Пляж', 'slug' => 'skelyasti-plyazhi'],
                    'ru' => ['name' => 'Скалистый Пляж', 'slug' => 'skalistyy-plyazh'],
                ],
            ],
            [
                'id' => 7,
                'code' => 'grass_beach',
                'status_id' => 1,
                'show_filter' => true, // show_filter = 1 in old DB
                'translations' => [
                    'en' => ['name' => 'Grass Beach', 'slug' => 'grass-beaches'],
                    'sl' => ['name' => 'Travnata Plaža', 'slug' => 'travnate-plaze'],
                    'de' => ['name' => 'Grasstrand', 'slug' => 'grasstrande'],
                    'hr' => ['name' => 'Travnata Plaža', 'slug' => 'travnate-plaze'],
                    'fr' => ['name' => 'Plage Herbeuse', 'slug' => 'plages-herbeuses'],
                    'es' => ['name' => 'Playa de Hierba', 'slug' => 'playas-de-hierba'],
                    'it' => ['name' => 'Spiaggia Erbosa', 'slug' => 'spiagge-erbose'],
                    'ua' => ['name' => 'Трав\'яний Пляж', 'slug' => 'travyani-plyazhi'],
                    'ru' => ['name' => 'Травяной Пляж', 'slug' => 'travyanoy-plyazh'],
                ],
            ],
            [
                'id' => 8,
                'code' => 'concrete',
                'status_id' => 1,
                'show_filter' => true, // show_filter = 1 in old DB
                'translations' => [
                    'en' => ['name' => 'Concrete', 'slug' => 'concrete-beaches'],
                    'sl' => ['name' => 'Beton', 'slug' => 'betonske-plaze'],
                    'de' => ['name' => 'Beton', 'slug' => 'betonstrande'],
                    'hr' => ['name' => 'Beton', 'slug' => 'betonske-plaze'],
                    'fr' => ['name' => 'Béton', 'slug' => 'plages-de-beton'],
                    'es' => ['name' => 'Hormigón', 'slug' => 'playas-de-hormigon'],
                    'it' => ['name' => 'Cemento', 'slug' => 'spiagge-di-cemento'],
                    'ua' => ['name' => 'Бетон', 'slug' => 'betonni-plyazhi'],
                    'ru' => ['name' => 'Бетон', 'slug' => 'betonnye-plyazhi'],
                ],
            ],
        ];

        foreach ($beachTypes as $beachTypeData) {
            // Create the beach type with explicit ID
            $beachType = BeachType::create([
                'id' => $beachTypeData['id'],
                'code' => $beachTypeData['code'],
                'status_id' => $beachTypeData['status_id'],
                'show_filter' => $beachTypeData['show_filter'],
            ]);

            // Create translations for all supported languages
            foreach ($beachTypeData['translations'] as $locale => $translation) {
                BeachTypeTranslation::create([
                    'beach_type_id' => $beachType->id,
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                ]);
            }
        }

        $this->command->info('Beach types seeded successfully with all translations!');
    }
}


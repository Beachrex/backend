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
                    'pl' => ['name' => 'Piasek', 'slug' => 'plaze-piaskowe'],
                    'cs' => ['name' => 'Písek', 'slug' => 'piskove-plaze'],
                    'hu' => ['name' => 'Homok', 'slug' => 'homokos-strandok'],
                    'bg' => ['name' => 'Пясък', 'slug' => 'peschani-plajove'],
                    'sk' => ['name' => 'Piesok', 'slug' => 'piesocne-plaze'],

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
                    'pl' => ['name' => 'Brązowy piasek', 'slug' => 'plaze-brazowy-piasek'],
                    'cs' => ['name' => 'Hnědý písek', 'slug' => 'hnede-piskove-plaze'],
                    'hu' => ['name' => 'Barna homok', 'slug' => 'barna-homokos-strandok'],
                    'bg' => ['name' => 'Кафяв пясък', 'slug' => 'kafjavi-peschani-plajove'],
                    'sk' => ['name' => 'Hnedý piesok', 'slug' => 'hnede-piesocne-plaze'],

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
                    'pl' => ['name' => 'Czarny piasek', 'slug' => 'plaze-czarny-piasek'],
                    'cs' => ['name' => 'Černý písek', 'slug' => 'cerne-piskove-plaze'],
                    'hu' => ['name' => 'Fekete homok', 'slug' => 'fekete-homokos-strandok'],
                    'bg' => ['name' => 'Черен пясък', 'slug' => 'cherni-peschani-plajove'],
                    'sk' => ['name' => 'Čierny piesok', 'slug' => 'cierne-piesocne-plaze'],

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
                    'pl' => ['name' => 'Małe kamienie', 'slug' => 'plaze-male-kamienie'],
                    'cs' => ['name' => 'Malé kameny', 'slug' => 'male-kamenite-plaze'],
                    'hu' => ['name' => 'Apró kövek', 'slug' => 'apro-koves-strandok'],
                    'bg' => ['name' => 'Малки камъни', 'slug' => 'malki-kamenisti-plajove'],
                    'sk' => ['name' => 'Malé kamene', 'slug' => 'male-kameniste-plaze'],

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
                    'pl' => ['name' => 'Duże kamienie', 'slug' => 'plaze-duze-kamienie'],
                    'cs' => ['name' => 'Velké kameny', 'slug' => 'velke-kamenite-plaze'],
                    'hu' => ['name' => 'Nagy kövek', 'slug' => 'nagy-koves-strandok'],
                    'bg' => ['name' => 'Големи камъни', 'slug' => 'golemi-kamenisti-plajove'],
                    'sk' => ['name' => 'Veľké kamene', 'slug' => 'velke-kameniste-plaze'],

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
                    'pl' => ['name' => 'Plaża skalna', 'slug' => 'plaze-skalne'],
                    'cs' => ['name' => 'Skalnatá pláž', 'slug' => 'skalnate-plaze'],
                    'hu' => ['name' => 'Sziklás strand', 'slug' => 'sziklas-strandok'],
                    'bg' => ['name' => 'Скалист плаж', 'slug' => 'skalisti-plajove'],
                    'sk' => ['name' => 'Skalnatá pláž', 'slug' => 'skalnate-plaze'],

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
                    'pl' => ['name' => 'Plaża trawiasta', 'slug' => 'plaze-trawiaste'],
                    'cs' => ['name' => 'Travnatá pláž', 'slug' => 'travnate-plaze'],
                    'hu' => ['name' => 'Füves strand', 'slug' => 'fuves-strandok'],
                    'bg' => ['name' => 'Тревист плаж', 'slug' => 'trevisti-plajove'],
                    'sk' => ['name' => 'Trávnatá pláž', 'slug' => 'travnate-plaze'],

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
                    'pl' => ['name' => 'Beton', 'slug' => 'plaze-betonowe'],
                    'cs' => ['name' => 'Beton', 'slug' => 'betonove-plaze'],
                    'hu' => ['name' => 'Beton', 'slug' => 'beton-strandok'],
                    'bg' => ['name' => 'Бетон', 'slug' => 'betonni-plajove'],
                    'sk' => ['name' => 'Betón', 'slug' => 'betonove-plaze'],

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

        $this->command->info('Beach types seeded successfully with translations in 14 languages!');
    }
}


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
        if (BeachType::count() > 0) {
            $this->command->info('Beach type table already seeded. Skipping.');
            return;
        }

        // Filter titles for all locales
        $filterTitles = [
            'en' => 'Beach Type',
            'sl' => 'Tip plaže',
            'de' => 'Strandtyp',
            'hr' => 'Tip plaže',
            'fr' => 'Type de plage',
            'es' => 'Tipo de playa',
            'it' => 'Tipo di spiaggia',
            'ua' => 'Тип пляжу',
            'ru' => 'Тип пляжа',
            'pl' => 'Typ plaży',
            'cs' => 'Typ pláže',
            'hu' => 'Strand típusa',
            'bg' => 'Тип плаж',
            'sk' => 'Typ pláže',
            'da' => 'Strandtype',
            'pt' => 'Tipo de praia',
            'nl' => 'Strandtype',
            'el' => 'Τύπος παραλίας',
            'sq' => 'Tipi i plazhit',
        ];

        // Order and IDs must match old database for migration compatibility:
        // 1 = sand, 2 = brown_sand, 3 = black_sand, 4 = small_stones,
        // 5 = big_stones, 6 = rock_beach, 7 = grass_beach, 8 = concrete
        $beachTypes = [
            [
                'id' => 1,
                'code' => 'sand',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/beach_type.svg',
                'filter_order' => 1,
                'input_type' => 'checkbox',
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
                    'da' => ['name' => 'Sand', 'slug' => 'sandstrande'],
                    'pt' => ['name' => 'Areia', 'slug' => 'praias-de-areia'],
                    'nl' => ['name' => 'Zand', 'slug' => 'zandstranden'],
                    'el' => ['name' => 'Άμμος', 'slug' => 'ammodeis-paralies'],
                    'sq' => ['name' => 'Rërë', 'slug' => 'plazhet-me-rere'],

                ],
            ],
            [
                'id' => 2,
                'code' => 'brown_sand',
                'status_id' => 1,
                'show_filter' => false,
                'icon' => '/icons/filters/beach_type.svg',
                'filter_order' => 2,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Brown sand', 'slug' => 'sand-beaches'],
                    'sl' => ['name' => 'Rjav pesek', 'slug' => 'peskene-plaze'],
                    'de' => ['name' => 'Brauner Sand', 'slug' => 'sandstrande'],
                    'hr' => ['name' => 'Smeđi pijesak', 'slug' => 'pjescane-plaze'],
                    'fr' => ['name' => 'Sable brun', 'slug' => 'plages-de-sable'],
                    'es' => ['name' => 'Arena marrón', 'slug' => 'playas-de-arena'],
                    'it' => ['name' => 'Sabbia marrone', 'slug' => 'spiagge-di-sabbia'],
                    'ua' => ['name' => 'Коричневий пісок', 'slug' => 'pischani-plyazhi'],
                    'ru' => ['name' => 'Коричневый песок', 'slug' => 'peschanye-plyazhi'],
                    'pl' => ['name' => 'Brązowy piasek', 'slug' => 'plaze-brazowy-piasek'],
                    'cs' => ['name' => 'Hnědý písek', 'slug' => 'hnede-piskove-plaze'],
                    'hu' => ['name' => 'Barna homok', 'slug' => 'barna-homokos-strandok'],
                    'bg' => ['name' => 'Кафяв пясък', 'slug' => 'kafjavi-peschani-plajove'],
                    'sk' => ['name' => 'Hnedý piesok', 'slug' => 'hnede-piesocne-plaze'],
                    'da' => ['name' => 'Brunt sand', 'slug' => 'sandstrande'],
                    'pt' => ['name' => 'Areia castanha', 'slug' => 'praias-de-areia'],
                    'nl' => ['name' => 'Bruin zand', 'slug' => 'zandstranden'],
                    'el' => ['name' => 'Καφέ άμμος', 'slug' => 'ammodeis-paralies'],
                    'sq' => ['name' => 'Rërë kafe', 'slug' => 'plazhet-me-rere'],

                ],
            ],
            [
                'id' => 3,
                'code' => 'black_sand',
                'status_id' => 1,
                'show_filter' => false,
                'icon' => '/icons/filters/beach_type.svg',
                'filter_order' => 3,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Black sand', 'slug' => 'sand-beaches'],
                    'sl' => ['name' => 'Črn pesek', 'slug' => 'peskene-plaze'],
                    'de' => ['name' => 'Schwarzer Sand', 'slug' => 'sandstrande'],
                    'hr' => ['name' => 'Crni pijesak', 'slug' => 'pjescane-plaze'],
                    'fr' => ['name' => 'Sable noir', 'slug' => 'plages-de-sable'],
                    'es' => ['name' => 'Arena negra', 'slug' => 'playas-de-arena'],
                    'it' => ['name' => 'Sabbia nera', 'slug' => 'spiagge-di-sabbia'],
                    'ua' => ['name' => 'Чорний пісок', 'slug' => 'pischani-plyazhi'],
                    'ru' => ['name' => 'Черный песок', 'slug' => 'peschanye-plyazhi'],
                    'pl' => ['name' => 'Czarny piasek', 'slug' => 'plaze-czarny-piasek'],
                    'cs' => ['name' => 'Černý písek', 'slug' => 'cerne-piskove-plaze'],
                    'hu' => ['name' => 'Fekete homok', 'slug' => 'fekete-homokos-strandok'],
                    'bg' => ['name' => 'Черен пясък', 'slug' => 'cherni-peschani-plajove'],
                    'sk' => ['name' => 'Čierny piesok', 'slug' => 'cierne-piesocne-plaze'],
                    'da' => ['name' => 'Sort sand', 'slug' => 'sandstrande'],
                    'pt' => ['name' => 'Areia preta', 'slug' => 'praias-de-areia'],
                    'nl' => ['name' => 'Zwart zand', 'slug' => 'zandstranden'],
                    'el' => ['name' => 'Μαύρη άμμος', 'slug' => 'ammodeis-paralies'],
                    'sq' => ['name' => 'Rërë e zi', 'slug' => 'plazhet-me-rere'],

                ],
            ],
            [
                'id' => 4,
                'code' => 'small_stones',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/beach_type.svg',
                'filter_order' => 4,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Small stones', 'slug' => 'gravel-beaches'],
                    'sl' => ['name' => 'Drobno kamenje', 'slug' => 'prodne-plaze'],
                    'de' => ['name' => 'Kleine Steine', 'slug' => 'kiesstrande'],
                    'hr' => ['name' => 'Sitno kamenje', 'slug' => 'sljuncane-plaze'],
                    'fr' => ['name' => 'Petits cailloux', 'slug' => 'plages-de-gravier'],
                    'es' => ['name' => 'Piedras pequeñas', 'slug' => 'playas-de-grava'],
                    'it' => ['name' => 'Sassolini', 'slug' => 'spiagge-di-ghiaia'],
                    'ua' => ['name' => 'Дрібне каміння', 'slug' => 'galkovі-plyazhi'],
                    'ru' => ['name' => 'Мелкая галька', 'slug' => 'galechnye-plyazhi'],
                    'pl' => ['name' => 'Małe kamienie', 'slug' => 'plaze-male-kamienie'],
                    'cs' => ['name' => 'Malé kameny', 'slug' => 'male-kamenite-plaze'],
                    'hu' => ['name' => 'Apró kövek', 'slug' => 'apro-koves-strandok'],
                    'bg' => ['name' => 'Малки камъни', 'slug' => 'malki-kamenisti-plajove'],
                    'sk' => ['name' => 'Malé kamene', 'slug' => 'male-kameniste-plaze'],
                    'da' => ['name' => 'Små sten', 'slug' => 'sma-stenstrande'],
                    'pt' => ['name' => 'Pedrinhas', 'slug' => 'praias-de-cascalho'],
                    'nl' => ['name' => 'Kleine stenen', 'slug' => 'grindstranden'],
                    'el' => ['name' => 'Μικρά λιθάρια', 'slug' => 'paralies-me-chalika'],
                    'sq' => ['name' => 'Gurë të vegjël', 'slug' => 'plazhet-me-zhavorr'],

                ],
            ],
            [
                'id' => 5,
                'code' => 'big_stones',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/beach_type.svg',
                'filter_order' => 5,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Big stones', 'slug' => 'pebbles-beaches'],
                    'sl' => ['name' => 'Veliko kamenje', 'slug' => 'kamnite-plaze'],
                    'de' => ['name' => 'Große Steine', 'slug' => 'kieselstrande'],
                    'hr' => ['name' => 'Krupno kamenje', 'slug' => 'kamenite-plaze'],
                    'fr' => ['name' => 'Gros galets', 'slug' => 'plages-de-galets'],
                    'es' => ['name' => 'Piedras grandes', 'slug' => 'playas-de-guijarros'],
                    'it' => ['name' => 'Ciottoli grandi', 'slug' => 'spiagge-di-ciottoli'],
                    'ua' => ['name' => 'Велике каміння', 'slug' => 'galkovі-plyazhi'],
                    'ru' => ['name' => 'Крупная галька', 'slug' => 'galechnye-plyazhi'],
                    'pl' => ['name' => 'Duże kamienie', 'slug' => 'plaze-duze-kamienie'],
                    'cs' => ['name' => 'Velké kameny', 'slug' => 'velke-kamenite-plaze'],
                    'hu' => ['name' => 'Nagy kövek', 'slug' => 'nagy-koves-strandok'],
                    'bg' => ['name' => 'Големи камъни', 'slug' => 'golemi-kamenisti-plajove'],
                    'sk' => ['name' => 'Veľké kamene', 'slug' => 'velke-kameniste-plaze'],
                    'da' => ['name' => 'Store sten', 'slug' => 'store-stenstrande'],
                    'pt' => ['name' => 'Seixos', 'slug' => 'praias-de-seixos'],
                    'nl' => ['name' => 'Grote stenen', 'slug' => 'kiezelstranden'],
                    'el' => ['name' => 'Μεγάλα λιθάρια', 'slug' => 'paralies-me-votsala'],
                    'sq' => ['name' => 'Gurë të mëdhenj', 'slug' => 'plazhet-me-gure'],

                ],
            ],
            [
                'id' => 6,
                'code' => 'rock_beach',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/beach_type.svg',
                'filter_order' => 6,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Rock beach', 'slug' => 'rocks-beaches'],
                    'sl' => ['name' => 'Skalnata plaža', 'slug' => 'skalnate-plaze'],
                    'de' => ['name' => 'Felsenstrand', 'slug' => 'felsenstrande'],
                    'hr' => ['name' => 'Stjenovita plaža', 'slug' => 'stjenovite-plaze'],
                    'fr' => ['name' => 'Plage rocheuse', 'slug' => 'plages-rocheuses'],
                    'es' => ['name' => 'Playa rocosa', 'slug' => 'playas-rocosas'],
                    'it' => ['name' => 'Spiaggia rocciosa', 'slug' => 'spiagge-rocciose'],
                    'ua' => ['name' => 'Скелястий пляж', 'slug' => 'skelyasti-plyazhi'],
                    'ru' => ['name' => 'Скалистый пляж', 'slug' => 'skalistyy-plyazh'],
                    'pl' => ['name' => 'Plaża skalna', 'slug' => 'plaze-skalne'],
                    'cs' => ['name' => 'Skalnatá pláž', 'slug' => 'skalnate-plaze'],
                    'hu' => ['name' => 'Sziklás strand', 'slug' => 'sziklas-strandok'],
                    'bg' => ['name' => 'Скалист плаж', 'slug' => 'skalisti-plajove'],
                    'sk' => ['name' => 'Skalnatá pláž', 'slug' => 'skalnate-plaze'],
                    'da' => ['name' => 'Klippestrand', 'slug' => 'klippestrande'],
                    'pt' => ['name' => 'Praia rochosa', 'slug' => 'praias-rochosas'],
                    'nl' => ['name' => 'Rotsstrand', 'slug' => 'rotsstranden'],
                    'el' => ['name' => 'Βραχώδης παραλία', 'slug' => 'vrachodes-paralies'],
                    'sq' => ['name' => 'Plazh shkëmbor', 'slug' => 'plazhet-shkembore'],

                ],
            ],
            [
                'id' => 7,
                'code' => 'grass_beach',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/beach_type.svg',
                'filter_order' => 7,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Grass beach', 'slug' => 'grass-beaches'],
                    'sl' => ['name' => 'Travnata plaža', 'slug' => 'travnate-plaze'],
                    'de' => ['name' => 'Grasstrand', 'slug' => 'grasstrande'],
                    'hr' => ['name' => 'Travnata plaža', 'slug' => 'travnate-plaze'],
                    'fr' => ['name' => 'Plage herbeuse', 'slug' => 'plages-herbeuses'],
                    'es' => ['name' => 'Playa de hierba', 'slug' => 'playas-de-hierba'],
                    'it' => ['name' => 'Spiaggia erbosa', 'slug' => 'spiagge-erbose'],
                    'ua' => ['name' => 'Трав\'яний пляж', 'slug' => 'travyani-plyazhi'],
                    'ru' => ['name' => 'Травяной пляж', 'slug' => 'travyanoy-plyazh'],
                    'pl' => ['name' => 'Plaża trawiasta', 'slug' => 'plaze-trawiaste'],
                    'cs' => ['name' => 'Travnatá pláž', 'slug' => 'travnate-plaze'],
                    'hu' => ['name' => 'Füves strand', 'slug' => 'fuves-strandok'],
                    'bg' => ['name' => 'Тревист плаж', 'slug' => 'trevisti-plajove'],
                    'sk' => ['name' => 'Trávnatá pláž', 'slug' => 'travnate-plaze'],
                    'da' => ['name' => 'Græsstrand', 'slug' => 'grasstrande'],
                    'pt' => ['name' => 'Praia de relva', 'slug' => 'praias-de-relva'],
                    'nl' => ['name' => 'Grasstrand', 'slug' => 'grasstranden'],
                    'el' => ['name' => 'Χλοώδης παραλία', 'slug' => 'chloodeis-paralies'],
                    'sq' => ['name' => 'Plazh me bar', 'slug' => 'plazhet-me-bar'],

                ],
            ],
            [
                'id' => 8,
                'code' => 'concrete',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/beach_type.svg',
                'filter_order' => 8,
                'input_type' => 'checkbox',
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
                    'da' => ['name' => 'Beton', 'slug' => 'betonstrande'],
                    'pt' => ['name' => 'Betão', 'slug' => 'praias-de-betao'],
                    'nl' => ['name' => 'Beton', 'slug' => 'betonstranden'],
                    'el' => ['name' => 'Μπετόν', 'slug' => 'paralies-apo-mpeton'],
                    'sq' => ['name' => 'Beton', 'slug' => 'plazhet-me-beton'],

                ],
            ],
        ];

        foreach ($beachTypes as $beachTypeData) {
            // Create or update the beach type with explicit ID
            $beachType = BeachType::updateOrCreate(
                ['id' => $beachTypeData['id']],
                [
                    'code' => $beachTypeData['code'],
                    'status_id' => $beachTypeData['status_id'],
                    'show_filter' => $beachTypeData['show_filter'],
                    'icon' => $beachTypeData['icon'],
                    'filter_order' => $beachTypeData['filter_order'],
                    'input_type' => $beachTypeData['input_type'],
                ]
            );

            // Create or update translations for all supported languages
            foreach ($beachTypeData['translations'] as $locale => $translation) {
                BeachTypeTranslation::updateOrCreate(
                    [
                        'beach_type_id' => $beachType->id,
                        'locale' => $locale,
                    ],
                    [
                        'name' => $translation['name'],
                        'slug' => $translation['slug'],
                        'filter_title' => $filterTitles[$locale] ?? $filterTitles['en'],
                    ]
                );
            }
        }

        $this->command->info('Beach types seeded successfully with translations in 14 languages!');
    }
}


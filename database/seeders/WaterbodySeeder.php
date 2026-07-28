<?php

namespace Database\Seeders;

use App\Models\Waterbody;
use App\Models\WaterbodyTranslation;
use Illuminate\Database\Seeder;

class WaterbodySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Waterbody::count() > 0) {
            $this->command->info('Waterbody table already seeded. Skipping.');
            return;
        }

        // Filter titles for all locales
        $filterTitles = [
            'en' => 'Water Body',
            'sl' => 'Vodna površina',
            'de' => 'Gewässer',
            'hr' => 'Vodeno tijelo',
            'fr' => 'Plan d\'eau',
            'es' => 'Cuerpo de agua',
            'it' => 'Corpo idrico',
            'ua' => 'Водойма',
            'ru' => 'Водоём',
            'pl' => 'Zbiornik wodny',
            'cs' => 'Vodní plocha',
            'hu' => 'Víztest',
            'bg' => 'Воден обект',
            'sk' => 'Vodná plocha',
            'da' => 'Vandlegeme',
            'pt' => 'Corpo de água',
            'nl' => 'Waterlichaam',
            'el' => 'Σώμα νερού',
            'sq' => 'Trup ujor',
        ];

        // Order and IDs must match old database for migration compatibility:
        // 1 = river, 2 = pool, 3 = sea, 4 = lake
        $waterbodies = [
            [
                'id' => 1,
                'code' => 'river',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/waterbody.svg',
                'filter_order' => 3,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'River', 'slug' => 'river'],
                    'sl' => ['name' => 'Reka', 'slug' => 'reka'],
                    'de' => ['name' => 'Fluss', 'slug' => 'fluss'],
                    'hr' => ['name' => 'Rijeka', 'slug' => 'rijeka'],
                    'fr' => ['name' => 'Rivière', 'slug' => 'riviere'],
                    'es' => ['name' => 'Río', 'slug' => 'rio'],
                    'it' => ['name' => 'Fiume', 'slug' => 'fiume'],
                    'ua' => ['name' => 'Річка', 'slug' => 'richka'],
                    'ru' => ['name' => 'Река', 'slug' => 'reka'],
                    'pl' => ['name' => 'Rzeka', 'slug' => 'rzeka'],
                    'cs' => ['name' => 'Řeka', 'slug' => 'reka'],
                    'hu' => ['name' => 'Folyó', 'slug' => 'folyo'],
                    'bg' => ['name' => 'Река', 'slug' => 'reka'],
                    'sk' => ['name' => 'Rieka', 'slug' => 'rieka'],
                    'da' => ['name' => 'Flod', 'slug' => 'flod'],
                    'pt' => ['name' => 'Rio', 'slug' => 'rio'],
                    'nl' => ['name' => 'Rivier', 'slug' => 'rivier'],
                    'el' => ['name' => 'Ποταμός', 'slug' => 'potamos'],
                    'sq' => ['name' => 'Lumë', 'slug' => 'lume'],
                ],
            ],
            [
                'id' => 2,
                'code' => 'pool',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/waterbody.svg',
                'filter_order' => 4,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Pool', 'slug' => 'pool'],
                    'sl' => ['name' => 'Bazen', 'slug' => 'bazen'],
                    'de' => ['name' => 'Schwimmbad', 'slug' => 'schwimmbad'],
                    'hr' => ['name' => 'Bazen', 'slug' => 'bazen'],
                    'fr' => ['name' => 'Piscine', 'slug' => 'piscine'],
                    'es' => ['name' => 'Piscina', 'slug' => 'piscina'],
                    'it' => ['name' => 'Piscina', 'slug' => 'piscina'],
                    'ua' => ['name' => 'Басейн', 'slug' => 'baseyn'],
                    'ru' => ['name' => 'Бассейн', 'slug' => 'basseyn'],
                    'pl' => ['name' => 'Basen', 'slug' => 'basen'],
                    'cs' => ['name' => 'Bazén', 'slug' => 'bazen'],
                    'hu' => ['name' => 'Medence', 'slug' => 'medence'],
                    'bg' => ['name' => 'Басейн', 'slug' => 'baseyn'],
                    'sk' => ['name' => 'Bazén', 'slug' => 'bazen'],
                    'da' => ['name' => 'Pool', 'slug' => 'pool'],
                    'pt' => ['name' => 'Piscina', 'slug' => 'piscina'],
                    'nl' => ['name' => 'Zwembad', 'slug' => 'zwembad'],
                    'el' => ['name' => 'Πισίνα', 'slug' => 'pisina'],
                    'sq' => ['name' => 'Pishinë', 'slug' => 'pishine'],
                ],
            ],
            [
                'id' => 3,
                'code' => 'sea',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/waterbody.svg',
                'filter_order' => 1,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Sea', 'slug' => 'sea'],
                    'sl' => ['name' => 'Morje', 'slug' => 'morje'],
                    'de' => ['name' => 'Meer', 'slug' => 'meer'],
                    'hr' => ['name' => 'More', 'slug' => 'more'],
                    'fr' => ['name' => 'Mer', 'slug' => 'mer'],
                    'es' => ['name' => 'Mar', 'slug' => 'mar'],
                    'it' => ['name' => 'Mare', 'slug' => 'mare'],
                    'ua' => ['name' => 'Море', 'slug' => 'more'],
                    'ru' => ['name' => 'Море', 'slug' => 'more'],
                    'pl' => ['name' => 'Morze', 'slug' => 'morze'],
                    'cs' => ['name' => 'Moře', 'slug' => 'more'],
                    'hu' => ['name' => 'Tenger', 'slug' => 'tenger'],
                    'bg' => ['name' => 'Море', 'slug' => 'more'],
                    'sk' => ['name' => 'More', 'slug' => 'more'],
                    'da' => ['name' => 'Hav', 'slug' => 'hav'],
                    'pt' => ['name' => 'Mar', 'slug' => 'mar'],
                    'nl' => ['name' => 'Zee', 'slug' => 'zee'],
                    'el' => ['name' => 'Θάλασσα', 'slug' => 'thalassa'],
                    'sq' => ['name' => 'Det', 'slug' => 'det'],
                ],
            ],
            [
                'id' => 4,
                'code' => 'lake',
                'status_id' => 1,
                'show_filter' => true,
                'icon' => '/icons/filters/waterbody.svg',
                'filter_order' => 2,
                'input_type' => 'checkbox',
                'translations' => [
                    'en' => ['name' => 'Lake', 'slug' => 'lake'],
                    'sl' => ['name' => 'Jezero', 'slug' => 'jezero'],
                    'de' => ['name' => 'See', 'slug' => 'see'],
                    'hr' => ['name' => 'Jezero', 'slug' => 'jezero'],
                    'fr' => ['name' => 'Lac', 'slug' => 'lac'],
                    'es' => ['name' => 'Lago', 'slug' => 'lago'],
                    'it' => ['name' => 'Lago', 'slug' => 'lago'],
                    'ua' => ['name' => 'Озеро', 'slug' => 'ozero'],
                    'ru' => ['name' => 'Озеро', 'slug' => 'ozero'],
                    'pl' => ['name' => 'Jezioro', 'slug' => 'jezioro'],
                    'cs' => ['name' => 'Jezero', 'slug' => 'jezero'],
                    'hu' => ['name' => 'Tó', 'slug' => 'to'],
                    'bg' => ['name' => 'Езеро', 'slug' => 'ezero'],
                    'sk' => ['name' => 'Jazero', 'slug' => 'jazero'],
                    'da' => ['name' => 'Sø', 'slug' => 'so'],
                    'pt' => ['name' => 'Lago', 'slug' => 'lago'],
                    'nl' => ['name' => 'Meer', 'slug' => 'meer'],
                    'el' => ['name' => 'Λίμνη', 'slug' => 'limni'],
                    'sq' => ['name' => 'Liqen', 'slug' => 'liqen'],
                ],
            ],
        ];

        foreach ($waterbodies as $waterbodyData) {
            // Create or update the waterbody with explicit ID
            $waterbody = Waterbody::updateOrCreate(
                ['id' => $waterbodyData['id']],
                [
                    'code' => $waterbodyData['code'],
                    'status_id' => $waterbodyData['status_id'],
                    'show_filter' => $waterbodyData['show_filter'],
                    'icon' => $waterbodyData['icon'],
                    'filter_order' => $waterbodyData['filter_order'],
                    'input_type' => $waterbodyData['input_type'],
                ]
            );

            // Create or update translations
            foreach ($waterbodyData['translations'] as $locale => $translation) {
                WaterbodyTranslation::updateOrCreate(
                    [
                        'waterbody_id' => $waterbody->id,
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

        $this->command->info('Waterbodies seeded successfully with translations in 14 languages.');
    }
}


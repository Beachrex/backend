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
        $waterbodies = [
            [
                'code' => 'sea',
                'status_id' => 1,
                'show_filter' => true,
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
                ],
            ],
            [
                'code' => 'lake',
                'status_id' => 1,
                'show_filter' => true,
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
                ],
            ],
            [
                'code' => 'river',
                'status_id' => 1,
                'show_filter' => true,
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
                ],
            ],
            [
                'code' => 'pool',
                'status_id' => 1,
                'show_filter' => false, // Don't show pool in filters by default
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
                ],
            ],
        ];

        foreach ($waterbodies as $waterbodyData) {
            // Create the waterbody
            $waterbody = Waterbody::create([
                'code' => $waterbodyData['code'],
                'status_id' => $waterbodyData['status_id'],
                'show_filter' => $waterbodyData['show_filter'],
            ]);

            // Create translations
            foreach ($waterbodyData['translations'] as $locale => $translation) {
                WaterbodyTranslation::create([
                    'waterbody_id' => $waterbody->id,
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                ]);
            }
        }

        $this->command->info('Waterbodies seeded successfully with translations in 9 languages.');
    }
}


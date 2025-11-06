<?php

namespace Database\Seeders;

use App\Models\Beach;
use App\Models\BeachTranslation;
use Illuminate\Database\Seeder;

class DemoBeachSeeder extends Seeder
{
    public function run(): void
    {
        if (Beach::count() > 0) {
            $this->command->info('Beach table already seeded. Skipping.');
            return;
        }

        $beaches = [
            // Europe
            [
                'user_id' => 1,
                'name' => 'Portorož Beach',
                'latitude' => 45.5133,
                'longitude' => 13.5900,
                'status_id' => 1,
                'continent_code' => 'EU',
                'country_code' => 'SI',
                'translations' => [
                    'en' => [
                        'name' => 'Portorož Beach',
                        'description' => 'Beautiful Slovenian coastal beach with crystal clear waters.',
                        'slug' => 'portoroz-beach',
                    ],
                    'sl' => [
                        'name' => 'Plaža Portorož',
                        'description' => 'Čudovita slovenska obalna plaža s kristalno čisto vodo.',
                        'slug' => 'plaza-portoroz',
                    ]
                ]
            ],
            [
                'user_id' => 1,
                'name' => 'Costa del Sol',
                'latitude' => 36.5201,
                'longitude' => -4.8824,
                'status_id' => 1,
                'continent_code' => 'EU',
                'country_code' => 'ES',
                'translations' => [
                    'en' => [
                        'name' => 'Costa del Sol',
                        'description' => 'Famous Spanish beach destination with golden sand.',
                        'slug' => 'costa-del-sol',
                    ]
                ]
            ],

            // North America
            [
                'user_id' => 1,
                'name' => 'Miami Beach',
                'latitude' => 25.7617,
                'longitude' => -80.1918,
                'status_id' => 1,
                'continent_code' => 'NA',
                'country_code' => 'US',
                'translations' => [
                    'en' => [
                        'name' => 'Miami Beach',
                        'description' => 'Iconic American beach with vibrant nightlife and art deco architecture.',
                        'slug' => 'miami-beach',
                    ]
                ]
            ],
            [
                'user_id' => 1,
                'name' => 'Waikiki Beach',
                'latitude' => 21.2793,
                'longitude' => -157.8311,
                'status_id' => 1,
                'continent_code' => 'NA',
                'country_code' => 'US',
                'translations' => [
                    'en' => [
                        'name' => 'Waikiki Beach',
                        'description' => 'World-famous Hawaiian beach perfect for surfing and relaxation.',
                        'slug' => 'waikiki-beach',
                    ]
                ]
            ],

            // Asia
            [
                'user_id' => 1,
                'name' => 'Boracay White Beach',
                'latitude' => 11.9674,
                'longitude' => 121.9248,
                'status_id' => 1,
                'continent_code' => 'AS',
                'country_code' => 'PH',
                'translations' => [
                    'en' => [
                        'name' => 'Boracay White Beach',
                        'description' => 'Pristine white sand beach in the Philippines, perfect for tropical getaways.',
                        'slug' => 'boracay-white-beach',
                    ]
                ]
            ],

            // South America
            [
                'user_id' => 1,
                'name' => 'Copacabana Beach',
                'latitude' => -22.9711,
                'longitude' => -43.1822,
                'status_id' => 1,
                'continent_code' => 'SA',
                'country_code' => 'BR',
                'translations' => [
                    'en' => [
                        'name' => 'Copacabana Beach',
                        'description' => 'Legendary Brazilian beach in Rio de Janeiro, famous for its carnival atmosphere.',
                        'slug' => 'copacabana-beach',
                    ]
                ]
            ],

            // Oceania
            [
                'user_id' => 1,
                'name' => 'Bondi Beach',
                'latitude' => -33.8915,
                'longitude' => 151.2767,
                'status_id' => 1,
                'continent_code' => 'OC',
                'country_code' => 'AU',
                'translations' => [
                    'en' => [
                        'name' => 'Bondi Beach',
                        'description' => 'Iconic Australian beach near Sydney, popular with surfers and tourists.',
                        'slug' => 'bondi-beach',
                    ]
                ]
            ],
        ];

        foreach ($beaches as $beachData) {
            $translations = $beachData['translations'];
            unset($beachData['translations']);

            $beach = Beach::create($beachData);

            foreach ($translations as $locale => $translation) {
                BeachTranslation::create([
                    'beach_id' => $beach->id,
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'description' => $translation['description'],
                    'slug' => $translation['slug'],
                ]);
            }
        }

        $this->command->info('Created ' . count($beaches) . ' demo beaches across different continents and countries.');
    }
}

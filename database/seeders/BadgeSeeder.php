<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\BadgeTranslation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 24 badges total (6 for each entity type)
        $badges = [
            // Country badges (6)
            [
                'code' => 'country-unesco-heritage',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'UNESCO Heritage', 'slug' => 'unesco-heritage'],
                    'hr' => ['name' => 'UNESCO baština', 'slug' => 'unesco-bastina'],
                    'sl' => ['name' => 'UNESCO dediščina', 'slug' => 'unesco-dediscina'],
                ],
            ],
            [
                'code' => 'country-olive-oil',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Olive Oil Production', 'slug' => 'olive-oil-production'],
                    'hr' => ['name' => 'Proizvodnja maslinovog ulja', 'slug' => 'proizvodnja-maslinovog-ulja'],
                    'sl' => ['name' => 'Proizvodnja oljčnega olja', 'slug' => 'proizvodnja-oljcnega-olja'],
                ],
            ],
            [
                'code' => 'country-wine-region',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Wine Region', 'slug' => 'wine-region'],
                    'hr' => ['name' => 'Vinarska regija', 'slug' => 'vinarska-regija'],
                    'sl' => ['name' => 'Vinska regija', 'slug' => 'vinska-regija'],
                ],
            ],
            [
                'code' => 'country-coastal-tourism',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Coastal Tourism', 'slug' => 'coastal-tourism'],
                    'hr' => ['name' => 'Obalni turizam', 'slug' => 'obalni-turizam'],
                    'sl' => ['name' => 'Obalni turizem', 'slug' => 'obalni-turizem'],
                ],
            ],
            [
                'code' => 'country-traditional-cuisine',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Traditional Cuisine', 'slug' => 'traditional-cuisine'],
                    'hr' => ['name' => 'Tradicionalna kuhinja', 'slug' => 'tradicionalna-kuhinja'],
                    'sl' => ['name' => 'Tradicionalna kuhinja', 'slug' => 'tradicionalna-kuhinja'],
                ],
            ],
            [
                'code' => 'country-blue-flag',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Blue Flag Beaches', 'slug' => 'blue-flag-beaches'],
                    'hr' => ['name' => 'Plaže s plavom zastavom', 'slug' => 'plaze-s-plavom-zastavom'],
                    'sl' => ['name' => 'Plaže z modro zastavo', 'slug' => 'plaze-z-modro-zastavo'],
                ],
            ],

            // Region badges (6)
            [
                'code' => 'region-local-festival',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Local Festival', 'slug' => 'local-festival'],
                    'hr' => ['name' => 'Lokalni festival', 'slug' => 'lokalni-festival'],
                    'sl' => ['name' => 'Lokalni festival', 'slug' => 'lokalni-festival'],
                ],
            ],
            [
                'code' => 'region-historic-sites',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Historic Sites', 'slug' => 'historic-sites'],
                    'hr' => ['name' => 'Povijesna mjesta', 'slug' => 'povijesna-mjesta'],
                    'sl' => ['name' => 'Zgodovinska mesta', 'slug' => 'zgodovinska-mesta'],
                ],
            ],
            [
                'code' => 'region-natural-park',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Natural Park', 'slug' => 'natural-park'],
                    'hr' => ['name' => 'Prirodni park', 'slug' => 'prirodni-park'],
                    'sl' => ['name' => 'Naravni park', 'slug' => 'naravni-park'],
                ],
            ],
            [
                'code' => 'region-seafood-specialty',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Seafood Specialty', 'slug' => 'seafood-specialty'],
                    'hr' => ['name' => 'Specijaliteti od morskih plodova', 'slug' => 'specijaliteti-od-morskih-plodova'],
                    'sl' => ['name' => 'Specialitete morskih sadežev', 'slug' => 'specialitete-morskih-sadezev'],
                ],
            ],
            [
                'code' => 'region-water-sports',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Water Sports', 'slug' => 'water-sports'],
                    'hr' => ['name' => 'Vodeni sportovi', 'slug' => 'vodeni-sportovi'],
                    'sl' => ['name' => 'Vodni športi', 'slug' => 'vodni-sporti'],
                ],
            ],
            [
                'code' => 'region-hiking-trails',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Hiking Trails', 'slug' => 'hiking-trails'],
                    'hr' => ['name' => 'Pješačke staze', 'slug' => 'pjesacke-staze'],
                    'sl' => ['name' => 'Pohodniške poti', 'slug' => 'pohodniske-poti'],
                ],
            ],

            // Island badges (6)
            [
                'code' => 'island-krk-lamb',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Krk Lamb', 'slug' => 'krk-lamb'],
                    'hr' => ['name' => 'Krčka janjetina', 'slug' => 'krcka-janjetina'],
                    'sl' => ['name' => 'Krška jagnjeta', 'slug' => 'krska-jagnjeta'],
                ],
            ],
            [
                'code' => 'island-zlahtina-wine',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Žlahtina Wine', 'slug' => 'zlahtina-wine'],
                    'hr' => ['name' => 'Vino Žlahtina', 'slug' => 'vino-zlahtina'],
                    'sl' => ['name' => 'Vino Žlahtina', 'slug' => 'vino-zlahtina'],
                ],
            ],
            [
                'code' => 'island-crna-ovca-festival',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Crna Ovca Festival', 'slug' => 'crna-ovca-festival'],
                    'hr' => ['name' => 'Festival Crna Ovca', 'slug' => 'festival-crna-ovca'],
                    'sl' => ['name' => 'Festival Crna Ovca', 'slug' => 'festival-crna-ovca'],
                ],
            ],
            [
                'code' => 'island-secluded-beaches',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Secluded Beaches', 'slug' => 'secluded-beaches'],
                    'hr' => ['name' => 'Skrivene plaže', 'slug' => 'skrivene-plaze'],
                    'sl' => ['name' => 'Osamljene plaže', 'slug' => 'osamljene-plaze'],
                ],
            ],
            [
                'code' => 'island-diving-spots',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Diving Spots', 'slug' => 'diving-spots'],
                    'hr' => ['name' => 'Ronilačka mjesta', 'slug' => 'ronilacka-mjesta'],
                    'sl' => ['name' => 'Potapljaška mesta', 'slug' => 'potapljaska-mesta'],
                ],
            ],
            [
                'code' => 'island-lighthouse',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Historic Lighthouse', 'slug' => 'historic-lighthouse'],
                    'hr' => ['name' => 'Povijesni svjetionik', 'slug' => 'povijesni-svjetionik'],
                    'sl' => ['name' => 'Zgodovinski svetilnik', 'slug' => 'zgodovinski-svetilnik'],
                ],
            ],

            // City badges (6)
            [
                'code' => 'city-marina',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Marina', 'slug' => 'marina'],
                    'hr' => ['name' => 'Marina', 'slug' => 'marina'],
                    'sl' => ['name' => 'Pristanišče', 'slug' => 'pristanisce'],
                ],
            ],
            [
                'code' => 'city-promenade',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Seaside Promenade', 'slug' => 'seaside-promenade'],
                    'hr' => ['name' => 'Šetalište uz more', 'slug' => 'setaliste-uz-more'],
                    'sl' => ['name' => 'Obmorska promenada', 'slug' => 'obmorska-promenada'],
                ],
            ],
            [
                'code' => 'city-old-town',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Historic Old Town', 'slug' => 'historic-old-town'],
                    'hr' => ['name' => 'Povijesna stara jezgra', 'slug' => 'povijesna-stara-jezgra'],
                    'sl' => ['name' => 'Zgodovinsko staro mesto', 'slug' => 'zgodovinsko-staro-mesto'],
                ],
            ],
            [
                'code' => 'city-nightlife',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Vibrant Nightlife', 'slug' => 'vibrant-nightlife'],
                    'hr' => ['name' => 'Živahan noćni život', 'slug' => 'zivahan-nocni-zivot'],
                    'sl' => ['name' => 'Živahno nočno življenje', 'slug' => 'zivahno-nocno-zivljenje'],
                ],
            ],
            [
                'code' => 'city-local-market',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Local Market', 'slug' => 'local-market'],
                    'hr' => ['name' => 'Lokalna tržnica', 'slug' => 'lokalna-trznica'],
                    'sl' => ['name' => 'Lokalna tržnica', 'slug' => 'lokalna-trznica'],
                ],
            ],
            [
                'code' => 'city-cultural-events',
                'icon' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Cultural Events', 'slug' => 'cultural-events'],
                    'hr' => ['name' => 'Kulturni događaji', 'slug' => 'kulturni-dogadaji'],
                    'sl' => ['name' => 'Kulturni dogodki', 'slug' => 'kulturni-dogodki'],
                ],
            ],
        ];

        $createdBadges = [];
        foreach ($badges as $badgeData) {
            $translations = $badgeData['translations'];
            unset($badgeData['translations']);

            $badge = Badge::create($badgeData);
            $createdBadges[$badgeData['code']] = $badge;

            foreach ($translations as $locale => $translation) {
                BadgeTranslation::create([
                    'badge_id' => $badge->id,
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                ]);
            }
        }

        // Attach badges to countries, regions, and islands
        $this->attachBadgesToEntities($createdBadges);
    }

    private function attachBadgesToEntities(array $badges): void
    {
        $now = now();

        // Get sample countries (Croatia, Slovenia, Italy, Greece)
        $countries = \App\Models\Country::whereIn('code', ['HR', 'SI', 'IT', 'GR'])->get()->keyBy('code');

        // Get sample regions (first 4)
        $regions = \App\Models\Region::limit(4)->get();

        // Get sample islands (first 4)
        $islands = \App\Models\Island::limit(4)->get();

        // Get sample cities (first 4)
        $cities = \App\Models\City::limit(4)->get();

        // Attach 6 country badges to Croatia
        if (isset($countries['HR'])) {
            $countryBadges = [
                'country-unesco-heritage',
                'country-olive-oil',
                'country-wine-region',
                'country-coastal-tourism',
                'country-traditional-cuisine',
                'country-blue-flag',
            ];
            foreach ($countryBadges as $badgeCode) {
                if (isset($badges[$badgeCode])) {
                    DB::table('badge_country')->insert([
                        'badge_id' => $badges[$badgeCode]->id,
                        'country_code' => 'HR',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 6 country badges to Slovenia
        if (isset($countries['SI'])) {
            $countryBadges = [
                'country-olive-oil',
                'country-wine-region',
                'country-coastal-tourism',
                'country-traditional-cuisine',
                'country-blue-flag',
                'country-unesco-heritage',
            ];
            foreach ($countryBadges as $badgeCode) {
                if (isset($badges[$badgeCode])) {
                    DB::table('badge_country')->insert([
                        'badge_id' => $badges[$badgeCode]->id,
                        'country_code' => 'SI',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 6 country badges to Italy
        if (isset($countries['IT'])) {
            $countryBadges = [
                'country-unesco-heritage',
                'country-olive-oil',
                'country-wine-region',
                'country-coastal-tourism',
                'country-traditional-cuisine',
                'country-blue-flag',
            ];
            foreach ($countryBadges as $badgeCode) {
                if (isset($badges[$badgeCode])) {
                    DB::table('badge_country')->insert([
                        'badge_id' => $badges[$badgeCode]->id,
                        'country_code' => 'IT',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 6 country badges to Greece
        if (isset($countries['GR'])) {
            $countryBadges = [
                'country-unesco-heritage',
                'country-olive-oil',
                'country-wine-region',
                'country-coastal-tourism',
                'country-traditional-cuisine',
                'country-blue-flag',
            ];
            foreach ($countryBadges as $badgeCode) {
                if (isset($badges[$badgeCode])) {
                    DB::table('badge_country')->insert([
                        'badge_id' => $badges[$badgeCode]->id,
                        'country_code' => 'GR',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 6 region badges to each region
        $regionBadges = [
            'region-local-festival',
            'region-historic-sites',
            'region-natural-park',
            'region-seafood-specialty',
            'region-water-sports',
            'region-hiking-trails',
        ];

        foreach ($regions as $region) {
            foreach ($regionBadges as $badgeCode) {
                if (isset($badges[$badgeCode])) {
                    DB::table('badge_region')->insert([
                        'badge_id' => $badges[$badgeCode]->id,
                        'region_id' => $region->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 6 island badges to each island
        $islandBadges = [
            'island-krk-lamb',
            'island-zlahtina-wine',
            'island-crna-ovca-festival',
            'island-secluded-beaches',
            'island-diving-spots',
            'island-lighthouse',
        ];

        foreach ($islands as $island) {
            foreach ($islandBadges as $badgeCode) {
                if (isset($badges[$badgeCode])) {
                    DB::table('badge_island')->insert([
                        'badge_id' => $badges[$badgeCode]->id,
                        'island_id' => $island->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 6 city badges to each city
        $cityBadges = [
            'city-marina',
            'city-promenade',
            'city-old-town',
            'city-nightlife',
            'city-local-market',
            'city-cultural-events',
        ];

        foreach ($cities as $city) {
            foreach ($cityBadges as $badgeCode) {
                if (isset($badges[$badgeCode])) {
                    DB::table('badge_city')->insert([
                        'badge_id' => $badges[$badgeCode]->id,
                        'city_id' => $city->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}

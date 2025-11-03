<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\RegionTranslation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example region data based on old database structure
        // Format: 'region', 'region_nice_url', 'region_full_name_nice_url'
        // Example: 'Dalmatia - Dubrovnik', 'dalmatia', 'dalmatia-dubrovnik'

        $regions = [
            [
                'id' => 1,
                'code' => 'dalmatia-dubrovnik',
                'country_code' => 'HR', // Croatia
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'name' => 'Dalmatia - Dubrovnik',
                        'slug' => 'dalmatia-dubrovnik',
                    ],
                    'sl' => [
                        'name' => 'Dalmacija - Dubrovnik',
                        'slug' => 'dalmacija-dubrovnik',
                    ],
                    'hr' => [
                        'name' => 'Dalmacija - Dubrovnik',
                        'slug' => 'dalmacija-dubrovnik',
                    ],
                    'de' => [
                        'name' => 'Dalmatien - Dubrovnik',
                        'slug' => 'dalmatien-dubrovnik',
                    ],
                    'fr' => [
                        'name' => 'Dalmatie - Dubrovnik',
                        'slug' => 'dalmatie-dubrovnik',
                    ],
                    'es' => [
                        'name' => 'Dalmacia - Dubrovnik',
                        'slug' => 'dalmacia-dubrovnik',
                    ],
                    'it' => [
                        'name' => 'Dalmazia - Dubrovnik',
                        'slug' => 'dalmazia-dubrovnik',
                    ],
                    'ua' => [
                        'name' => 'Далмація - Дубровник',
                        'slug' => 'dalmatsiia-dubrovnyk',
                    ],
                    'ru' => [
                        'name' => 'Далмация - Дубровник',
                        'slug' => 'dalmatsiia-dubrovnik',
                    ],
                    'pl' => [
                        'name' => 'Dalmacja - Dubrownik',
                        'slug' => 'dalmacja-dubrownik',
                    ],
                    'cs' => [
                        'name' => 'Dalmácie - Dubrovník',
                        'slug' => 'dalmacie-dubrovnik',
                    ],
                    'hu' => [
                        'name' => 'Dalmácia - Dubrovnik',
                        'slug' => 'dalmacia-dubrovnik',
                    ],
                    'bg' => [
                        'name' => 'Далмация - Дубровник',
                        'slug' => 'dalmatsiia-dubrovnik',
                    ],
                    'sk' => [
                        'name' => 'Dalmácia - Dubrovník',
                        'slug' => 'dalmacia-dubrovnik',
                    ],
                ],
            ],
            // Add more regions here as needed
        ];

        foreach ($regions as $regionData) {
            $region = Region::updateOrCreate(
                ['id' => $regionData['id']],
                [
                    'code' => $regionData['code'],
                    'country_code' => $regionData['country_code'],
                    'status_id' => $regionData['status_id'],
                ]
            );

            foreach ($regionData['translations'] as $locale => $translation) {
                RegionTranslation::updateOrCreate(
                    ['region_id' => $region->id, 'locale' => $locale],
                    [
                        'name' => $translation['name'],
                        'slug' => $translation['slug'],
                    ]
                );
            }
        }

        $this->command->info('Regions seeded successfully with translations in 14 languages!');
    }
}

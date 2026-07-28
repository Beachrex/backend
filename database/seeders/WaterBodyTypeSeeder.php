<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WaterBodyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('water_body_type')->count() > 0) {
                $this->command->info('Water body type table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $waterBodyTypes = [
            [
                'id' => 1,
                'code' => 'lake',
                'status_id' => 1,
            ],
            [
                'id' => 2,
                'code' => 'sea',
                'status_id' => 1,
            ],
            [
                'id' => 3,
                'code' => 'river',
                'status_id' => 1,
            ],
        ];

        $translations = [
            'lake' => [
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
            'sea' => [
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
            'river' => [
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
        ];

        foreach ($waterBodyTypes as $typeData) {
            // Insert water body type with explicit ID
            DB::table('water_body_type')->insert([
                'id' => $typeData['id'],
                'code' => $typeData['code'],
                'status_id' => $typeData['status_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($translations[$typeData['code']] as $locale => $translation) {
                DB::table('water_body_type_translation')->insert([
                    'water_body_type_id' => $typeData['id'],
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


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BayLengthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('bay_length')->count() > 0) {
                $this->command->info('bay_length table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $bayLengths = [
            ['id' => 1, 'code' => '3m'],
            ['id' => 2, 'code' => '5m'],
            ['id' => 3, 'code' => '10m'],
            ['id' => 4, 'code' => '15m'],
            ['id' => 5, 'code' => '20m'],
            ['id' => 6, 'code' => '25m'],
            ['id' => 7, 'code' => '30m'],
            ['id' => 8, 'code' => '40m'],
            ['id' => 9, 'code' => '50m'],
            ['id' => 10, 'code' => '75m'],
            ['id' => 11, 'code' => '100m'],
            ['id' => 12, 'code' => 'more_100m'],
        ];

        foreach ($bayLengths as $lengthData) {
            // Insert bay length with explicit ID
            DB::table('bay_length')->insert([
                'id' => $lengthData['id'],
                'code' => $lengthData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Determine display name based on code
            $displayName = $lengthData['code'];
            $slug = $lengthData['code'];
            
            // Special handling for more_100m
            if ($lengthData['code'] === 'more_100m') {
                $translations = [
                    'en' => ['name' => '>100m', 'slug' => 'more-than-100m'],
                    'sl' => ['name' => '>100m', 'slug' => 'vec-kot-100m'],
                    'de' => ['name' => '>100m', 'slug' => 'mehr-als-100m'],
                    'hr' => ['name' => '>100m', 'slug' => 'vise-od-100m'],
                    'fr' => ['name' => '>100m', 'slug' => 'plus-de-100m'],
                    'es' => ['name' => '>100m', 'slug' => 'mas-de-100m'],
                    'it' => ['name' => '>100m', 'slug' => 'piu-di-100m'],
                    'ua' => ['name' => '>100м', 'slug' => 'bilshe-100m'],
                    'ru' => ['name' => '>100м', 'slug' => 'bolshe-100m'],
                    'pl' => ['name' => '>100m', 'slug' => 'wiecej-niz-100m'],
                    'cs' => ['name' => '>100m', 'slug' => 'vice-nez-100m'],
                    'hu' => ['name' => '>100m', 'slug' => 'tobb-mint-100m'],
                    'bg' => ['name' => '>100м', 'slug' => 'poveche-ot-100m'],
                    'sk' => ['name' => '>100m', 'slug' => 'viac-ako-100m'],
                ];
            } else {
                // For numeric lengths, use the same value for all languages
                $translations = [
                    'en' => ['name' => $displayName, 'slug' => $slug],
                    'sl' => ['name' => $displayName, 'slug' => $slug],
                    'de' => ['name' => $displayName, 'slug' => $slug],
                    'hr' => ['name' => $displayName, 'slug' => $slug],
                    'fr' => ['name' => $displayName, 'slug' => $slug],
                    'es' => ['name' => $displayName, 'slug' => $slug],
                    'it' => ['name' => $displayName, 'slug' => $slug],
                    'ua' => ['name' => str_replace('m', 'м', $displayName), 'slug' => $slug],
                    'ru' => ['name' => str_replace('m', 'м', $displayName), 'slug' => $slug],
                    'pl' => ['name' => $displayName, 'slug' => $slug],
                    'cs' => ['name' => $displayName, 'slug' => $slug],
                    'hu' => ['name' => $displayName, 'slug' => $slug],
                    'bg' => ['name' => str_replace('m', 'м', $displayName), 'slug' => $slug],
                    'sk' => ['name' => $displayName, 'slug' => $slug],
                ];
            }

            // Insert translations
            foreach ($translations as $locale => $translation) {
                DB::table('bay_length_translation')->insert([
                    'bay_length_id' => $lengthData['id'],
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Bay lengths seeded successfully with translations in 14 languages!');
    }
}


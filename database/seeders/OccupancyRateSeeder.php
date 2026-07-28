<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OccupancyRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('occupancy_rate')->count() > 0) {
                $this->command->info('occupancy_rate table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $occupancyRates = [
            ['id' => 1, 'code' => 'small'],
            ['id' => 2, 'code' => 'middle'],
            ['id' => 3, 'code' => 'big'],
        ];

        $translations = [
            'small' => [
                'en' => ['name' => 'Small', 'slug' => 'small'],
                'sl' => ['name' => 'Majhna', 'slug' => 'majhna'],
                'de' => ['name' => 'Klein', 'slug' => 'klein'],
                'hr' => ['name' => 'Mala', 'slug' => 'mala'],
                'fr' => ['name' => 'Petite', 'slug' => 'petite'],
                'es' => ['name' => 'Pequeña', 'slug' => 'pequena'],
                'it' => ['name' => 'Piccola', 'slug' => 'piccola'],
                'ua' => ['name' => 'Мала', 'slug' => 'mala'],
                'ru' => ['name' => 'Малая', 'slug' => 'malaya'],
                'pl' => ['name' => 'Mała', 'slug' => 'mala'],
                'cs' => ['name' => 'Malá', 'slug' => 'mala'],
                'hu' => ['name' => 'Kicsi', 'slug' => 'kicsi'],
                'bg' => ['name' => 'Малка', 'slug' => 'malka'],
                'sk' => ['name' => 'Malá', 'slug' => 'mala'],
                'da' => ['name' => 'Lille', 'slug' => 'lille'],
                'pt' => ['name' => 'Pequena', 'slug' => 'pequena'],
                'nl' => ['name' => 'Klein', 'slug' => 'klein'],
                'el' => ['name' => 'Μικρή', 'slug' => 'mikri'],
                'sq' => ['name' => 'E vogël', 'slug' => 'e-vogel'],
            ],
            'middle' => [
                'en' => ['name' => 'Middle', 'slug' => 'middle'],
                'sl' => ['name' => 'Srednja', 'slug' => 'srednja'],
                'de' => ['name' => 'Mittel', 'slug' => 'mittel'],
                'hr' => ['name' => 'Srednja', 'slug' => 'srednja'],
                'fr' => ['name' => 'Moyenne', 'slug' => 'moyenne'],
                'es' => ['name' => 'Media', 'slug' => 'media'],
                'it' => ['name' => 'Media', 'slug' => 'media'],
                'ua' => ['name' => 'Середня', 'slug' => 'serednia'],
                'ru' => ['name' => 'Средняя', 'slug' => 'srednyaya'],
                'pl' => ['name' => 'Średnia', 'slug' => 'srednia'],
                'cs' => ['name' => 'Střední', 'slug' => 'stredni'],
                'hu' => ['name' => 'Közepes', 'slug' => 'kozepes'],
                'bg' => ['name' => 'Средна', 'slug' => 'sredna'],
                'sk' => ['name' => 'Stredná', 'slug' => 'stredna'],
                'da' => ['name' => 'Mellem', 'slug' => 'mellem'],
                'pt' => ['name' => 'Média', 'slug' => 'media'],
                'nl' => ['name' => 'Gemiddeld', 'slug' => 'gemiddeld'],
                'el' => ['name' => 'Μεσαία', 'slug' => 'mesaia'],
                'sq' => ['name' => 'E mesme', 'slug' => 'e-mesme'],
            ],
            'big' => [
                'en' => ['name' => 'Big', 'slug' => 'big'],
                'sl' => ['name' => 'Velika', 'slug' => 'velika'],
                'de' => ['name' => 'Groß', 'slug' => 'gross'],
                'hr' => ['name' => 'Velika', 'slug' => 'velika'],
                'fr' => ['name' => 'Grande', 'slug' => 'grande'],
                'es' => ['name' => 'Grande', 'slug' => 'grande'],
                'it' => ['name' => 'Grande', 'slug' => 'grande'],
                'ua' => ['name' => 'Велика', 'slug' => 'velyka'],
                'ru' => ['name' => 'Большая', 'slug' => 'bolshaya'],
                'pl' => ['name' => 'Duża', 'slug' => 'duza'],
                'cs' => ['name' => 'Velká', 'slug' => 'velka'],
                'hu' => ['name' => 'Nagy', 'slug' => 'nagy'],
                'bg' => ['name' => 'Голяма', 'slug' => 'golyama'],
                'sk' => ['name' => 'Veľká', 'slug' => 'velka'],
                'da' => ['name' => 'Stor', 'slug' => 'stor'],
                'pt' => ['name' => 'Grande', 'slug' => 'grande'],
                'nl' => ['name' => 'Groot', 'slug' => 'groot'],
                'el' => ['name' => 'Μεγάλη', 'slug' => 'megali'],
                'sq' => ['name' => 'E madhe', 'slug' => 'e-madhe'],
            ],
        ];

        foreach ($occupancyRates as $rateData) {
            // Insert occupancy rate with explicit ID
            DB::table('occupancy_rate')->insert([
                'id' => $rateData['id'],
                'code' => $rateData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($translations[$rateData['code']] as $locale => $translation) {
                DB::table('occupancy_rate_translation')->insert([
                    'occupancy_rate_id' => $rateData['id'],
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Occupancy rates seeded successfully with translations in 14 languages!');
    }
}


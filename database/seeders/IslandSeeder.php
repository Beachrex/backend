<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IslandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('island')->count() > 0) {
                $this->command->info('Island table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $islands = [
            [
                'id' => 1,
                'code' => 'als',
                'status_id' => 1,
            ],
            [
                'id' => 2,
                'code' => 'euboea',
                'status_id' => 1,
            ],
            [
                'id' => 3,
                'code' => 'fehmarn',
                'status_id' => 1,
            ],
            [
                'id' => 4,
                'code' => 'funen',
                'status_id' => 1,
            ],
            [
                'id' => 5,
                'code' => 'brac',
                'status_id' => 1,
            ],
            [
                'id' => 6,
                'code' => 'ciovo',
                'status_id' => 1,
            ],
            [
                'id' => 7,
                'code' => 'cres',
                'status_id' => 1,
            ],
            [
                'id' => 8,
                'code' => 'hvar',
                'status_id' => 1,
            ],
            [
                'id' => 9,
                'code' => 'korcula',
                'status_id' => 1,
            ],
            [
                'id' => 10,
                'code' => 'krk',
                'status_id' => 1,
            ],
            [
                'id' => 11,
                'code' => 'lido',
                'status_id' => 1,
            ],
            [
                'id' => 12,
                'code' => 'losinj',
                'status_id' => 1,
            ],
            [
                'id' => 13,
                'code' => 'murter',
                'status_id' => 1,
            ],
            [
                'id' => 14,
                'code' => 'pag',
                'status_id' => 1,
            ],
            [
                'id' => 15,
                'code' => 'pasman',
                'status_id' => 1,
            ],
            [
                'id' => 16,
                'code' => 'pellestrina',
                'status_id' => 1,
            ],
            [
                'id' => 17,
                'code' => 'rab',
                'status_id' => 1,
            ],
            [
                'id' => 18,
                'code' => 'solta',
                'status_id' => 1,
            ],
            [
                'id' => 19,
                'code' => 'ugljan',
                'status_id' => 1,
            ],
            [
                'id' => 20,
                'code' => 'vir',
                'status_id' => 1,
            ],
            [
                'id' => 21,
                'code' => 'vis',
                'status_id' => 1,
            ],
            [
                'id' => 22,
                'code' => 'langeland',
                'status_id' => 1,
            ],
            [
                'id' => 23,
                'code' => 'lefkada',
                'status_id' => 1,
            ],
            [
                'id' => 24,
                'code' => 'poel',
                'status_id' => 1,
            ],
            [
                'id' => 25,
                'code' => 'rugen',
                'status_id' => 1,
            ],
            [
                'id' => 26,
                'code' => 'romo',
                'status_id' => 1,
            ],
            [
                'id' => 27,
                'code' => 'sardegna',
                'status_id' => 1,
            ],
            [
                'id' => 28,
                'code' => 'sylt',
                'status_id' => 1,
            ],
            [
                'id' => 29,
                'code' => 'usedom',
                'status_id' => 1,
            ],
        ];

        foreach ($islands as $island) {
            DB::table('island')->insert([
                'id' => $island['id'],
                'code' => $island['code'],
                'status_id' => $island['status_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Translations for islands
        // Most islands have the same name in all languages, but some have translations
        $translations = $this->getIslandTranslations();

        foreach ($translations as $code => $localeTranslations) {
            $island = DB::table('island')->where('code', $code)->first();
            if ($island) {
                foreach ($localeTranslations as $locale => $data) {
                    DB::table('island_translation')->insert([
                        'island_id' => $island->id,
                        'locale' => $locale,
                        'name' => $data['name'],
                        'slug' => $data['slug'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function getIslandTranslations(): array
    {
        $locales = ['en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq'];

        // Helper function to create same name for all languages
        $sameName = function($name) use ($locales) {
            $result = [];
            foreach ($locales as $locale) {
                $result[$locale] = ['name' => $name, 'slug' => Str::slug($name)];
            }
            return $result;
        };

        return [
            'als' => $sameName('Als'),
            'euboea' => $sameName('Euboea'),
            'fehmarn' => $sameName('Fehmarn'),
            'funen' => $sameName('Funen'),
            'brac' => $sameName('Brač'),
            'ciovo' => $sameName('Čiovo'),
            'cres' => $sameName('Cres'),
            'hvar' => $sameName('Hvar'),
            'korcula' => $sameName('Korčula'),
            'krk' => $sameName('Krk'),
            'lido' => $sameName('Lido'),
            'losinj' => $sameName('Lošinj'),
            'murter' => $sameName('Murter'),
            'pag' => $sameName('Pag'),
            'pasman' => $sameName('Pašman'),
            'pellestrina' => $sameName('Pellestrina'),
            'rab' => $sameName('Rab'),
            'solta' => $sameName('Šolta'),
            'ugljan' => $sameName('Ugljan'),
            'vir' => $sameName('Vir'),
            'vis' => $sameName('Vis'),
            'langeland' => $sameName('Langeland'),
            'lefkada' => $sameName('Lefkada'),
            'poel' => $sameName('Poel'),
            'rugen' => $sameName('Rügen'),
            'romo' => $sameName('Rømø'),
            'sylt' => $sameName('Sylt'),
            'usedom' => $sameName('Usedom'),

            // Sardegna has different translations
            'sardegna' => [
                'en' => ['name' => 'Sardinia', 'slug' => 'sardinia'],
                'sl' => ['name' => 'Sardinija', 'slug' => 'sardinija'],
                'de' => ['name' => 'Sardinien', 'slug' => 'sardinien'],
                'hr' => ['name' => 'Sardinija', 'slug' => 'sardinija'],
                'fr' => ['name' => 'Sardaigne', 'slug' => 'sardaigne'],
                'es' => ['name' => 'Cerdeña', 'slug' => 'cerdena'],
                'it' => ['name' => 'Sardegna', 'slug' => 'sardegna'],
                'ua' => ['name' => 'Сардинія', 'slug' => 'sardyniia'],
                'ru' => ['name' => 'Сардиния', 'slug' => 'sardiniya'],
                'pl' => ['name' => 'Sardynia', 'slug' => 'sardynia'],
                'cs' => ['name' => 'Sardinie', 'slug' => 'sardinie'],
                'hu' => ['name' => 'Szardínia', 'slug' => 'szardinia'],
                'bg' => ['name' => 'Сардиния', 'slug' => 'sardiniya'],
                'sk' => ['name' => 'Sardínia', 'slug' => 'sardinia'],
            ],
        ];
    }
}


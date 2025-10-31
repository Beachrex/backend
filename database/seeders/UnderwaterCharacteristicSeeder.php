<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnderwaterCharacteristicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $characteristics = [
            ['id' => 1, 'code' => 'sand_ground'],
            ['id' => 2, 'code' => 'stones'],
            ['id' => 3, 'code' => 'rocks'],
            ['id' => 4, 'code' => 'grass'],
            ['id' => 5, 'code' => 'coral'],
        ];

        $translations = [
            'sand_ground' => [
                'en' => ['name' => 'Sand Ground', 'slug' => 'sand-ground'],
                'sl' => ['name' => 'Peščeno Dno', 'slug' => 'pesceno-dno'],
                'de' => ['name' => 'Sandboden', 'slug' => 'sandboden'],
                'hr' => ['name' => 'Pješčano Dno', 'slug' => 'pjescano-dno'],
                'fr' => ['name' => 'Fond Sableux', 'slug' => 'fond-sableux'],
                'es' => ['name' => 'Fondo Arenoso', 'slug' => 'fondo-arenoso'],
                'it' => ['name' => 'Fondale Sabbioso', 'slug' => 'fondale-sabbioso'],
                'ua' => ['name' => 'Піщане Дно', 'slug' => 'pischane-dno'],
                'ru' => ['name' => 'Песчаное Дно', 'slug' => 'peschanoe-dno'],
                'pl' => ['name' => 'Piaszczyste Dno', 'slug' => 'piaszczyste-dno'],
                'cs' => ['name' => 'Písčité Dno', 'slug' => 'piscite-dno'],
                'hu' => ['name' => 'Homokos Fenék', 'slug' => 'homokos-fenek'],
                'bg' => ['name' => 'Пясъчно Дъно', 'slug' => 'piasachno-dano'],
                'sk' => ['name' => 'Piesočnaté Dno', 'slug' => 'piesocnate-dno'],
            ],
            'stones' => [
                'en' => ['name' => 'Stones', 'slug' => 'stones'],
                'sl' => ['name' => 'Kamni', 'slug' => 'kamni'],
                'de' => ['name' => 'Steine', 'slug' => 'steine'],
                'hr' => ['name' => 'Kamenje', 'slug' => 'kamenje'],
                'fr' => ['name' => 'Pierres', 'slug' => 'pierres'],
                'es' => ['name' => 'Piedras', 'slug' => 'piedras'],
                'it' => ['name' => 'Pietre', 'slug' => 'pietre'],
                'ua' => ['name' => 'Камені', 'slug' => 'kameni'],
                'ru' => ['name' => 'Камни', 'slug' => 'kamni'],
                'pl' => ['name' => 'Kamienie', 'slug' => 'kamienie'],
                'cs' => ['name' => 'Kameny', 'slug' => 'kameny'],
                'hu' => ['name' => 'Kövek', 'slug' => 'kovek'],
                'bg' => ['name' => 'Камъни', 'slug' => 'kamani'],
                'sk' => ['name' => 'Kamene', 'slug' => 'kamene'],
            ],
            'rocks' => [
                'en' => ['name' => 'Rocks', 'slug' => 'rocks'],
                'sl' => ['name' => 'Skale', 'slug' => 'skale'],
                'de' => ['name' => 'Felsen', 'slug' => 'felsen'],
                'hr' => ['name' => 'Stijene', 'slug' => 'stijene'],
                'fr' => ['name' => 'Rochers', 'slug' => 'rochers'],
                'es' => ['name' => 'Rocas', 'slug' => 'rocas'],
                'it' => ['name' => 'Rocce', 'slug' => 'rocce'],
                'ua' => ['name' => 'Скелі', 'slug' => 'skeli'],
                'ru' => ['name' => 'Скалы', 'slug' => 'skaly'],
                'pl' => ['name' => 'Skały', 'slug' => 'skaly'],
                'cs' => ['name' => 'Skály', 'slug' => 'skaly'],
                'hu' => ['name' => 'Sziklák', 'slug' => 'sziklak'],
                'bg' => ['name' => 'Скали', 'slug' => 'skali'],
                'sk' => ['name' => 'Skaly', 'slug' => 'skaly'],
            ],
            'grass' => [
                'en' => ['name' => 'Grass', 'slug' => 'grass'],
                'sl' => ['name' => 'Trava', 'slug' => 'trava'],
                'de' => ['name' => 'Gras', 'slug' => 'gras'],
                'hr' => ['name' => 'Trava', 'slug' => 'trava'],
                'fr' => ['name' => 'Herbe', 'slug' => 'herbe'],
                'es' => ['name' => 'Hierba', 'slug' => 'hierba'],
                'it' => ['name' => 'Erba', 'slug' => 'erba'],
                'ua' => ['name' => 'Трава', 'slug' => 'trava'],
                'ru' => ['name' => 'Трава', 'slug' => 'trava'],
                'pl' => ['name' => 'Trawa', 'slug' => 'trawa'],
                'cs' => ['name' => 'Tráva', 'slug' => 'trava'],
                'hu' => ['name' => 'Fű', 'slug' => 'fu'],
                'bg' => ['name' => 'Трева', 'slug' => 'treva'],
                'sk' => ['name' => 'Tráva', 'slug' => 'trava'],
            ],
            'coral' => [
                'en' => ['name' => 'Coral', 'slug' => 'coral'],
                'sl' => ['name' => 'Korala', 'slug' => 'korala'],
                'de' => ['name' => 'Koralle', 'slug' => 'koralle'],
                'hr' => ['name' => 'Koralj', 'slug' => 'koralj'],
                'fr' => ['name' => 'Corail', 'slug' => 'corail'],
                'es' => ['name' => 'Coral', 'slug' => 'coral'],
                'it' => ['name' => 'Corallo', 'slug' => 'corallo'],
                'ua' => ['name' => 'Корал', 'slug' => 'koral'],
                'ru' => ['name' => 'Коралл', 'slug' => 'korall'],
                'pl' => ['name' => 'Koral', 'slug' => 'koral'],
                'cs' => ['name' => 'Korál', 'slug' => 'koral'],
                'hu' => ['name' => 'Korall', 'slug' => 'korall'],
                'bg' => ['name' => 'Корал', 'slug' => 'koral'],
                'sk' => ['name' => 'Koral', 'slug' => 'koral'],
            ],
        ];

        foreach ($characteristics as $characteristicData) {
            // Insert characteristic with explicit ID
            DB::table('underwater_characteristic')->insert([
                'id' => $characteristicData['id'],
                'code' => $characteristicData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations if available
            if (isset($translations[$characteristicData['code']])) {
                foreach ($translations[$characteristicData['code']] as $locale => $translation) {
                    DB::table('underwater_characteristic_translation')->insert([
                        'underwater_characteristic_id' => $characteristicData['id'],
                        'locale' => $locale,
                        'name' => $translation['name'],
                        'slug' => $translation['slug'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Underwater characteristics seeded successfully with translations in 14 languages!');
    }
}


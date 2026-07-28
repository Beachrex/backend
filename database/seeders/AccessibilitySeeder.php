<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessibilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('accessibility')->count() > 0) {
                $this->command->info('accessibility table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $accessibilities = [
            [
                'id' => 1,
                'code' => 'easy',
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Easy', 'slug' => 'easy'],
                    'sl' => ['name' => 'Lahko', 'slug' => 'lahko'],
                    'de' => ['name' => 'Einfach', 'slug' => 'einfach'],
                    'hr' => ['name' => 'Lako', 'slug' => 'lako'],
                    'fr' => ['name' => 'Facile', 'slug' => 'facile'],
                    'es' => ['name' => 'Fácil', 'slug' => 'facil'],
                    'it' => ['name' => 'Facile', 'slug' => 'facile'],
                    'ua' => ['name' => 'Легко', 'slug' => 'lehko'],
                    'ru' => ['name' => 'Легко', 'slug' => 'legko'],
                    'pl' => ['name' => 'Łatwy', 'slug' => 'latwy'],
                    'cs' => ['name' => 'Snadný', 'slug' => 'snadny'],
                    'hu' => ['name' => 'Könnyű', 'slug' => 'konnyu'],
                    'bg' => ['name' => 'Лесен', 'slug' => 'lesen'],
                    'sk' => ['name' => 'Ľahký', 'slug' => 'lahky'],
                    'da' => ['name' => 'Nem', 'slug' => 'nem'],
                    'pt' => ['name' => 'Fácil', 'slug' => 'facil'],
                    'nl' => ['name' => 'Makkelijk', 'slug' => 'makkelijk'],
                    'el' => ['name' => 'Εύκολο', 'slug' => 'efkolo'],
                    'sq' => ['name' => 'I lehtë', 'slug' => 'i-lete'],

                ],
            ],
            [
                'id' => 2,
                'code' => 'medium',
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Medium', 'slug' => 'medium'],
                    'sl' => ['name' => 'Srednje', 'slug' => 'srednje'],
                    'de' => ['name' => 'Mittel', 'slug' => 'mittel'],
                    'hr' => ['name' => 'Srednje', 'slug' => 'srednje'],
                    'fr' => ['name' => 'Moyen', 'slug' => 'moyen'],
                    'es' => ['name' => 'Medio', 'slug' => 'medio'],
                    'it' => ['name' => 'Medio', 'slug' => 'medio'],
                    'ua' => ['name' => 'Середньо', 'slug' => 'seredno'],
                    'ru' => ['name' => 'Средне', 'slug' => 'sredne'],
                    'pl' => ['name' => 'Średni', 'slug' => 'sredni'],
                    'cs' => ['name' => 'Střední', 'slug' => 'stredni'],
                    'hu' => ['name' => 'Közepes', 'slug' => 'kozepes'],
                    'bg' => ['name' => 'Среден', 'slug' => 'sreden'],
                    'sk' => ['name' => 'Stredný', 'slug' => 'stredny'],
                    'da' => ['name' => 'Middel', 'slug' => 'middel'],
                    'pt' => ['name' => 'Médio', 'slug' => 'medio'],
                    'nl' => ['name' => 'Gemiddeld', 'slug' => 'gemiddeld'],
                    'el' => ['name' => 'Μεσαίο', 'slug' => 'mesaio'],
                    'sq' => ['name' => 'I mesëm', 'slug' => 'i-mesem'],

                ],
            ],
            [
                'id' => 3,
                'code' => 'difficult',
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Difficult', 'slug' => 'difficult'],
                    'sl' => ['name' => 'Težko', 'slug' => 'tezko'],
                    'de' => ['name' => 'Schwierig', 'slug' => 'schwierig'],
                    'hr' => ['name' => 'Teško', 'slug' => 'tesko'],
                    'fr' => ['name' => 'Difficile', 'slug' => 'difficile'],
                    'es' => ['name' => 'Difícil', 'slug' => 'dificil'],
                    'it' => ['name' => 'Difficile', 'slug' => 'difficile'],
                    'ua' => ['name' => 'Важко', 'slug' => 'vazhko'],
                    'ru' => ['name' => 'Сложно', 'slug' => 'slozhno'],
                    'da' => ['name' => 'Svær', 'slug' => 'svaer'],
                    'pt' => ['name' => 'Difícil', 'slug' => 'dificil'],
                    'nl' => ['name' => 'Moeilijk', 'slug' => 'moeilijk'],
                    'el' => ['name' => 'Δύσκολο', 'slug' => 'dyskolo'],
                    'sq' => ['name' => 'I vështirë', 'slug' => 'i-veshtire'],
                ],
            ],
        ];

        foreach ($accessibilities as $accessibilityData) {
            // Insert accessibility with explicit ID
            DB::table('accessibility')->insert([
                'id' => $accessibilityData['id'],
                'code' => $accessibilityData['code'],
                'status_id' => $accessibilityData['status_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($accessibilityData['translations'] as $locale => $translation) {
                DB::table('accessibility_translation')->insert([
                    'accessibility_id' => $accessibilityData['id'],
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


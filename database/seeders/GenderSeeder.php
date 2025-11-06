<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genders = [
            [
                'id' => 1,
                'name' => 'male',
                'translations' => [
                    'en' => ['name' => 'Male', 'slug' => 'male'],
                    'sl' => ['name' => 'Moški', 'slug' => 'moski'],
                    'de' => ['name' => 'Männlich', 'slug' => 'mannlich'],
                    'hr' => ['name' => 'Muški', 'slug' => 'muski'],
                    'fr' => ['name' => 'Homme', 'slug' => 'homme'],
                    'es' => ['name' => 'Hombre', 'slug' => 'hombre'],
                    'it' => ['name' => 'Maschio', 'slug' => 'maschio'],
                    'ua' => ['name' => 'Чоловік', 'slug' => 'cholovik'],
                    'ru' => ['name' => 'Мужской', 'slug' => 'muzhskoy'],
                    'pl' => ['name' => 'Mężczyzna', 'slug' => 'mezczyzna'],
                    'cs' => ['name' => 'Muž', 'slug' => 'muz'],
                    'hu' => ['name' => 'Férfi', 'slug' => 'ferfi'],
                    'bg' => ['name' => 'Мъжки', 'slug' => 'mazhki'],
                    'sk' => ['name' => 'Muž', 'slug' => 'muz'],
                ],
            ],
            [
                'id' => 2,
                'name' => 'female',
                'translations' => [
                    'en' => ['name' => 'Female', 'slug' => 'female'],
                    'sl' => ['name' => 'Ženska', 'slug' => 'zenska'],
                    'de' => ['name' => 'Weiblich', 'slug' => 'weiblich'],
                    'hr' => ['name' => 'Ženski', 'slug' => 'zenski'],
                    'fr' => ['name' => 'Femme', 'slug' => 'femme'],
                    'es' => ['name' => 'Mujer', 'slug' => 'mujer'],
                    'it' => ['name' => 'Femmina', 'slug' => 'femmina'],
                    'ua' => ['name' => 'Жінка', 'slug' => 'zhinka'],
                    'ru' => ['name' => 'Женский', 'slug' => 'zhenskiy'],
                    'pl' => ['name' => 'Kobieta', 'slug' => 'kobieta'],
                    'cs' => ['name' => 'Žena', 'slug' => 'zena'],
                    'hu' => ['name' => 'Nő', 'slug' => 'no'],
                    'bg' => ['name' => 'Женски', 'slug' => 'zhenski'],
                    'sk' => ['name' => 'Žena', 'slug' => 'zena'],
                ],
            ],
        ];

        foreach ($genders as $genderData) {
            // Insert gender with explicit ID
            DB::table('gender')->insert([
                'id' => $genderData['id'],
                'name' => $genderData['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($genderData['translations'] as $locale => $translation) {
                DB::table('gender_translation')->insert([
                    'gender_id' => $genderData['id'],
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


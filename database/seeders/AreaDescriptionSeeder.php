<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaDescriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('area_description')->count() > 0) {
                $this->command->info('Area description table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $areaDescriptions = [
            [
                'id' => 1,
                'code' => 'urban',
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Urban', 'slug' => 'urban'],
                    'sl' => ['name' => 'Urbano', 'slug' => 'urbano'],
                    'de' => ['name' => 'Städtisch', 'slug' => 'stadtisch'],
                    'hr' => ['name' => 'Urbano', 'slug' => 'urbano'],
                    'fr' => ['name' => 'Urbain', 'slug' => 'urbain'],
                    'es' => ['name' => 'Urbano', 'slug' => 'urbano'],
                    'it' => ['name' => 'Urbano', 'slug' => 'urbano'],
                    'ua' => ['name' => 'Міський', 'slug' => 'miskyi'],
                    'ru' => ['name' => 'Городской', 'slug' => 'gorodskoy'],
                    'pl' => ['name' => 'Miejski', 'slug' => 'miejski'],
                    'cs' => ['name' => 'Městský', 'slug' => 'mestsky'],
                    'hu' => ['name' => 'Városi', 'slug' => 'varosi'],
                    'bg' => ['name' => 'Градски', 'slug' => 'gradski'],
                    'sk' => ['name' => 'Mestský', 'slug' => 'mestsky'],
                    'da' => ['name' => 'Bymæssig', 'slug' => 'bymaessig'],
                    'pt' => ['name' => 'Urbano', 'slug' => 'urbano'],
                    'nl' => ['name' => 'Stedelijk', 'slug' => 'stedelijk'],
                    'el' => ['name' => 'Αστικό', 'slug' => 'astiko'],
                    'sq' => ['name' => 'Urban', 'slug' => 'urban'],

                ],
            ],
            [
                'id' => 2,
                'code' => 'part_urban',
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Partly Urban', 'slug' => 'partly-urban'],
                    'sl' => ['name' => 'Delno urbano', 'slug' => 'delno-urbano'],
                    'de' => ['name' => 'Teilweise städtisch', 'slug' => 'teilweise-stadtisch'],
                    'hr' => ['name' => 'Djelomično urbano', 'slug' => 'djelomicno-urbano'],
                    'fr' => ['name' => 'Partiellement urbain', 'slug' => 'partiellement-urbain'],
                    'es' => ['name' => 'Parcialmente urbano', 'slug' => 'parcialmente-urbano'],
                    'it' => ['name' => 'Parzialmente urbano', 'slug' => 'parzialmente-urbano'],
                    'ua' => ['name' => 'Частково міський', 'slug' => 'chastkovo-miskyi'],
                    'ru' => ['name' => 'Частично городской', 'slug' => 'chastichno-gorodskoy'],
                    'da' => ['name' => 'Delvist bymæssig', 'slug' => 'delvist-bymaessig'],
                    'pt' => ['name' => 'Parcialmente urbano', 'slug' => 'parcialmente-urbano'],
                    'nl' => ['name' => 'Deels stedelijk', 'slug' => 'deels-stedelijk'],
                    'el' => ['name' => 'Μερικώς αστικό', 'slug' => 'merikos-astiko'],
                    'sq' => ['name' => 'Pjesërisht urban', 'slug' => 'pjeserisht-urban'],
                ],
            ],
            [
                'id' => 3,
                'code' => 'natural',
                'status_id' => 1,
                'translations' => [
                    'en' => ['name' => 'Natural', 'slug' => 'natural'],
                    'sl' => ['name' => 'Naravno', 'slug' => 'naravno'],
                    'de' => ['name' => 'Natürlich', 'slug' => 'naturlich'],
                    'hr' => ['name' => 'Prirodno', 'slug' => 'prirodno'],
                    'fr' => ['name' => 'Naturel', 'slug' => 'naturel'],
                    'es' => ['name' => 'Natural', 'slug' => 'natural'],
                    'it' => ['name' => 'Naturale', 'slug' => 'naturale'],
                    'ua' => ['name' => 'Природний', 'slug' => 'pryrodnyi'],
                    'ru' => ['name' => 'Природный', 'slug' => 'prirodnyy'],
                    'pl' => ['name' => 'Naturalny', 'slug' => 'naturalny'],
                    'cs' => ['name' => 'Přírodní', 'slug' => 'prirodni'],
                    'hu' => ['name' => 'Természetes', 'slug' => 'termeszetes'],
                    'bg' => ['name' => 'Природен', 'slug' => 'priroden'],
                    'sk' => ['name' => 'Prírodný', 'slug' => 'prirodny'],
                    'da' => ['name' => 'Naturlig', 'slug' => 'naturlig'],
                    'pt' => ['name' => 'Natural', 'slug' => 'natural'],
                    'nl' => ['name' => 'Natuurlijk', 'slug' => 'natuurlijk'],
                    'el' => ['name' => 'Φυσικό', 'slug' => 'fysiko'],
                    'sq' => ['name' => 'Natyror', 'slug' => 'natyror'],

                ],
            ],
        ];

        foreach ($areaDescriptions as $areaData) {
            // Insert area description with explicit ID
            DB::table('area_description')->insert([
                'id' => $areaData['id'],
                'code' => $areaData['code'],
                'status_id' => $areaData['status_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($areaData['translations'] as $locale => $translation) {
                DB::table('area_description_translation')->insert([
                    'area_description_id' => $areaData['id'],
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


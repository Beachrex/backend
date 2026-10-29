<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BeachInclinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('beach_inclination')->count() > 0) {
                $this->command->info('beach_inclination table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $beachInclinations = [
            ['id' => 1, 'code' => 'small_slope'],
            ['id' => 2, 'code' => 'normal'],
            ['id' => 3, 'code' => 'increase'],
            ['id' => 4, 'code' => 'sudden_drop'],
            ['id' => 5, 'code' => 'different_mixed'],
        ];

        $translations = [
            'small_slope' => [
                'en' => ['name' => 'Small slope', 'slug' => 'small-slope'],
                'sl' => ['name' => 'Majhen naklon', 'slug' => 'majhen-naklon'],
                'de' => ['name' => 'Kleine Neigung', 'slug' => 'kleine-neigung'],
                'hr' => ['name' => 'Mali nagib', 'slug' => 'mali-nagib'],
                'fr' => ['name' => 'Petite pente', 'slug' => 'petite-pente'],
                'es' => ['name' => 'Pendiente pequeña', 'slug' => 'pendiente-pequena'],
                'it' => ['name' => 'Piccola pendenza', 'slug' => 'piccola-pendenza'],
                'ua' => ['name' => 'Невеликий нахил', 'slug' => 'nevelykyi-nakhyl'],
                'ru' => ['name' => 'Небольшой уклон', 'slug' => 'nebolshoy-uklon'],
                'pl' => ['name' => 'Małe nachylenie', 'slug' => 'male-nachylenie'],
                'cs' => ['name' => 'Malý sklon', 'slug' => 'maly-sklon'],
                'hu' => ['name' => 'Kis lejtés', 'slug' => 'kis-lejtes'],
                'bg' => ['name' => 'Малък наклон', 'slug' => 'malak-naklon'],
                'sk' => ['name' => 'Malý sklon', 'slug' => 'maly-sklon'],
                'da' => ['name' => 'Lille hældning', 'slug' => 'lille-haeldning'],
                'pt' => ['name' => 'Pequena inclinação', 'slug' => 'pequena-inclinacao'],
                'nl' => ['name' => 'Kleine helling', 'slug' => 'kleine-helling'],
                'el' => ['name' => 'Μικρή κλίση', 'slug' => 'mikri-klisi'],
                'sq' => ['name' => 'Pjerrësi e vogël', 'slug' => 'pjerresi-e-vogel'],
            ],
            'normal' => [
                'en' => ['name' => 'Normal', 'slug' => 'normal'],
                'sl' => ['name' => 'Normalno', 'slug' => 'normalno'],
                'de' => ['name' => 'Normal', 'slug' => 'normal'],
                'hr' => ['name' => 'Normalno', 'slug' => 'normalno'],
                'fr' => ['name' => 'Normal', 'slug' => 'normal'],
                'es' => ['name' => 'Normal', 'slug' => 'normal'],
                'it' => ['name' => 'Normale', 'slug' => 'normale'],
                'ua' => ['name' => 'Нормальний', 'slug' => 'normalnyi'],
                'ru' => ['name' => 'Нормальный', 'slug' => 'normalnyy'],
                'pl' => ['name' => 'Normalne', 'slug' => 'normalne'],
                'cs' => ['name' => 'Normální', 'slug' => 'normalni'],
                'hu' => ['name' => 'Normál', 'slug' => 'normal'],
                'bg' => ['name' => 'Нормален', 'slug' => 'normalen'],
                'sk' => ['name' => 'Normálne', 'slug' => 'normalne'],
                'da' => ['name' => 'Normal', 'slug' => 'normal'],
                'pt' => ['name' => 'Normal', 'slug' => 'normal'],
                'nl' => ['name' => 'Normaal', 'slug' => 'normaal'],
                'el' => ['name' => 'Κανονικό', 'slug' => 'kanoniko'],
                'sq' => ['name' => 'Normale', 'slug' => 'normale'],
            ],
            'increase' => [
                'en' => ['name' => 'Increase', 'slug' => 'increase'],
                'sl' => ['name' => 'Naraščajoč naklon', 'slug' => 'povecanje'],
                'de' => ['name' => 'Zunahme', 'slug' => 'zunahme'],
                'hr' => ['name' => 'Povećanje', 'slug' => 'povecanje'],
                'fr' => ['name' => 'Augmentation', 'slug' => 'augmentation'],
                'es' => ['name' => 'Aumento', 'slug' => 'aumento'],
                'it' => ['name' => 'Aumento', 'slug' => 'aumento'],
                'ua' => ['name' => 'Збільшення', 'slug' => 'zbilshennia'],
                'ru' => ['name' => 'Увеличение', 'slug' => 'uvelichenie'],
                'pl' => ['name' => 'Wzrost', 'slug' => 'wzrost'],
                'cs' => ['name' => 'Zvýšení', 'slug' => 'zvyseni'],
                'hu' => ['name' => 'Növekedés', 'slug' => 'novekedes'],
                'bg' => ['name' => 'Увеличение', 'slug' => 'uvelichenie'],
                'sk' => ['name' => 'Zvýšenie', 'slug' => 'zvysenie'],
                'da' => ['name' => 'Stigning', 'slug' => 'stigning'],
                'pt' => ['name' => 'Aumento', 'slug' => 'aumento'],
                'nl' => ['name' => 'Toename', 'slug' => 'toename'],
                'el' => ['name' => 'Αύξηση', 'slug' => 'afxisi'],
                'sq' => ['name' => 'Rritje', 'slug' => 'rritje'],
            ],
            'sudden_drop' => [
                'en' => ['name' => 'Sudden drop', 'slug' => 'sudden-drop'],
                'sl' => ['name' => 'Nenadni padec', 'slug' => 'nenadni-padec'],
                'de' => ['name' => 'Plötzlicher Abfall', 'slug' => 'plotzlicher-abfall'],
                'hr' => ['name' => 'Nagli pad', 'slug' => 'nagli-pad'],
                'fr' => ['name' => 'Chute soudaine', 'slug' => 'chute-soudaine'],
                'es' => ['name' => 'Caída repentina', 'slug' => 'caida-repentina'],
                'it' => ['name' => 'Caduta improvvisa', 'slug' => 'caduta-improvvisa'],
                'ua' => ['name' => 'Раптове падіння', 'slug' => 'raptove-padinnia'],
                'ru' => ['name' => 'Резкое падение', 'slug' => 'rezkoe-padenie'],
                'pl' => ['name' => 'Nagły spadek', 'slug' => 'nagly-spadek'],
                'cs' => ['name' => 'Náhlý pokles', 'slug' => 'nahly-pokles'],
                'hu' => ['name' => 'Hirtelen esés', 'slug' => 'hirtelen-eses'],
                'bg' => ['name' => 'Внезапно падане', 'slug' => 'vnezapno-padane'],
                'sk' => ['name' => 'Náhly pokles', 'slug' => 'nahly-pokles'],
                'da' => ['name' => 'Pludseligt fald', 'slug' => 'pludseligt-fald'],
                'pt' => ['name' => 'Queda repentina', 'slug' => 'queda-repentina'],
                'nl' => ['name' => 'Plotselinge daling', 'slug' => 'plotselinge-daling'],
                'el' => ['name' => 'Απότομη πτώση', 'slug' => 'aptomi-ptosi'],
                'sq' => ['name' => 'Rënie e papritur', 'slug' => 'renie-e-papritur'],
            ],
            'different_mixed' => [
                'en' => ['name' => 'Different/mixed', 'slug' => 'different-mixed'],
                'sl' => ['name' => 'Različno/mešano', 'slug' => 'razlicno-mesano'],
                'de' => ['name' => 'Unterschiedlich/gemischt', 'slug' => 'unterschiedlich-gemischt'],
                'hr' => ['name' => 'Različito/mješovito', 'slug' => 'razlicito-mjesovito'],
                'fr' => ['name' => 'Différent/mixte', 'slug' => 'different-mixte'],
                'es' => ['name' => 'Diferente/mixto', 'slug' => 'diferente-mixto'],
                'it' => ['name' => 'Diverso/misto', 'slug' => 'diverso-misto'],
                'ua' => ['name' => 'Різний/змішаний', 'slug' => 'riznyi-zmishanyi'],
                'ru' => ['name' => 'Разный/смешанный', 'slug' => 'raznyy-smeshannyy'],
                'pl' => ['name' => 'Różne/mieszane', 'slug' => 'rozne-mieszane'],
                'cs' => ['name' => 'Různé/smíšené', 'slug' => 'ruzne-smisene'],
                'hu' => ['name' => 'Különböző/vegyes', 'slug' => 'kulonbozo-vegyes'],
                'bg' => ['name' => 'Различен/смесен', 'slug' => 'razlichen-smesen'],
                'sk' => ['name' => 'Rôzne/zmiešané', 'slug' => 'rozne-zmiesane'],
                'da' => ['name' => 'Forskellige/blandet', 'slug' => 'forskellige-blandet'],
                'pt' => ['name' => 'Diferente/misto', 'slug' => 'diferente-misto'],
                'nl' => ['name' => 'Verschillend/gemengd', 'slug' => 'verschillend-gemengd'],
                'el' => ['name' => 'Διαφορετικό/μικτό', 'slug' => 'diaforetiko-mikto'],
                'sq' => ['name' => 'Të ndryshme/përzier', 'slug' => 'te-ndryshme-perzier'],
            ],
        ];

        foreach ($beachInclinations as $inclinationData) {
            // Insert beach inclination with explicit ID
            DB::table('beach_inclination')->insert([
                'id' => $inclinationData['id'],
                'code' => $inclinationData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($translations[$inclinationData['code']] as $locale => $translation) {
                DB::table('beach_inclination_translation')->insert([
                    'beach_inclination_id' => $inclinationData['id'],
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Beach inclinations seeded successfully with translations in 14 languages!');
    }
}


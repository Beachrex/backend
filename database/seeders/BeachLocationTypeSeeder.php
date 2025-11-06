<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BeachLocationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('beach_location_type')->count() > 0) {
                $this->command->info('beach_location_type table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $beachLocationTypes = [
            [
                'id' => 1,
                'code' => 'LK',
                'status_id' => 1,
            ],
            [
                'id' => 2,
                'code' => 'LKJ',
                'status_id' => 1,
            ],
            [
                'id' => 3,
                'code' => 'LKR',
                'status_id' => 1,
            ],
            [
                'id' => 4,
                'code' => 'T',
                'status_id' => 1,
            ],
            [
                'id' => 5,
                'code' => 'PB',
                'status_id' => 1,
            ],
            [
                'id' => 6,
                'code' => 'AQ',
                'status_id' => 1,
            ],
        ];

        $translations = [
            'LK' => [
                'en' => ['name' => 'Annual Bathing Area', 'slug' => 'annual-bathing-area'],
                'sl' => ['name' => 'Letno Kopališče', 'slug' => 'letno-kopalisce'],
                'de' => ['name' => 'Jährlicher Badebereich', 'slug' => 'jahrlicher-badebereich'],
                'hr' => ['name' => 'Godišnje Kupalište', 'slug' => 'godisnje-kupaliste'],
                'fr' => ['name' => 'Zone de Baignade Annuelle', 'slug' => 'zone-de-baignade-annuelle'],
                'es' => ['name' => 'Área de Baño Anual', 'slug' => 'area-de-bano-anual'],
                'it' => ['name' => 'Area Balneare Annuale', 'slug' => 'area-balneare-annuale'],
                'ua' => ['name' => 'Річна Зона Купання', 'slug' => 'richna-zona-kupannya'],
                'ru' => ['name' => 'Годовая Зона Купания', 'slug' => 'godovaya-zona-kupaniya'],
                'pl' => ['name' => 'Roczne Kąpielisko', 'slug' => 'roczne-kapielisko'],
                'cs' => ['name' => 'Roční Koupaliště', 'slug' => 'rocni-koupaliste'],
                'hu' => ['name' => 'Éves Fürdőhely', 'slug' => 'eves-furdohely'],
                'bg' => ['name' => 'Годишна Зона за Къпане', 'slug' => 'godishna-zona-za-kapane'],
                'sk' => ['name' => 'Ročné Kúpalisko', 'slug' => 'rocne-kupalisko'],
            ],
            'LKJ' => [
                'en' => ['name' => 'Annual Bathing Area by Lake', 'slug' => 'annual-bathing-area-by-lake'],
                'sl' => ['name' => 'Letno Kopališče ob Jezeru', 'slug' => 'letno-kopalisce-ob-jezeru'],
                'de' => ['name' => 'Jährlicher Badebereich am See', 'slug' => 'jahrlicher-badebereich-am-see'],
                'hr' => ['name' => 'Godišnje Kupalište na Jezeru', 'slug' => 'godisnje-kupaliste-na-jezeru'],
                'fr' => ['name' => 'Zone de Baignade Annuelle au Lac', 'slug' => 'zone-de-baignade-annuelle-au-lac'],
                'es' => ['name' => 'Área de Baño Anual en Lago', 'slug' => 'area-de-bano-anual-en-lago'],
                'it' => ['name' => 'Area Balneare Annuale al Lago', 'slug' => 'area-balneare-annuale-al-lago'],
                'ua' => ['name' => 'Річна Зона Купання на Озері', 'slug' => 'richna-zona-kupannya-na-ozeri'],
                'ru' => ['name' => 'Годовая Зона Купания на Озере', 'slug' => 'godovaya-zona-kupaniya-na-ozere'],
                'pl' => ['name' => 'Roczne Kąpielisko nad Jeziorem', 'slug' => 'roczne-kapielisko-nad-jeziorem'],
                'cs' => ['name' => 'Roční Koupaliště u Jezera', 'slug' => 'rocni-koupaliste-u-jezera'],
                'hu' => ['name' => 'Éves Fürdőhely a Tónál', 'slug' => 'eves-furdohely-a-tonal'],
                'bg' => ['name' => 'Годишна Зона за Къпане на Езеро', 'slug' => 'godishna-zona-za-kapane-na-ezero'],
                'sk' => ['name' => 'Ročné Kúpalisko pri Jazere', 'slug' => 'rocne-kupalisko-pri-jazere'],
            ],
            'LKR' => [
                'en' => ['name' => 'Annual Bathing Area by River', 'slug' => 'annual-bathing-area-by-river'],
                'sl' => ['name' => 'Letno Kopališče ob Reki', 'slug' => 'letno-kopalisce-ob-reki'],
                'de' => ['name' => 'Jährlicher Badebereich am Fluss', 'slug' => 'jahrlicher-badebereich-am-fluss'],
                'hr' => ['name' => 'Godišnje Kupalište na Rijeci', 'slug' => 'godisnje-kupaliste-na-rijeci'],
                'fr' => ['name' => 'Zone de Baignade Annuelle à la Rivière', 'slug' => 'zone-de-baignade-annuelle-a-la-riviere'],
                'es' => ['name' => 'Área de Baño Anual en Río', 'slug' => 'area-de-bano-anual-en-rio'],
                'it' => ['name' => 'Area Balneare Annuale al Fiume', 'slug' => 'area-balneare-annuale-al-fiume'],
                'ua' => ['name' => 'Річна Зона Купання на Річці', 'slug' => 'richna-zona-kupannya-na-richtsi'],
                'ru' => ['name' => 'Годовая Зона Купания на Реке', 'slug' => 'godovaya-zona-kupaniya-na-reke'],
                'pl' => ['name' => 'Roczne Kąpielisko nad Rzeką', 'slug' => 'roczne-kapielisko-nad-rzeka'],
                'cs' => ['name' => 'Roční Koupaliště u Řeky', 'slug' => 'rocni-koupaliste-u-reky'],
                'hu' => ['name' => 'Éves Fürdőhely a Folyónál', 'slug' => 'eves-furdohely-a-folyonal'],
                'bg' => ['name' => 'Годишна Зона за Къпане на Река', 'slug' => 'godishna-zona-za-kapane-na-reka'],
                'sk' => ['name' => 'Ročné Kúpalisko pri Rieke', 'slug' => 'rocne-kupalisko-pri-rieke'],
            ],
            'T' => [
                'en' => ['name' => 'Thermal Spa', 'slug' => 'thermal-spa'],
                'sl' => ['name' => 'Terme', 'slug' => 'terme'],
                'de' => ['name' => 'Thermalbad', 'slug' => 'thermalbad'],
                'hr' => ['name' => 'Toplice', 'slug' => 'toplice'],
                'fr' => ['name' => 'Spa Thermal', 'slug' => 'spa-thermal'],
                'es' => ['name' => 'Balneario Termal', 'slug' => 'balneario-termal'],
                'it' => ['name' => 'Terme', 'slug' => 'terme'],
                'ua' => ['name' => 'Термальний Курорт', 'slug' => 'termalnyi-kurort'],
                'ru' => ['name' => 'Термальный Курорт', 'slug' => 'termalnyy-kurort'],
                'pl' => ['name' => 'Termy', 'slug' => 'termy'],
                'cs' => ['name' => 'Termální Lázně', 'slug' => 'termalni-lazne'],
                'hu' => ['name' => 'Termálfürdő', 'slug' => 'termalfurdo'],
                'bg' => ['name' => 'Термален Курорт', 'slug' => 'termalen-kurort'],
                'sk' => ['name' => 'Termálne Kúpele', 'slug' => 'termalne-kupele'],
            ],
            'PB' => [
                'en' => ['name' => 'Indoor Pool', 'slug' => 'indoor-pool'],
                'sl' => ['name' => 'Pokriti Bazen', 'slug' => 'pokriti-bazen'],
                'de' => ['name' => 'Hallenbad', 'slug' => 'hallenbad'],
                'hr' => ['name' => 'Zatvoreni Bazen', 'slug' => 'zatvoreni-bazen'],
                'fr' => ['name' => 'Piscine Couverte', 'slug' => 'piscine-couverte'],
                'es' => ['name' => 'Piscina Cubierta', 'slug' => 'piscina-cubierta'],
                'it' => ['name' => 'Piscina Coperta', 'slug' => 'piscina-coperta'],
                'ua' => ['name' => 'Критий Басейн', 'slug' => 'krytyi-baseyn'],
                'ru' => ['name' => 'Крытый Бассейн', 'slug' => 'krytyy-basseyn'],
                'pl' => ['name' => 'Basen Kryty', 'slug' => 'basen-kryty'],
                'cs' => ['name' => 'Krytý Bazén', 'slug' => 'kryty-bazen'],
                'hu' => ['name' => 'Fedett Medence', 'slug' => 'fedett-medence'],
                'bg' => ['name' => 'Закрит Басейн', 'slug' => 'zakrit-baseyn'],
                'sk' => ['name' => 'Krytý Bazén', 'slug' => 'kryty-bazen'],
            ],
            'AQ' => [
                'en' => ['name' => 'Aquapark', 'slug' => 'aquapark'],
                'sl' => ['name' => 'Aquapark', 'slug' => 'aquapark'],
                'de' => ['name' => 'Aquapark', 'slug' => 'aquapark'],
                'hr' => ['name' => 'Aquapark', 'slug' => 'aquapark'],
                'fr' => ['name' => 'Parc Aquatique', 'slug' => 'parc-aquatique'],
                'es' => ['name' => 'Parque Acuático', 'slug' => 'parque-acuatico'],
                'it' => ['name' => 'Parco Acquatico', 'slug' => 'parco-acquatico'],
                'ua' => ['name' => 'Аквапарк', 'slug' => 'akvapark'],
                'ru' => ['name' => 'Аквапарк', 'slug' => 'akvapark'],
                'pl' => ['name' => 'Aquapark', 'slug' => 'aquapark'],
                'cs' => ['name' => 'Aquapark', 'slug' => 'aquapark'],
                'hu' => ['name' => 'Aquapark', 'slug' => 'aquapark'],
                'bg' => ['name' => 'Аквапарк', 'slug' => 'akvapark'],
                'sk' => ['name' => 'Aquapark', 'slug' => 'aquapark'],
            ],
        ];

        foreach ($beachLocationTypes as $typeData) {
            // Insert beach location type with explicit ID
            DB::table('beach_location_type')->insert([
                'id' => $typeData['id'],
                'code' => $typeData['code'],
                'status_id' => $typeData['status_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($translations[$typeData['code']] as $locale => $translation) {
                DB::table('beach_location_type_translation')->insert([
                    'beach_location_type_id' => $typeData['id'],
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


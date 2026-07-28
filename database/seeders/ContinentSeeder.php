<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContinentSeeder extends Seeder
{
    public function run(): void
    {
        try {
            if (DB::table('continents')->count() > 0) {
                $this->command->info('Continents table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        // Insert continents (just codes, names are in translations)
        $continents = [
            ['code' => 'AF'],
            ['code' => 'AN'],
            ['code' => 'AS'],
            ['code' => 'EU'],
            ['code' => 'NA'],
            ['code' => 'OC'],
            ['code' => 'SA'],
        ];

        foreach ($continents as $continent) {
            DB::table('continents')->updateOrInsert(
                ['code' => $continent['code']],
                $continent
            );
        }

        // Insert translations
        $translations = $this->getContinentTranslations();

        foreach ($translations as $code => $localeTranslations) {
            foreach ($localeTranslations as $locale => $data) {
                DB::table('continent_translation')->updateOrInsert(
                    [
                        'continent_code' => $code,
                        'locale' => $locale,
                    ],
                    [
                        'continent_code' => $code,
                        'locale' => $locale,
                        'name' => $data['name'],
                        'slug' => $data['slug'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('Continents and translations seeded successfully.');
    }

    /**
     * Get continent translations for all languages
     */
    private function getContinentTranslations(): array
    {
        return [
            'AF' => [
                'en' => ['name' => 'Africa', 'slug' => 'africa'],
                'sl' => ['name' => 'Afrika', 'slug' => 'afrika'],
                'de' => ['name' => 'Afrika', 'slug' => 'afrika'],
                'hr' => ['name' => 'Afrika', 'slug' => 'afrika'],
                'fr' => ['name' => 'Afrique', 'slug' => 'afrique'],
                'es' => ['name' => 'África', 'slug' => 'africa'],
                'it' => ['name' => 'Africa', 'slug' => 'africa'],
                'ua' => ['name' => 'Африка', 'slug' => 'afrika'],
                'ru' => ['name' => 'Африка', 'slug' => 'afrika'],
                'pl' => ['name' => 'Afryka', 'slug' => 'afryka'],
                'cs' => ['name' => 'Afrika', 'slug' => 'afrika'],
                'hu' => ['name' => 'Afrika', 'slug' => 'afrika'],
                'bg' => ['name' => 'Африка', 'slug' => 'afrika'],
                'sk' => ['name' => 'Afrika', 'slug' => 'afrika'],
                'da' => ['name' => 'Afrika', 'slug' => 'afrika'],
                'pt' => ['name' => 'África', 'slug' => 'africa'],
                'nl' => ['name' => 'Afrika', 'slug' => 'afrika'],
                'el' => ['name' => 'Αφρική', 'slug' => 'afriki'],
                'sq' => ['name' => 'Afrika', 'slug' => 'afrika'],
            ],
            'AN' => [
                'en' => ['name' => 'Antarctica', 'slug' => 'antarctica'],
                'sl' => ['name' => 'Antarktika', 'slug' => 'antarktika'],
                'de' => ['name' => 'Antarktis', 'slug' => 'antarktis'],
                'hr' => ['name' => 'Antarktika', 'slug' => 'antarktika'],
                'fr' => ['name' => 'Antarctique', 'slug' => 'antarctique'],
                'es' => ['name' => 'Antártida', 'slug' => 'antartida'],
                'it' => ['name' => 'Antartide', 'slug' => 'antartide'],
                'ua' => ['name' => 'Антарктида', 'slug' => 'antarktida'],
                'ru' => ['name' => 'Антарктида', 'slug' => 'antarktida'],
                'pl' => ['name' => 'Antarktyda', 'slug' => 'antarktyda'],
                'cs' => ['name' => 'Antarktida', 'slug' => 'antarktida'],
                'hu' => ['name' => 'Antarktisz', 'slug' => 'antarktisz'],
                'bg' => ['name' => 'Антарктида', 'slug' => 'antarktida'],
                'sk' => ['name' => 'Antarktída', 'slug' => 'antarktida'],
                'da' => ['name' => 'Antarktis', 'slug' => 'antarktis'],
                'pt' => ['name' => 'Antártida', 'slug' => 'antartida'],
                'nl' => ['name' => 'Antarctica', 'slug' => 'antarctica'],
                'el' => ['name' => 'Ανταρκτική', 'slug' => 'antarktiki'],
                'sq' => ['name' => 'Antarktiku', 'slug' => 'antarktiku'],
            ],
            'AS' => [
                'en' => ['name' => 'Asia', 'slug' => 'asia'],
                'sl' => ['name' => 'Azija', 'slug' => 'azija'],
                'de' => ['name' => 'Asien', 'slug' => 'asien'],
                'hr' => ['name' => 'Azija', 'slug' => 'azija'],
                'fr' => ['name' => 'Asie', 'slug' => 'asie'],
                'es' => ['name' => 'Asia', 'slug' => 'asia'],
                'it' => ['name' => 'Asia', 'slug' => 'asia'],
                'ua' => ['name' => 'Азія', 'slug' => 'azija'],
                'ru' => ['name' => 'Азия', 'slug' => 'azija'],
                'pl' => ['name' => 'Azja', 'slug' => 'azja'],
                'cs' => ['name' => 'Asie', 'slug' => 'asie'],
                'hu' => ['name' => 'Ázsia', 'slug' => 'azsia'],
                'bg' => ['name' => 'Азия', 'slug' => 'azija'],
                'sk' => ['name' => 'Ázia', 'slug' => 'azia'],
                'da' => ['name' => 'Asien', 'slug' => 'asien'],
                'pt' => ['name' => 'Ásia', 'slug' => 'asia'],
                'nl' => ['name' => 'Azië', 'slug' => 'azie'],
                'el' => ['name' => 'Ασία', 'slug' => 'asia'],
                'sq' => ['name' => 'Azia', 'slug' => 'azia'],
            ],
            'EU' => [
                'en' => ['name' => 'Europe', 'slug' => 'europe'],
                'sl' => ['name' => 'Evropa', 'slug' => 'evropa'],
                'de' => ['name' => 'Europa', 'slug' => 'europa'],
                'hr' => ['name' => 'Europa', 'slug' => 'europa'],
                'fr' => ['name' => 'Europe', 'slug' => 'europe'],
                'es' => ['name' => 'Europa', 'slug' => 'europa'],
                'it' => ['name' => 'Europa', 'slug' => 'europa'],
                'ua' => ['name' => 'Європа', 'slug' => 'jevropa'],
                'ru' => ['name' => 'Европа', 'slug' => 'jevropa'],
                'pl' => ['name' => 'Europa', 'slug' => 'europa'],
                'cs' => ['name' => 'Evropa', 'slug' => 'evropa'],
                'hu' => ['name' => 'Európa', 'slug' => 'europa'],
                'bg' => ['name' => 'Европа', 'slug' => 'jevropa'],
                'sk' => ['name' => 'Európa', 'slug' => 'europa'],
                'da' => ['name' => 'Europa', 'slug' => 'europa'],
                'pt' => ['name' => 'Europa', 'slug' => 'europa'],
                'nl' => ['name' => 'Europa', 'slug' => 'europa'],
                'el' => ['name' => 'Ευρώπη', 'slug' => 'evropi'],
                'sq' => ['name' => 'Evropa', 'slug' => 'evropa'],
            ],
            'NA' => [
                'en' => ['name' => 'North America', 'slug' => 'north-america'],
                'sl' => ['name' => 'Severna Amerika', 'slug' => 'severna-amerika'],
                'de' => ['name' => 'Nordamerika', 'slug' => 'nordamerika'],
                'hr' => ['name' => 'Sjeverna Amerika', 'slug' => 'sjeverna-amerika'],
                'fr' => ['name' => 'Amérique du Nord', 'slug' => 'amerique-du-nord'],
                'es' => ['name' => 'América del Norte', 'slug' => 'america-del-norte'],
                'it' => ['name' => 'America del Nord', 'slug' => 'america-del-nord'],
                'ua' => ['name' => 'Північна Америка', 'slug' => 'pivnichna-amerika'],
                'ru' => ['name' => 'Северная Америка', 'slug' => 'severnaja-amerika'],
                'pl' => ['name' => 'Ameryka Północna', 'slug' => 'ameryka-polnocna'],
                'cs' => ['name' => 'Severní Amerika', 'slug' => 'severni-amerika'],
                'hu' => ['name' => 'Észak-Amerika', 'slug' => 'eszak-amerika'],
                'bg' => ['name' => 'Северна Америка', 'slug' => 'severna-amerika'],
                'sk' => ['name' => 'Severná Amerika', 'slug' => 'severna-amerika'],
                'da' => ['name' => 'Nordamerika', 'slug' => 'nordamerika'],
                'pt' => ['name' => 'América do Norte', 'slug' => 'america-do-norte'],
                'nl' => ['name' => 'Noord-Amerika', 'slug' => 'noord-amerika'],
                'el' => ['name' => 'Βόρεια Αμερική', 'slug' => 'boreia-ameriki'],
                'sq' => ['name' => 'Amerika e Veriut', 'slug' => 'amerika-e-veriut'],
            ],
            'OC' => [
                'en' => ['name' => 'Oceania', 'slug' => 'oceania'],
                'sl' => ['name' => 'Oceanija', 'slug' => 'oceanija'],
                'de' => ['name' => 'Ozeanien', 'slug' => 'ozeanien'],
                'hr' => ['name' => 'Oceanija', 'slug' => 'oceanija'],
                'fr' => ['name' => 'Océanie', 'slug' => 'oceanie'],
                'es' => ['name' => 'Oceanía', 'slug' => 'oceania'],
                'it' => ['name' => 'Oceania', 'slug' => 'oceania'],
                'ua' => ['name' => 'Океанія', 'slug' => 'okeanija'],
                'ru' => ['name' => 'Океания', 'slug' => 'okeanija'],
                'pl' => ['name' => 'Oceania', 'slug' => 'oceania'],
                'cs' => ['name' => 'Oceánie', 'slug' => 'oceanie'],
                'hu' => ['name' => 'Óceánia', 'slug' => 'oceania'],
                'bg' => ['name' => 'Океания', 'slug' => 'okeanija'],
                'sk' => ['name' => 'Oceánia', 'slug' => 'oceania'],
                'da' => ['name' => 'Oceanien', 'slug' => 'oceanien'],
                'pt' => ['name' => 'Oceânia', 'slug' => 'oceania'],
                'nl' => ['name' => 'Oceanië', 'slug' => 'oceanie'],
                'el' => ['name' => 'Ωκεανία', 'slug' => 'okeania'],
                'sq' => ['name' => 'Oqeania', 'slug' => 'oqeania'],
            ],
            'SA' => [
                'en' => ['name' => 'South America', 'slug' => 'south-america'],
                'sl' => ['name' => 'Južna Amerika', 'slug' => 'juzna-amerika'],
                'de' => ['name' => 'Südamerika', 'slug' => 'sudamerika'],
                'hr' => ['name' => 'Južna Amerika', 'slug' => 'juzna-amerika'],
                'fr' => ['name' => 'Amérique du Sud', 'slug' => 'amerique-du-sud'],
                'es' => ['name' => 'América del Sur', 'slug' => 'america-del-sur'],
                'it' => ['name' => 'America del Sud', 'slug' => 'america-del-sud'],
                'ua' => ['name' => 'Південна Америка', 'slug' => 'pivdenna-amerika'],
                'ru' => ['name' => 'Южная Америка', 'slug' => 'juzhnaja-amerika'],
                'pl' => ['name' => 'Ameryka Południowa', 'slug' => 'ameryka-poludniowa'],
                'cs' => ['name' => 'Jižní Amerika', 'slug' => 'jizni-amerika'],
                'hu' => ['name' => 'Dél-Amerika', 'slug' => 'del-amerika'],
                'bg' => ['name' => 'Южна Америка', 'slug' => 'juzhna-amerika'],
                'sk' => ['name' => 'Južná Amerika', 'slug' => 'juzna-amerika'],
                'da' => ['name' => 'Sydamerika', 'slug' => 'sydamerika'],
                'pt' => ['name' => 'América do Sul', 'slug' => 'america-do-sul'],
                'nl' => ['name' => 'Zuid-Amerika', 'slug' => 'zuid-amerika'],
                'el' => ['name' => 'Νότια Αμερική', 'slug' => 'notia-ameriki'],
                'sq' => ['name' => 'Amerika e Jugut', 'slug' => 'amerika-e-jugut'],
            ],
        ];
    }
}

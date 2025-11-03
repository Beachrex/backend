<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\RegionTranslation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Region data extracted from old database
        // This seeder creates regions with English translations only
        // Add translations in other languages as needed for important regions

        $regions = [
            // Croatia (HR)
            ['code' => 'dalmatia-dubrovnik', 'country_code' => 'HR', 'name' => 'Dalmatia - Dubrovnik'],
            ['code' => 'dalmatia-sibenik', 'country_code' => 'HR', 'name' => 'Dalmatia - Šibenik'],
            ['code' => 'dalmatia-split', 'country_code' => 'HR', 'name' => 'Dalmatia - Split'],
            ['code' => 'dalmatia-zadar', 'country_code' => 'HR', 'name' => 'Dalmatia - Zadar'],
            ['code' => 'istra', 'country_code' => 'HR', 'name' => 'Istra'],
            ['code' => 'kvarner', 'country_code' => 'HR', 'name' => 'Kvarner'],
            ['code' => 'lika', 'country_code' => 'HR', 'name' => 'Lika'],

            // Portugal (PT)
            ['code' => 'algarve', 'country_code' => 'PT', 'name' => 'Algarve'],
            ['code' => 'beja', 'country_code' => 'PT', 'name' => 'Beja'],
            ['code' => 'coimbra', 'country_code' => 'PT', 'name' => 'Coimbra'],
            ['code' => 'leiria', 'country_code' => 'PT', 'name' => 'Leiria'],
            ['code' => 'lisboa', 'country_code' => 'PT', 'name' => 'Lisboa'],
            ['code' => 'setubal', 'country_code' => 'PT', 'name' => 'Setubal'],

            // Spain (ES)
            ['code' => 'ambit-metropolita', 'country_code' => 'ES', 'name' => 'Àmbit metropolità'],
            ['code' => 'andalucia', 'country_code' => 'ES', 'name' => 'Andalucia'],
            ['code' => 'asturias', 'country_code' => 'ES', 'name' => 'Asturias'],
            ['code' => 'camp-de-tarragona', 'country_code' => 'ES', 'name' => 'Camp de Tarragona'],
            ['code' => 'cantabria', 'country_code' => 'ES', 'name' => 'Cantabria'],
            ['code' => 'comarques-gironines', 'country_code' => 'ES', 'name' => 'Comarques gironines'],
            ['code' => 'murcia', 'country_code' => 'ES', 'name' => 'Murcia'],
            ['code' => 'pais-vasco', 'country_code' => 'ES', 'name' => 'País Vasco'],
            ['code' => 'valencia', 'country_code' => 'ES', 'name' => 'Valencia'],

            // France (FR)
            ['code' => 'alpes-maritimes', 'country_code' => 'FR', 'name' => 'Alpes-Maritimes'],
            ['code' => 'bretagne', 'country_code' => 'FR', 'name' => 'Bretagne'],
            ['code' => 'hauts-de-france', 'country_code' => 'FR', 'name' => 'Hauts-de-France'],
            ['code' => 'normandie', 'country_code' => 'FR', 'name' => 'Normandie'],
            ['code' => 'nouvelle-aquitaine', 'country_code' => 'FR', 'name' => 'Nouvelle-Aquitaine'],
            ['code' => 'pays-de-la-loire', 'country_code' => 'FR', 'name' => 'Pays de la Loire'],
            ['code' => 'var', 'country_code' => 'FR', 'name' => 'Var'],

            // Greece (GR)
            ['code' => 'anatoliki-makedonia-kai-thraki', 'country_code' => 'GR', 'name' => 'Anatoliki Makedonia kai Thraki'],
            ['code' => 'attika', 'country_code' => 'GR', 'name' => 'Attika'],
            ['code' => 'attiki', 'country_code' => 'GR', 'name' => 'Attiki'],
            ['code' => 'dytiki-ellada', 'country_code' => 'GR', 'name' => 'Dytiki Ellada'],
            ['code' => 'ionioi-nisoi', 'country_code' => 'GR', 'name' => 'Iónioi Nísoi'],
            ['code' => 'ipeiros', 'country_code' => 'GR', 'name' => 'Ipeiros'],
            ['code' => 'kentriki-makedonia', 'country_code' => 'GR', 'name' => 'Kentriki Makedonia'],
            ['code' => 'peloponnisos', 'country_code' => 'GR', 'name' => 'Peloponnisos'],
            ['code' => 'sterea-ellada', 'country_code' => 'GR', 'name' => 'Sterea Ellada'],
            ['code' => 'thessalia', 'country_code' => 'GR', 'name' => 'Thessalia'],

            // Montenegro (ME)
            ['code' => 'bar', 'country_code' => 'ME', 'name' => 'Bar'],
            ['code' => 'budva', 'country_code' => 'ME', 'name' => 'Budva'],
            ['code' => 'herceg-novi', 'country_code' => 'ME', 'name' => 'Herceg Novi'],
            ['code' => 'kotar', 'country_code' => 'ME', 'name' => 'Kotar'],
            ['code' => 'kotor', 'country_code' => 'ME', 'name' => 'Kotor'],
            ['code' => 'tivat', 'country_code' => 'ME', 'name' => 'Tivat'],
            ['code' => 'ulcinj', 'country_code' => 'ME', 'name' => 'Ulcinj'],

            // Albania (AL)
            ['code' => 'durres', 'country_code' => 'AL', 'name' => 'Durrës'],
            ['code' => 'fier', 'country_code' => 'AL', 'name' => 'Fier'],
            ['code' => 'lezhe', 'country_code' => 'AL', 'name' => 'Lezhë'],
            ['code' => 'shkoder', 'country_code' => 'AL', 'name' => 'Shkodër'],
            ['code' => 'tirane', 'country_code' => 'AL', 'name' => 'Tiranë'],
            ['code' => 'vlore', 'country_code' => 'AL', 'name' => 'Vlorë'],

            // Italy (IT)
            ['code' => 'emilia-romagna', 'country_code' => 'IT', 'name' => 'Emilia-Romagna'],
            ['code' => 'friuli-venezia-giulia', 'country_code' => 'IT', 'name' => 'Friuli-Venezia Giulia'],
            ['code' => 'lazio', 'country_code' => 'IT', 'name' => 'Lazio'],
            ['code' => 'liguria', 'country_code' => 'IT', 'name' => 'Liguria'],
            ['code' => 'nuoro', 'country_code' => 'IT', 'name' => 'Nuoro'],
            ['code' => 'oristano', 'country_code' => 'IT', 'name' => 'Oristano'],
            ['code' => 'sassari', 'country_code' => 'IT', 'name' => 'Sassari'],
            ['code' => 'toscana', 'country_code' => 'IT', 'name' => 'Toscana'],
            ['code' => 'veneto', 'country_code' => 'IT', 'name' => 'Veneto'],

            // Netherlands (NL)
            ['code' => 'flevoland', 'country_code' => 'NL', 'name' => 'Flevoland'],
            ['code' => 'friesland', 'country_code' => 'NL', 'name' => 'Friesland'],
            ['code' => 'groningen', 'country_code' => 'NL', 'name' => 'Groningen'],
            ['code' => 'noord-holland', 'country_code' => 'NL', 'name' => 'Noord-Holland'],
            ['code' => 'zeeland', 'country_code' => 'NL', 'name' => 'Zeeland'],
            ['code' => 'zuid-holland', 'country_code' => 'NL', 'name' => 'Zuid-Holland'],

            // Germany (DE)
            ['code' => 'mecklenburg-vorpommern', 'country_code' => 'DE', 'name' => 'Mecklenburg-Vorpommern'],
            ['code' => 'niedersachsen', 'country_code' => 'DE', 'name' => 'Niedersachsen'],
            ['code' => 'schleswig-holstein', 'country_code' => 'DE', 'name' => 'Schleswig-Holstein'],

            // Denmark (DK)
            ['code' => 'midtjylland', 'country_code' => 'DK', 'name' => 'Midtjylland'],
            ['code' => 'nordjylland', 'country_code' => 'DK', 'name' => 'Nordjylland'],
            ['code' => 'syddanmark', 'country_code' => 'DK', 'name' => 'Syddanmark'],

            // Belgium (BE)
            ['code' => 'limburg', 'country_code' => 'BE', 'name' => 'Limburg'],
            ['code' => 'oost-vlaanderen', 'country_code' => 'BE', 'name' => 'Oost-Vlaanderen'],
            ['code' => 'provincie-antwerpen', 'country_code' => 'BE', 'name' => 'Provincie Antwerpen'],
            ['code' => 'west-vlaanderen', 'country_code' => 'BE', 'name' => 'West-Vlaanderen'],

            // Slovenia (SI)
            ['code' => 'obalno-kraska', 'country_code' => 'SI', 'name' => 'Obalno kraška'],
            ['code' => 'stajerska', 'country_code' => 'SI', 'name' => 'Štajerska'],
        ];

        foreach ($regions as $regionData) {
            $region = Region::updateOrCreate(
                ['code' => $regionData['code']],
                [
                    'country_code' => $regionData['country_code'],
                    'status_id' => 1,
                ]
            );

            RegionTranslation::updateOrCreate(
                ['region_id' => $region->id, 'locale' => 'en'],
                [
                    'name' => $regionData['name'],
                    'slug' => $regionData['code'],
                ]
            );
        }

        $this->command->info('Regions seeded successfully with English translations!');
        $this->command->info('Total regions: ' . count($regions));
        $this->command->info('Add translations in other languages as needed for important regions.');
    }
}

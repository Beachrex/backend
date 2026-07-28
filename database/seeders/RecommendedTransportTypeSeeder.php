<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecommendedTransportTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('recommended_transport_type')->count() > 0) {
                $this->command->info('recommended_transport_type table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $transportTypes = [
            ['id' => 1, 'code' => 'walk'],
            ['id' => 2, 'code' => 'bike'],
            ['id' => 3, 'code' => 'vehicle'],
            ['id' => 4, 'code' => 'off_road_vehicle'],
            ['id' => 5, 'code' => 'camper'],
            ['id' => 6, 'code' => 'boat'],
        ];

        $translations = [
            'walk' => [
                'en' => ['name' => 'Walk', 'slug' => 'walk'],
                'sl' => ['name' => 'Hoja', 'slug' => 'hoja'],
                'de' => ['name' => 'Zu Fuß', 'slug' => 'zu-fuss'],
                'hr' => ['name' => 'Pješice', 'slug' => 'pjesice'],
                'fr' => ['name' => 'À Pied', 'slug' => 'a-pied'],
                'es' => ['name' => 'A Pie', 'slug' => 'a-pie'],
                'it' => ['name' => 'A Piedi', 'slug' => 'a-piedi'],
                'ua' => ['name' => 'Пішки', 'slug' => 'pishky'],
                'ru' => ['name' => 'Пешком', 'slug' => 'peshkom'],
                'pl' => ['name' => 'Pieszo', 'slug' => 'pieszo'],
                'cs' => ['name' => 'Pěšky', 'slug' => 'pesky'],
                'hu' => ['name' => 'Gyalog', 'slug' => 'gyalog'],
                'bg' => ['name' => 'Пеша', 'slug' => 'pesha'],
                'sk' => ['name' => 'Pešo', 'slug' => 'peso'],
                'da' => ['name' => 'Til Fods', 'slug' => 'til-fods'],
                'pt' => ['name' => 'A Pé', 'slug' => 'a-pe'],
                'nl' => ['name' => 'Te Voet', 'slug' => 'te-voet'],
                'el' => ['name' => 'Με τα Πόδια', 'slug' => 'me-ta-podia'],
                'sq' => ['name' => 'Këmbë', 'slug' => 'kembe'],
            ],
            'bike' => [
                'en' => ['name' => 'Bike', 'slug' => 'bike'],
                'sl' => ['name' => 'Kolo', 'slug' => 'kolo'],
                'de' => ['name' => 'Fahrrad', 'slug' => 'fahrrad'],
                'hr' => ['name' => 'Bicikl', 'slug' => 'bicikl'],
                'fr' => ['name' => 'Vélo', 'slug' => 'velo'],
                'es' => ['name' => 'Bicicleta', 'slug' => 'bicicleta'],
                'it' => ['name' => 'Bicicletta', 'slug' => 'bicicletta'],
                'ua' => ['name' => 'Велосипед', 'slug' => 'velosyped'],
                'ru' => ['name' => 'Велосипед', 'slug' => 'velosiped'],
                'pl' => ['name' => 'Rower', 'slug' => 'rower'],
                'cs' => ['name' => 'Kolo', 'slug' => 'kolo'],
                'hu' => ['name' => 'Kerékpár', 'slug' => 'kerekpar'],
                'bg' => ['name' => 'Велосипед', 'slug' => 'velosiped'],
                'sk' => ['name' => 'Bicykel', 'slug' => 'bicykel'],
                'da' => ['name' => 'Cykel', 'slug' => 'cykel'],
                'pt' => ['name' => 'Bicicleta', 'slug' => 'bicicleta'],
                'nl' => ['name' => 'Fiets', 'slug' => 'fiets'],
                'el' => ['name' => 'Ποδήλατο', 'slug' => 'podilato'],
                'sq' => ['name' => 'Biçikletë', 'slug' => 'biciklete'],
            ],
            'vehicle' => [
                'en' => ['name' => 'Vehicle', 'slug' => 'vehicle'],
                'sl' => ['name' => 'Vozilo', 'slug' => 'vozilo'],
                'de' => ['name' => 'Fahrzeug', 'slug' => 'fahrzeug'],
                'hr' => ['name' => 'Vozilo', 'slug' => 'vozilo'],
                'fr' => ['name' => 'Véhicule', 'slug' => 'vehicule'],
                'es' => ['name' => 'Vehículo', 'slug' => 'vehiculo'],
                'it' => ['name' => 'Veicolo', 'slug' => 'veicolo'],
                'ua' => ['name' => 'Транспортний Засіб', 'slug' => 'transportnyi-zasib'],
                'ru' => ['name' => 'Транспортное Средство', 'slug' => 'transportnoe-sredstvo'],
                'pl' => ['name' => 'Pojazd', 'slug' => 'pojazd'],
                'cs' => ['name' => 'Vozidlo', 'slug' => 'vozidlo'],
                'hu' => ['name' => 'Jármű', 'slug' => 'jarmu'],
                'bg' => ['name' => 'Превозно Средство', 'slug' => 'prevozno-sredstvo'],
                'sk' => ['name' => 'Vozidlo', 'slug' => 'vozidlo'],
                'da' => ['name' => 'Køretøj', 'slug' => 'koretoj'],
                'pt' => ['name' => 'Veículo', 'slug' => 'veiculo'],
                'nl' => ['name' => 'Voertuig', 'slug' => 'voertuig'],
                'el' => ['name' => 'Όχημα', 'slug' => 'ochima'],
                'sq' => ['name' => 'Mjet', 'slug' => 'mjet'],
            ],
            'off_road_vehicle' => [
                'en' => ['name' => 'Off-Road Vehicle', 'slug' => 'off-road-vehicle'],
                'sl' => ['name' => 'Terensko Vozilo', 'slug' => 'terensko-vozilo'],
                'de' => ['name' => 'Geländewagen', 'slug' => 'gelandewagen'],
                'hr' => ['name' => 'Terensko Vozilo', 'slug' => 'terensko-vozilo'],
                'fr' => ['name' => 'Véhicule Tout-Terrain', 'slug' => 'vehicule-tout-terrain'],
                'es' => ['name' => 'Vehículo Todoterreno', 'slug' => 'vehiculo-todoterreno'],
                'it' => ['name' => 'Veicolo Fuoristrada', 'slug' => 'veicolo-fuoristrada'],
                'ua' => ['name' => 'Позашляховик', 'slug' => 'pozashliahovyk'],
                'ru' => ['name' => 'Внедорожник', 'slug' => 'vnedorozhnik'],
                'pl' => ['name' => 'Pojazd Terenowy', 'slug' => 'pojazd-terenowy'],
                'cs' => ['name' => 'Terénní Vozidlo', 'slug' => 'terenni-vozidlo'],
                'hu' => ['name' => 'Terepjáró', 'slug' => 'terepjaro'],
                'bg' => ['name' => 'Джип', 'slug' => 'dzhip'],
                'sk' => ['name' => 'Terénne Vozidlo', 'slug' => 'terenne-vozidlo'],
                'da' => ['name' => 'Terrængående Køretøj', 'slug' => 'terrengaende-koretoj'],
                'pt' => ['name' => 'Veículo Todo-o-Terreno', 'slug' => 'veiculo-todo-o-terreno'],
                'nl' => ['name' => 'Terreinvoertuig', 'slug' => 'terreinvoertuig'],
                'el' => ['name' => 'Όχημα Εκτός Δρόμου', 'slug' => 'ochima-ektos-dromou'],
                'sq' => ['name' => 'Mjet Terreni', 'slug' => 'mjet-terreni'],
            ],
            'camper' => [
                'en' => ['name' => 'Camper', 'slug' => 'camper'],
                'sl' => ['name' => 'Avtodom', 'slug' => 'avtodom'],
                'de' => ['name' => 'Wohnmobil', 'slug' => 'wohnmobil'],
                'hr' => ['name' => 'Kamper', 'slug' => 'kamper'],
                'fr' => ['name' => 'Camping-Car', 'slug' => 'camping-car'],
                'es' => ['name' => 'Autocaravana', 'slug' => 'autocaravana'],
                'it' => ['name' => 'Camper', 'slug' => 'camper'],
                'ua' => ['name' => 'Кемпер', 'slug' => 'kemper'],
                'ru' => ['name' => 'Кемпер', 'slug' => 'kemper'],
                'pl' => ['name' => 'Kamper', 'slug' => 'kamper'],
                'cs' => ['name' => 'Obytný Vůz', 'slug' => 'obytny-vuz'],
                'hu' => ['name' => 'Lakóautó', 'slug' => 'lakoauto'],
                'bg' => ['name' => 'Кемпер', 'slug' => 'kemper'],
                'sk' => ['name' => 'Obytný Voz', 'slug' => 'obytny-voz'],
                'da' => ['name' => 'Autocamper', 'slug' => 'autocamper'],
                'pt' => ['name' => 'Autocaravana', 'slug' => 'autocaravana'],
                'nl' => ['name' => 'Camper', 'slug' => 'camper'],
                'el' => ['name' => 'Αυτοκινούμενο', 'slug' => 'autokinitoumeno'],
                'sq' => ['name' => 'Kampër', 'slug' => 'kamper'],
            ],
            'boat' => [
                'en' => ['name' => 'Boat', 'slug' => 'boat'],
                'sl' => ['name' => 'Čoln', 'slug' => 'coln'],
                'de' => ['name' => 'Boot', 'slug' => 'boot'],
                'hr' => ['name' => 'Čamac', 'slug' => 'camac'],
                'fr' => ['name' => 'Bateau', 'slug' => 'bateau'],
                'es' => ['name' => 'Barco', 'slug' => 'barco'],
                'it' => ['name' => 'Barca', 'slug' => 'barca'],
                'ua' => ['name' => 'Човен', 'slug' => 'choven'],
                'ru' => ['name' => 'Лодка', 'slug' => 'lodka'],
                'pl' => ['name' => 'Łódź', 'slug' => 'lodz'],
                'cs' => ['name' => 'Loď', 'slug' => 'lod'],
                'hu' => ['name' => 'Csónak', 'slug' => 'csonak'],
                'bg' => ['name' => 'Лодка', 'slug' => 'lodka'],
                'sk' => ['name' => 'Loď', 'slug' => 'lod'],
                'da' => ['name' => 'Båd', 'slug' => 'bad'],
                'pt' => ['name' => 'Barco', 'slug' => 'barco'],
                'nl' => ['name' => 'Boot', 'slug' => 'boot'],
                'el' => ['name' => 'Σκάφος', 'slug' => 'skafos'],
                'sq' => ['name' => 'Varkë', 'slug' => 'varke'],
            ],
        ];

        foreach ($transportTypes as $typeData) {
            // Insert transport type with explicit ID
            DB::table('recommended_transport_type')->insert([
                'id' => $typeData['id'],
                'code' => $typeData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations if available
            if (isset($translations[$typeData['code']])) {
                foreach ($translations[$typeData['code']] as $locale => $translation) {
                    DB::table('recommended_transport_type_translation')->insert([
                        'recommended_transport_type_id' => $typeData['id'],
                        'locale' => $locale,
                        'name' => $translation['name'],
                        'slug' => $translation['slug'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Recommended transport types seeded successfully with translations in 14 languages!');
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NaturalLocationConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('natural_location_condition')->count() > 0) {
                $this->command->info('natural_location_condition table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $conditions = [
            ['id' => 1, 'code' => 'white_sand'],
            ['id' => 2, 'code' => 'brown_sand'],
            ['id' => 3, 'code' => 'black_sand'],
            ['id' => 4, 'code' => 'small_stones'],
            ['id' => 5, 'code' => 'big_stones'],
            ['id' => 6, 'code' => 'rocks'],
            ['id' => 7, 'code' => 'grass'],
            ['id' => 8, 'code' => 'natural_shade'],
            ['id' => 9, 'code' => 'sunshade'],
            ['id' => 10, 'code' => 'concrete'],
            ['id' => 11, 'code' => 'swamp'],
            ['id' => 12, 'code' => 'holes_caves'],
        ];

        $translations = [
            'white_sand' => [
                'en' => ['name' => 'White Sand', 'slug' => 'white-sand'],
                'sl' => ['name' => 'Bel Pesek', 'slug' => 'bel-pesek'],
                'de' => ['name' => 'Weißer Sand', 'slug' => 'weisser-sand'],
                'hr' => ['name' => 'Bijeli Pijesak', 'slug' => 'bijeli-pijesak'],
                'fr' => ['name' => 'Sable Blanc', 'slug' => 'sable-blanc'],
                'es' => ['name' => 'Arena Blanca', 'slug' => 'arena-blanca'],
                'it' => ['name' => 'Sabbia Bianca', 'slug' => 'sabbia-bianca'],
                'ua' => ['name' => 'Білий Пісок', 'slug' => 'bilyi-pisok'],
                'ru' => ['name' => 'Белый Песок', 'slug' => 'belyi-pesok'],
                'pl' => ['name' => 'Biały Piasek', 'slug' => 'bialy-piasek'],
                'cs' => ['name' => 'Bílý Písek', 'slug' => 'bily-pisek'],
                'hu' => ['name' => 'Fehér Homok', 'slug' => 'feher-homok'],
                'bg' => ['name' => 'Бял Пясък', 'slug' => 'bial-piasak'],
                'sk' => ['name' => 'Biely Piesok', 'slug' => 'biely-piesok'],
            ],
            'brown_sand' => [
                'en' => ['name' => 'Brown Sand', 'slug' => 'brown-sand'],
                'sl' => ['name' => 'Rjav Pesek', 'slug' => 'rjav-pesek'],
                'de' => ['name' => 'Brauner Sand', 'slug' => 'brauner-sand'],
                'hr' => ['name' => 'Smeđi Pijesak', 'slug' => 'smedi-pijesak'],
                'fr' => ['name' => 'Sable Brun', 'slug' => 'sable-brun'],
                'es' => ['name' => 'Arena Marrón', 'slug' => 'arena-marron'],
                'it' => ['name' => 'Sabbia Marrone', 'slug' => 'sabbia-marrone'],
                'ua' => ['name' => 'Коричневий Пісок', 'slug' => 'korychnevyi-pisok'],
                'ru' => ['name' => 'Коричневый Песок', 'slug' => 'korichnevyi-pesok'],
                'pl' => ['name' => 'Brązowy Piasek', 'slug' => 'brazowy-piasek'],
                'cs' => ['name' => 'Hnědý Písek', 'slug' => 'hnedy-pisek'],
                'hu' => ['name' => 'Barna Homok', 'slug' => 'barna-homok'],
                'bg' => ['name' => 'Кафяв Пясък', 'slug' => 'kafiav-piasak'],
                'sk' => ['name' => 'Hnedý Piesok', 'slug' => 'hnedy-piesok'],
            ],
            'black_sand' => [
                'en' => ['name' => 'Black Sand', 'slug' => 'black-sand'],
                'sl' => ['name' => 'Črn Pesek', 'slug' => 'crn-pesek'],
                'de' => ['name' => 'Schwarzer Sand', 'slug' => 'schwarzer-sand'],
                'hr' => ['name' => 'Crni Pijesak', 'slug' => 'crni-pijesak'],
                'fr' => ['name' => 'Sable Noir', 'slug' => 'sable-noir'],
                'es' => ['name' => 'Arena Negra', 'slug' => 'arena-negra'],
                'it' => ['name' => 'Sabbia Nera', 'slug' => 'sabbia-nera'],
                'ua' => ['name' => 'Чорний Пісок', 'slug' => 'chornyi-pisok'],
                'ru' => ['name' => 'Черный Песок', 'slug' => 'chernyi-pesok'],
                'pl' => ['name' => 'Czarny Piasek', 'slug' => 'czarny-piasek'],
                'cs' => ['name' => 'Černý Písek', 'slug' => 'cerny-pisek'],
                'hu' => ['name' => 'Fekete Homok', 'slug' => 'fekete-homok'],
                'bg' => ['name' => 'Черен Пясък', 'slug' => 'cheren-piasak'],
                'sk' => ['name' => 'Čierny Piesok', 'slug' => 'cierny-piesok'],
            ],
            'small_stones' => [
                'en' => ['name' => 'Small Stones', 'slug' => 'small-stones'],
                'sl' => ['name' => 'Majhni Kamni', 'slug' => 'majhni-kamni'],
                'de' => ['name' => 'Kleine Steine', 'slug' => 'kleine-steine'],
                'hr' => ['name' => 'Mali Kamenčići', 'slug' => 'mali-kamencici'],
                'fr' => ['name' => 'Petits Cailloux', 'slug' => 'petits-cailloux'],
                'es' => ['name' => 'Piedras Pequeñas', 'slug' => 'piedras-pequenas'],
                'it' => ['name' => 'Sassolini', 'slug' => 'sassolini'],
                'ua' => ['name' => 'Дрібна Галька', 'slug' => 'dribna-halka'],
                'ru' => ['name' => 'Мелкая Галька', 'slug' => 'melkaya-galka'],
                'pl' => ['name' => 'Małe Kamienie', 'slug' => 'male-kamienie'],
                'cs' => ['name' => 'Malé Kameny', 'slug' => 'male-kameny'],
                'hu' => ['name' => 'Apró Kövek', 'slug' => 'apro-kovek'],
                'bg' => ['name' => 'Малки Камъни', 'slug' => 'malki-kamani'],
                'sk' => ['name' => 'Malé Kamene', 'slug' => 'male-kamene'],
            ],
            'big_stones' => [
                'en' => ['name' => 'Big Stones', 'slug' => 'big-stones'],
                'sl' => ['name' => 'Veliki Kamni', 'slug' => 'veliki-kamni'],
                'de' => ['name' => 'Große Steine', 'slug' => 'grosse-steine'],
                'hr' => ['name' => 'Veliki Kameni', 'slug' => 'veliki-kameni'],
                'fr' => ['name' => 'Gros Cailloux', 'slug' => 'gros-cailloux'],
                'es' => ['name' => 'Piedras Grandes', 'slug' => 'piedras-grandes'],
                'it' => ['name' => 'Sassi Grandi', 'slug' => 'sassi-grandi'],
                'ua' => ['name' => 'Велика Галька', 'slug' => 'velyka-halka'],
                'ru' => ['name' => 'Крупная Галька', 'slug' => 'krupnaya-galka'],
                'pl' => ['name' => 'Duże Kamienie', 'slug' => 'duze-kamienie'],
                'cs' => ['name' => 'Velké Kameny', 'slug' => 'velke-kameny'],
                'hu' => ['name' => 'Nagy Kövek', 'slug' => 'nagy-kovek'],
                'bg' => ['name' => 'Големи Камъни', 'slug' => 'golemi-kamani'],
                'sk' => ['name' => 'Veľké Kamene', 'slug' => 'velke-kamene'],
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
            'natural_shade' => [
                'en' => ['name' => 'Natural Shade', 'slug' => 'natural-shade'],
                'sl' => ['name' => 'Naravna Senca', 'slug' => 'naravna-senca'],
                'de' => ['name' => 'Natürlicher Schatten', 'slug' => 'naturlicher-schatten'],
                'hr' => ['name' => 'Prirodna Hladovina', 'slug' => 'prirodna-hladovina'],
                'fr' => ['name' => 'Ombre Naturelle', 'slug' => 'ombre-naturelle'],
                'es' => ['name' => 'Sombra Natural', 'slug' => 'sombra-natural'],
                'it' => ['name' => 'Ombra Naturale', 'slug' => 'ombra-naturale'],
                'ua' => ['name' => 'Природна Тінь', 'slug' => 'pryrodna-tin'],
                'ru' => ['name' => 'Природная Тень', 'slug' => 'prirodnaya-ten'],
                'pl' => ['name' => 'Naturalny Cień', 'slug' => 'naturalny-cien'],
                'cs' => ['name' => 'Přirozený Stín', 'slug' => 'prirozeny-stin'],
                'hu' => ['name' => 'Természetes Árnyék', 'slug' => 'termeszetes-arnyek'],
                'bg' => ['name' => 'Естествена Сянка', 'slug' => 'estestvena-sianka'],
                'sk' => ['name' => 'Prírodný Tieň', 'slug' => 'prirodny-tien'],
            ],
            'sunshade' => [
                'en' => ['name' => 'Sunshade', 'slug' => 'sunshade'],
                'sl' => ['name' => 'Senčnik', 'slug' => 'sencnik'],
                'de' => ['name' => 'Sonnenschirm', 'slug' => 'sonnenschirm'],
                'hr' => ['name' => 'Suncobran', 'slug' => 'suncobran'],
                'fr' => ['name' => 'Parasol', 'slug' => 'parasol'],
                'es' => ['name' => 'Sombrilla', 'slug' => 'sombrilla'],
                'it' => ['name' => 'Ombrellone', 'slug' => 'ombrellone'],
                'ua' => ['name' => 'Парасолька', 'slug' => 'parasolka'],
                'ru' => ['name' => 'Зонтик', 'slug' => 'zontik'],
                'pl' => ['name' => 'Parasol', 'slug' => 'parasol'],
                'cs' => ['name' => 'Slunečník', 'slug' => 'slunecnik'],
                'hu' => ['name' => 'Napernyő', 'slug' => 'napernyo'],
                'bg' => ['name' => 'Чадър', 'slug' => 'chadar'],
                'sk' => ['name' => 'Slnečník', 'slug' => 'slnecnik'],
            ],
            'concrete' => [
                'en' => ['name' => 'Concrete', 'slug' => 'concrete'],
                'sl' => ['name' => 'Beton', 'slug' => 'beton'],
                'de' => ['name' => 'Beton', 'slug' => 'beton'],
                'hr' => ['name' => 'Beton', 'slug' => 'beton'],
                'fr' => ['name' => 'Béton', 'slug' => 'beton'],
                'es' => ['name' => 'Hormigón', 'slug' => 'hormigon'],
                'it' => ['name' => 'Cemento', 'slug' => 'cemento'],
                'ua' => ['name' => 'Бетон', 'slug' => 'beton'],
                'ru' => ['name' => 'Бетон', 'slug' => 'beton'],
                'pl' => ['name' => 'Beton', 'slug' => 'beton'],
                'cs' => ['name' => 'Beton', 'slug' => 'beton'],
                'hu' => ['name' => 'Beton', 'slug' => 'beton'],
                'bg' => ['name' => 'Бетон', 'slug' => 'beton'],
                'sk' => ['name' => 'Betón', 'slug' => 'beton'],
            ],
            'swamp' => [
                'en' => ['name' => 'Swamp', 'slug' => 'swamp'],
                'sl' => ['name' => 'Močvirje', 'slug' => 'mocvirje'],
                'de' => ['name' => 'Sumpf', 'slug' => 'sumpf'],
                'hr' => ['name' => 'Močvara', 'slug' => 'mocvara'],
                'fr' => ['name' => 'Marais', 'slug' => 'marais'],
                'es' => ['name' => 'Pantano', 'slug' => 'pantano'],
                'it' => ['name' => 'Palude', 'slug' => 'palude'],
                'ua' => ['name' => 'Болото', 'slug' => 'boloto'],
                'ru' => ['name' => 'Болото', 'slug' => 'boloto'],
                'pl' => ['name' => 'Bagno', 'slug' => 'bagno'],
                'cs' => ['name' => 'Bažina', 'slug' => 'bazina'],
                'hu' => ['name' => 'Mocsár', 'slug' => 'mocsar'],
                'bg' => ['name' => 'Блато', 'slug' => 'blato'],
                'sk' => ['name' => 'Močiar', 'slug' => 'mociar'],
            ],
            'holes_caves' => [
                'en' => ['name' => 'Holes/Caves', 'slug' => 'holes-caves'],
                'sl' => ['name' => 'Luknje/Jame', 'slug' => 'luknje-jame'],
                'de' => ['name' => 'Löcher/Höhlen', 'slug' => 'locher-hohlen'],
                'hr' => ['name' => 'Rupe/Špilje', 'slug' => 'rupe-spilje'],
                'fr' => ['name' => 'Trous/Grottes', 'slug' => 'trous-grottes'],
                'es' => ['name' => 'Agujeros/Cuevas', 'slug' => 'agujeros-cuevas'],
                'it' => ['name' => 'Buchi/Grotte', 'slug' => 'buchi-grotte'],
                'ua' => ['name' => 'Ями/Печери', 'slug' => 'yamy-pechery'],
                'ru' => ['name' => 'Ямы/Пещеры', 'slug' => 'yamy-peshchery'],
                'pl' => ['name' => 'Dziury/Jaskinie', 'slug' => 'dziury-jaskinie'],
                'cs' => ['name' => 'Díry/Jeskyně', 'slug' => 'diry-jeskyne'],
                'hu' => ['name' => 'Lyukak/Barlangok', 'slug' => 'lyukak-barlangok'],
                'bg' => ['name' => 'Дупки/Пещери', 'slug' => 'dupki-peshteri'],
                'sk' => ['name' => 'Diery/Jaskyne', 'slug' => 'diery-jaskyne'],
            ],
        ];

        foreach ($conditions as $conditionData) {
            // Insert condition with explicit ID
            DB::table('natural_location_condition')->insert([
                'id' => $conditionData['id'],
                'code' => $conditionData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations if available
            if (isset($translations[$conditionData['code']])) {
                foreach ($translations[$conditionData['code']] as $locale => $translation) {
                    DB::table('natural_location_condition_translation')->insert([
                        'natural_location_condition_id' => $conditionData['id'],
                        'locale' => $locale,
                        'name' => $translation['name'],
                        'slug' => $translation['slug'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Natural location conditions seeded successfully with translations in 14 languages!');
    }
}


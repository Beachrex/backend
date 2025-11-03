<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleTranslation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 16 articles total (4 for each entity type: country, region, island, city)
        $articles = [
            // Country articles (4)
            [
                'code' => 'country-coastal-heritage',
                'category' => 'History',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Coastal Heritage and Maritime History',
                        'slug' => 'coastal-heritage-maritime-history',
                        'excerpt' => 'Discover the rich maritime history and coastal heritage of the Adriatic region.',
                        'content' => '<p>The Adriatic coast has been a crossroads of civilizations for millennia. From ancient Greek colonies to Venetian trading posts, the maritime heritage shapes the identity of coastal communities.</p><p>Historic ports, lighthouses, and shipbuilding traditions tell the story of a region deeply connected to the sea.</p>',
                    ],
                    'hr' => [
                        'title' => 'Obalna baština i pomorska povijest',
                        'slug' => 'obalna-bastina-pomorska-povijest',
                        'excerpt' => 'Otkrijte bogatu pomorsku povijest i obalnu baštinu Jadranskog područja.',
                        'content' => '<p>Jadranska obala je tisućljećima bila raskrižje civilizacija. Od antičkih grčkih kolonija do mletačkih trgovačkih postaja, pomorska baština oblikuje identitet obalnih zajednica.</p><p>Povijesne luke, svjetionici i tradicija brodogradnje pričaju priču o regiji duboko povezanoj s morem.</p>',
                    ],
                ],
            ],
            [
                'code' => 'country-mediterranean-cuisine',
                'category' => 'Cuisine',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Mediterranean Cuisine and Local Flavors',
                        'slug' => 'mediterranean-cuisine-local-flavors',
                        'excerpt' => 'Explore the delicious Mediterranean cuisine and traditional recipes of the region.',
                        'content' => '<p>Mediterranean cuisine is renowned for its fresh ingredients, olive oil, and seafood. Local specialties include grilled fish, octopus salad, and traditional pastries.</p><p>The region\'s culinary traditions have been shaped by centuries of cultural exchange and local agricultural practices.</p>',
                    ],
                    'hr' => [
                        'title' => 'Mediteranska kuhinja i lokalni okusi',
                        'slug' => 'mediteranska-kuhinja-lokalni-okusi',
                        'excerpt' => 'Istražite ukusnu mediteransku kuhinju i tradicionalne recepte regije.',
                        'content' => '<p>Mediteranska kuhinja poznata je po svježim sastojcima, maslinovom ulju i morskim plodovima. Lokalni specijaliteti uključuju ribu na žaru, salatu od hobotnice i tradicionalne kolače.</p><p>Kulinarske tradicije regije oblikovane su stoljećima kulturne razmjene i lokalnih poljoprivrednih praksi.</p>',
                    ],
                ],
            ],
            [
                'code' => 'country-cultural-festivals',
                'category' => 'Culture',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Cultural Festivals and Traditions',
                        'slug' => 'cultural-festivals-traditions',
                        'excerpt' => 'Experience the vibrant cultural festivals and traditions of the Adriatic coast.',
                        'content' => '<p>The Adriatic region hosts numerous cultural festivals throughout the year, celebrating music, dance, and local traditions.</p><p>From summer music festivals to traditional folk celebrations, these events showcase the rich cultural heritage of coastal communities.</p>',
                    ],
                    'hr' => [
                        'title' => 'Kulturni festivali i tradicije',
                        'slug' => 'kulturni-festivali-tradicije',
                        'excerpt' => 'Doživite živahne kulturne festivale i tradicije jadranske obale.',
                        'content' => '<p>Jadransko područje tijekom godine ugošćuje brojne kulturne festivale koji slave glazbu, ples i lokalne tradicije.</p><p>Od ljetnih glazbenih festivala do tradicionalnih narodnih proslava, ovi događaji prikazuju bogatu kulturnu baštinu obalnih zajednica.</p>',
                    ],
                ],
            ],
            [
                'code' => 'country-natural-beauty',
                'category' => 'Sights',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Natural Beauty and Scenic Landscapes',
                        'slug' => 'natural-beauty-scenic-landscapes',
                        'excerpt' => 'Discover the stunning natural beauty and scenic landscapes of the Adriatic coast.',
                        'content' => '<p>The Adriatic coast offers breathtaking natural beauty, from crystal-clear waters to dramatic cliffs and lush Mediterranean vegetation.</p><p>National parks, nature reserves, and protected areas preserve the region\'s unique ecosystems and biodiversity.</p>',
                    ],
                    'hr' => [
                        'title' => 'Prirodne ljepote i slikoviti krajolici',
                        'slug' => 'prirodne-ljepote-slikoviti-krajolici',
                        'excerpt' => 'Otkrijte zadivljujuće prirodne ljepote i slikovite krajolike jadranske obale.',
                        'content' => '<p>Jadranska obala nudi zadivljujuću prirodnu ljepotu, od kristalno čistog mora do dramatičnih litica i bujne mediteranske vegetacije.</p><p>Nacionalni parkovi, rezervati prirode i zaštićena područja čuvaju jedinstvene ekosustave i bioraznolikost regije.</p>',
                    ],
                ],
            ],

            // Region articles (4)
            [
                'code' => 'region-local-heritage',
                'category' => 'History',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Local Heritage and Historic Sites',
                        'slug' => 'local-heritage-historic-sites',
                        'excerpt' => 'Explore the local heritage and historic sites of this unique region.',
                        'content' => '<p>This region is home to numerous historic sites, from ancient ruins to medieval fortresses. Each monument tells a story of the area\'s rich past.</p><p>Archaeological discoveries continue to reveal new insights into the region\'s ancient civilizations and cultural development.</p>',
                    ],
                    'hr' => [
                        'title' => 'Lokalna baština i povijesna mjesta',
                        'slug' => 'lokalna-bastina-povijesna-mjesta',
                        'excerpt' => 'Istražite lokalnu baštinu i povijesna mjesta ove jedinstvene regije.',
                        'content' => '<p>Ova regija dom je brojnih povijesnih mjesta, od antičkih ruševina do srednjovjekovnih utvrda. Svaki spomenik priča priču o bogatoj prošlosti područja.</p><p>Arheološka otkrića nastavljaju otkrivati nove uvide u drevne civilizacije i kulturni razvoj regije.</p>',
                    ],
                ],
            ],
            [
                'code' => 'region-gastronomy',
                'category' => 'Cuisine',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Regional Gastronomy and Specialties',
                        'slug' => 'regional-gastronomy-specialties',
                        'excerpt' => 'Taste the unique regional gastronomy and local specialties.',
                        'content' => '<p>The region\'s gastronomy is characterized by fresh local ingredients and traditional cooking methods passed down through generations.</p><p>Local specialties include seafood dishes, homemade pasta, and regional wines that reflect the area\'s terroir.</p>',
                    ],
                    'hr' => [
                        'title' => 'Regionalna gastronomija i specijaliteti',
                        'slug' => 'regionalna-gastronomija-specijaliteti',
                        'excerpt' => 'Kušajte jedinstvenu regionalnu gastronomiju i lokalne specijalitete.',
                        'content' => '<p>Gastronomija regije karakterizirana je svježim lokalnim sastojcima i tradicionalnim načinima kuhanja koji se prenose generacijama.</p><p>Lokalni specijaliteti uključuju jela od morskih plodova, domaće tjestenine i regionalna vina koja odražavaju teruarregije.</p>',
                    ],
                ],
            ],
            [
                'code' => 'region-traditions',
                'category' => 'Culture',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Regional Traditions and Customs',
                        'slug' => 'regional-traditions-customs',
                        'excerpt' => 'Learn about the unique regional traditions and customs.',
                        'content' => '<p>The region maintains strong connections to its traditional customs, from folk music and dance to seasonal celebrations.</p><p>Local artisans continue to practice traditional crafts, preserving cultural heritage for future generations.</p>',
                    ],
                    'hr' => [
                        'title' => 'Regionalne tradicije i običaji',
                        'slug' => 'regionalne-tradicije-obicaji',
                        'excerpt' => 'Saznajte o jedinstvenim regionalnim tradicijama i običajima.',
                        'content' => '<p>Regija održava snažne veze sa svojim tradicionalnim običajima, od narodne glazbe i plesa do sezonskih proslava.</p><p>Lokalni obrtnici nastavljaju prakticirati tradicionalne obrte, čuvajući kulturnu baštinu za buduće generacije.</p>',
                    ],
                ],
            ],
            [
                'code' => 'region-attractions',
                'category' => 'Sights',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Regional Attractions and Points of Interest',
                        'slug' => 'regional-attractions-points-interest',
                        'excerpt' => 'Discover the top regional attractions and points of interest.',
                        'content' => '<p>The region offers diverse attractions, from natural wonders to cultural landmarks. Visitors can explore caves, waterfalls, and scenic viewpoints.</p><p>Historic towns and villages provide glimpses into traditional architecture and local way of life.</p>',
                    ],
                    'hr' => [
                        'title' => 'Regionalne atrakcije i zanimljivosti',
                        'slug' => 'regionalne-atrakcije-zanimljivosti',
                        'excerpt' => 'Otkrijte vrhunske regionalne atrakcije i zanimljivosti.',
                        'content' => '<p>Regija nudi raznolike atrakcije, od prirodnih čuda do kulturnih znamenitosti. Posjetitelji mogu istražiti špilje, slapove i slikovite vidikovce.</p><p>Povijesni gradovi i sela pružaju uvid u tradicionalnu arhitekturu i lokalni način života.</p>',
                    ],
                ],
            ],

            // Island articles (4)
            [
                'code' => 'island-history',
                'category' => 'History',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Island History and Ancient Settlements',
                        'slug' => 'island-history-ancient-settlements',
                        'excerpt' => 'Uncover the fascinating history and ancient settlements of the island.',
                        'content' => '<p>The island has been inhabited since prehistoric times, with evidence of ancient settlements and Roman ruins scattered across its landscape.</p><p>Medieval fortifications and historic churches showcase the island\'s strategic importance throughout history.</p>',
                    ],
                    'hr' => [
                        'title' => 'Povijest otoka i drevna naselja',
                        'slug' => 'povijest-otoka-drevna-naselja',
                        'excerpt' => 'Otkrijte fascinantnu povijest i drevna naselja otoka.',
                        'content' => '<p>Otok je bio naseljen od prapovijesti, s dokazima drevnih naselja i rimskih ruševina rasutih po njegovom krajoliku.</p><p>Srednjovjekovne utvrde i povijesne crkve prikazuju stratešku važnost otoka kroz povijest.</p>',
                    ],
                ],
            ],
            [
                'code' => 'island-cuisine',
                'category' => 'Cuisine',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Island Cuisine and Local Products',
                        'slug' => 'island-cuisine-local-products',
                        'excerpt' => 'Savor the unique island cuisine and locally produced delicacies.',
                        'content' => '<p>Island cuisine features fresh seafood, locally grown vegetables, and traditional recipes unique to the island.</p><p>Local products include olive oil, wine, honey, and cheese, all produced using traditional methods.</p>',
                    ],
                    'hr' => [
                        'title' => 'Otočka kuhinja i lokalni proizvodi',
                        'slug' => 'otocka-kuhinja-lokalni-proizvodi',
                        'excerpt' => 'Uživajte u jedinstveno otočkoj kuhinji i lokalno proizvedenim delicijama.',
                        'content' => '<p>Otočka kuhinja sadrži svježe plodove mora, lokalno uzgojeno povrće i tradicionalne recepte jedinstvene za otok.</p><p>Lokalni proizvodi uključuju maslinovo ulje, vino, med i sir, sve proizvedeno tradicionalnim metodama.</p>',
                    ],
                ],
            ],
            [
                'code' => 'island-culture',
                'category' => 'Culture',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Island Culture and Festivals',
                        'slug' => 'island-culture-festivals',
                        'excerpt' => 'Experience the vibrant island culture and traditional festivals.',
                        'content' => '<p>The island maintains a strong cultural identity with traditional festivals, folk music, and local customs celebrated throughout the year.</p><p>Summer festivals attract visitors from around the world, showcasing local talent and traditions.</p>',
                    ],
                    'hr' => [
                        'title' => 'Otočka kultura i festivali',
                        'slug' => 'otocka-kultura-festivali',
                        'excerpt' => 'Doživite živahnu otočku kulturu i tradicionalne festivale.',
                        'content' => '<p>Otok održava snažan kulturni identitet s tradicionalnim festivalima, narodnom glazbom i lokalnim običajima koji se slave tijekom godine.</p><p>Ljetni festivali privlače posjetitelje iz cijelog svijeta, prikazujući lokalne talente i tradicije.</p>',
                    ],
                ],
            ],
            [
                'code' => 'island-beaches',
                'category' => 'Sights',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Island Beaches and Natural Wonders',
                        'slug' => 'island-beaches-natural-wonders',
                        'excerpt' => 'Explore the stunning island beaches and natural wonders.',
                        'content' => '<p>The island boasts numerous beautiful beaches, from secluded coves to popular sandy shores. Crystal-clear waters make it perfect for swimming and diving.</p><p>Natural attractions include caves, hiking trails, and scenic viewpoints offering panoramic views of the Adriatic.</p>',
                    ],
                    'hr' => [
                        'title' => 'Otočke plaže i prirodne ljepote',
                        'slug' => 'otocke-plaze-prirodne-ljepote',
                        'excerpt' => 'Istražite zadivljujuće otočke plaže i prirodne ljepote.',
                        'content' => '<p>Otok se ponosi brojnim prekrasnim plažama, od skrivenih uvala do popularnih pješčanih obala. Kristalno čisto more čini ga savršenim za kupanje i ronjenje.</p><p>Prirodne atrakcije uključuju špilje, pješačke staze i slikovite vidikovce koji nude panoramski pogled na Jadran.</p>',
                    ],
                ],
            ],

            // City articles (4)
            [
                'code' => 'city-urban-history',
                'category' => 'History',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Urban History and Architectural Heritage',
                        'slug' => 'urban-history-architectural-heritage',
                        'excerpt' => 'Discover the rich urban history and architectural heritage of the city.',
                        'content' => '<p>The city\'s history spans centuries, with architectural styles ranging from Roman ruins to Venetian palaces and Austro-Hungarian buildings.</p><p>Historic city centers preserve medieval street layouts and showcase the evolution of urban planning through different eras.</p>',
                    ],
                    'hr' => [
                        'title' => 'Urbana povijest i arhitektonska baština',
                        'slug' => 'urbana-povijest-arhitektonska-bastina',
                        'excerpt' => 'Otkrijte bogatu urbanu povijest i arhitektonsku baštinu grada.',
                        'content' => '<p>Povijest grada obuhvaća stoljeća, s arhitektonskim stilovima koji sežu od rimskih ruševina do mletačkih palača i austro-ugarskih zgrada.</p><p>Povijesne gradske jezgre čuvaju srednjovjekovne rasporede ulica i prikazuju evoluciju urbanog planiranja kroz različita razdoblja.</p>',
                    ],
                ],
            ],
            [
                'code' => 'city-gastronomy',
                'category' => 'Cuisine',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'City Gastronomy and Dining Scene',
                        'slug' => 'city-gastronomy-dining-scene',
                        'excerpt' => 'Explore the vibrant city gastronomy and diverse dining scene.',
                        'content' => '<p>The city offers a diverse culinary scene, from traditional konobas serving local specialties to modern restaurants with innovative cuisine.</p><p>Fresh seafood markets, local bakeries, and wine bars provide authentic tastes of the region\'s gastronomic heritage.</p>',
                    ],
                    'hr' => [
                        'title' => 'Gradska gastronomija i kulinarstvo',
                        'slug' => 'gradska-gastronomija-kulinarstvo',
                        'excerpt' => 'Istražite živahnu gradsku gastronomiju i raznoliku kulinarsku scenu.',
                        'content' => '<p>Grad nudi raznoliku kulinarsku scenu, od tradicionalnih konoba koje poslužuju lokalne specijalitete do modernih restorana s inovativnom kuhinjom.</p><p>Tržnice svježih plodova mora, lokalne pekare i vinari pružaju autentične okuse gastronomske baštine regije.</p>',
                    ],
                ],
            ],
            [
                'code' => 'city-cultural-life',
                'category' => 'Culture',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'City Cultural Life and Entertainment',
                        'slug' => 'city-cultural-life-entertainment',
                        'excerpt' => 'Experience the dynamic city cultural life and entertainment options.',
                        'content' => '<p>The city boasts a vibrant cultural scene with theaters, museums, galleries, and concert halls hosting year-round events.</p><p>Summer festivals, open-air concerts, and cultural events transform the city into a hub of artistic expression and entertainment.</p>',
                    ],
                    'hr' => [
                        'title' => 'Gradski kulturni život i zabava',
                        'slug' => 'gradski-kulturni-zivot-zabava',
                        'excerpt' => 'Doživite dinamičan gradski kulturni život i mogućnosti zabave.',
                        'content' => '<p>Grad se ponosi živahnom kulturnom scenom s kazalištima, muzejima, galerijama i koncertnim dvoranama koje ugošćuju cjelogodišnje događaje.</p><p>Ljetni festivali, koncerti na otvorenom i kulturni događaji pretvaraju grad u središte umjetničkog izražavanja i zabave.</p>',
                    ],
                ],
            ],
            [
                'code' => 'city-attractions',
                'category' => 'Sights',
                'feature_image' => null,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'City Attractions and Must-See Landmarks',
                        'slug' => 'city-attractions-must-see-landmarks',
                        'excerpt' => 'Visit the top city attractions and must-see landmarks.',
                        'content' => '<p>The city features numerous attractions including historic monuments, waterfront promenades, and panoramic viewpoints.</p><p>Popular landmarks include ancient amphitheaters, medieval fortresses, and modern architectural marvels that define the city\'s skyline.</p>',
                    ],
                    'hr' => [
                        'title' => 'Gradske atrakcije i znamenitosti',
                        'slug' => 'gradske-atrakcije-znamenitosti',
                        'excerpt' => 'Posjetite vrhunske gradske atrakcije i znamenitosti.',
                        'content' => '<p>Grad sadrži brojne atrakcije uključujući povijesne spomenike, obalna šetališta i panoramske vidikovce.</p><p>Popularne znamenitosti uključuju antičke amfiteatre, srednjovjekovne tvrđave i moderna arhitektonska čuda koja definiraju gradsku panoramu.</p>',
                    ],
                ],
            ],
        ];

        $createdArticles = [];
        foreach ($articles as $articleData) {
            $translations = $articleData['translations'];
            unset($articleData['translations']);

            $article = Article::create($articleData);
            $createdArticles[$articleData['code']] = $article;

            foreach ($translations as $locale => $translation) {
                ArticleTranslation::create([
                    'article_id' => $article->id,
                    'locale' => $locale,
                    'title' => $translation['title'],
                    'slug' => $translation['slug'],
                    'excerpt' => $translation['excerpt'],
                    'content' => $translation['content'],
                ]);
            }
        }

        // Attach articles to countries, regions, and islands
        $this->attachArticlesToEntities($createdArticles);
    }

    private function attachArticlesToEntities(array $articles): void
    {
        $now = now();

        // Get sample countries (Croatia, Slovenia, Italy, Greece)
        $countries = \App\Models\Country::whereIn('code', ['HR', 'SI', 'IT', 'GR'])->get()->keyBy('code');

        // Get sample regions (first 4)
        $regions = \App\Models\Region::limit(4)->get();

        // Get sample islands (first 4)
        $islands = \App\Models\Island::limit(4)->get();

        // Get sample cities (first 4)
        $cities = \App\Models\City::limit(4)->get();

        // Attach 4 country articles to each country
        $countryArticles = [
            'country-coastal-heritage',
            'country-mediterranean-cuisine',
            'country-cultural-festivals',
            'country-natural-beauty',
        ];

        foreach ($countries as $country) {
            foreach ($countryArticles as $articleCode) {
                if (isset($articles[$articleCode])) {
                    DB::table('article_country')->insert([
                        'article_id' => $articles[$articleCode]->id,
                        'country_code' => $country->code,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 4 region articles to each region
        $regionArticles = [
            'region-local-heritage',
            'region-gastronomy',
            'region-traditions',
            'region-attractions',
        ];

        foreach ($regions as $region) {
            foreach ($regionArticles as $articleCode) {
                if (isset($articles[$articleCode])) {
                    DB::table('article_region')->insert([
                        'article_id' => $articles[$articleCode]->id,
                        'region_id' => $region->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 4 island articles to each island
        $islandArticles = [
            'island-history',
            'island-cuisine',
            'island-culture',
            'island-beaches',
        ];

        foreach ($islands as $island) {
            foreach ($islandArticles as $articleCode) {
                if (isset($articles[$articleCode])) {
                    DB::table('article_island')->insert([
                        'article_id' => $articles[$articleCode]->id,
                        'island_id' => $island->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Attach 4 city articles to each city
        $cityArticles = [
            'city-urban-history',
            'city-gastronomy',
            'city-cultural-life',
            'city-attractions',
        ];

        foreach ($cities as $city) {
            foreach ($cityArticles as $articleCode) {
                if (isset($articles[$articleCode])) {
                    DB::table('article_city')->insert([
                        'article_id' => $articles[$articleCode]->id,
                        'city_id' => $city->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}


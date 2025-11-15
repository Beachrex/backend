<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\FirstPageContent;
use App\Models\FirstPageContentTranslation;
use App\Models\FirstPageContentItem;
use App\Models\FirstPageContentItemTranslation;
use App\Models\Media;
use App\Models\MediaType;
use App\Models\MediaCollection;

class FirstPageContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding first page content...');

        // Get media type and collection IDs
        $imageType = MediaType::where('code', 'image')->first();
        $iconType = MediaType::where('code', 'icon')->first();

        // Create sections
        $this->createBanner();
        $this->createSection1();
        $this->createSection2();
        $this->createSection3();
        $this->createSection4();
        $this->createSection5();
        $this->createSection6();
        $this->createSection7();

        $this->command->info('First page content seeded successfully!');
    }

    private function createBanner(): void
    {
        $banner = FirstPageContent::create([
            'code' => 'hero_banner',
            'section_type' => 'banner',
            'order' => 1,
            'status_id' => 1,
        ]);

        // Create translations
        $translations = [
            'en' => 'All worries are smaller on the beach!',
            'sl' => 'Vse skrbi so manjše na plaži!',
            'de' => 'Alle Sorgen sind am Strand kleiner!',
            'hr' => 'Sve brige su manje na plaži!',
            'fr' => 'Tous les soucis sont plus petits à la plage!',
            'es' => '¡Todas las preocupaciones son más pequeñas en la playa!',
            'it' => 'Tutte le preoccupazioni sono più piccole in spiaggia!',
            'ua' => 'Всі турботи менші на пляжі!',
            'ru' => 'Все заботы меньше на пляже!',
            'pl' => 'Wszystkie zmartwienia są mniejsze na plaży!',
            'cs' => 'Všechny starosti jsou na pláži menší!',
            'hu' => 'Minden gond kisebb a strandon!',
            'bg' => 'Всички грижи са по-малки на плажа!',
            'sk' => 'Všetky starosti sú na pláži menšie!',
        ];

        foreach ($translations as $locale => $title) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $banner->id,
                'locale' => $locale,
                'title' => $title,
            ]);
        }

        // Create media record for background image
        $this->createMediaRecord($banner, 'image', 'img/first-page/hero-bg.jpg', 'hero-bg.jpg');
    }

    private function createSection1(): void
    {
        $section = FirstPageContent::create([
            'code' => 'browse_map',
            'section_type' => 'text_image',
            'order' => 2,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => [
                'title' => 'Browse the map with over 1500 beaches',
                'description' => 'Discover the most beautiful beaches around the world with an interactive map containing over 1500 stunning locations. Whether you\'re looking for sandy paradise shores, hidden coves, or popular surfing destinations, you\'ll find the perfect beach for you. Check descriptions, ratings, and photos to plan your next dream seaside adventure!',
            ],
            'sl' => [
                'title' => 'Prebrskaj zemljevid z več kot 1500 plažami',
                'description' => 'Odkrijte najlepše plaže po svetu z interaktivnim zemljevidom, ki vsebuje več kot 1500 osupljivih lokacij. Ne glede na to, ali iščete peščene rajske obale, skrite uvale ali priljubljene destinacije za surfanje, boste našli popolno plažo zase. Preverite opise, ocene in fotografije ter načrtujte svojo naslednjo sanjsko pustolovščino ob morju!',
            ],
            'de' => [
                'title' => 'Durchsuchen Sie die Karte mit über 1500 Stränden',
                'description' => 'Entdecken Sie die schönsten Strände der Welt mit einer interaktiven Karte mit über 1500 atemberaubenden Standorten. Egal, ob Sie nach paradiesischen Sandstränden, versteckten Buchten oder beliebten Surfzielen suchen, Sie werden den perfekten Strand für sich finden. Überprüfen Sie Beschreibungen, Bewertungen und Fotos, um Ihr nächstes Traumabenteuer am Meer zu planen!',
            ],
            'hr' => [
                'title' => 'Pregledajte kartu s više od 1500 plaža',
                'description' => 'Otkrijte najljepše plaže svijeta s interaktivnom kartom koja sadrži više od 1500 zadivljujućih lokacija. Bilo da tražite pješčane rajske obale, skrivene uvale ili popularne destinacije za surfanje, pronaći ćete savršenu plažu za sebe. Provjerite opise, ocjene i fotografije kako biste planirali svoju sljedeću avanturu iz snova uz more!',
            ],
            'fr' => [
                'title' => 'Parcourez la carte avec plus de 1500 plages',
                'description' => 'Découvrez les plus belles plages du monde avec une carte interactive contenant plus de 1500 emplacements magnifiques. Que vous recherchiez des rivages paradisiaques de sable, des criques cachées ou des destinations de surf populaires, vous trouverez la plage parfaite pour vous. Consultez les descriptions, les notes et les photos pour planifier votre prochaine aventure de rêve en bord de mer!',
            ],
            'es' => [
                'title' => 'Explora el mapa con más de 1500 playas',
                'description' => 'Descubre las playas más hermosas del mundo con un mapa interactivo que contiene más de 1500 ubicaciones impresionantes. Ya sea que busques costas paradisíacas de arena, calas escondidas o destinos populares para surfear, encontrarás la playa perfecta para ti. ¡Consulta descripciones, calificaciones y fotos para planificar tu próxima aventura de ensueño junto al mar!',
            ],
            'it' => [
                'title' => 'Esplora la mappa con oltre 1500 spiagge',
                'description' => 'Scopri le spiagge più belle del mondo con una mappa interattiva contenente oltre 1500 località mozzafiato. Che tu stia cercando coste paradisiache di sabbia, calette nascoste o destinazioni popolari per il surf, troverai la spiaggia perfetta per te. Controlla descrizioni, valutazioni e foto per pianificare la tua prossima avventura da sogno al mare!',
            ],
            'ua' => [
                'title' => 'Переглядайте карту з понад 1500 пляжами',
                'description' => 'Відкрийте для себе найкрасивіші пляжі світу за допомогою інтерактивної карти, що містить понад 1500 приголомшливих локацій. Незалежно від того, чи шукаєте ви райські піщані береги, приховані бухти чи популярні місця для серфінгу, ви знайдете ідеальний пляж для себе. Перевірте описи, рейтинги та фотографії, щоб спланувати свою наступну мрійну пригоду біля моря!',
            ],
            'ru' => [
                'title' => 'Просматривайте карту с более чем 1500 пляжами',
                'description' => 'Откройте для себя самые красивые пляжи мира с интерактивной картой, содержащей более 1500 потрясающих локаций. Независимо от того, ищете ли вы райские песчаные берега, скрытые бухты или популярные места для серфинга, вы найдете идеальный пляж для себя. Проверьте описания, рейтинги и фотографии, чтобы спланировать свое следующее приключение мечты у моря!',
            ],
            'pl' => [
                'title' => 'Przeglądaj mapę z ponad 1500 plażami',
                'description' => 'Odkryj najpiękniejsze plaże świata dzięki interaktywnej mapie zawierającej ponad 1500 zachwycających lokalizacji. Niezależnie od tego, czy szukasz rajskich piaszczystych brzegów, ukrytych zatoczek czy popularnych miejsc do surfowania, znajdziesz idealną plażę dla siebie. Sprawdź opisy, oceny i zdjęcia, aby zaplanować swoją następną wymarzoną przygodę nad morzem!',
            ],
            'cs' => [
                'title' => 'Procházejte mapu s více než 1500 plážemi',
                'description' => 'Objevte nejkrásnější pláže světa s interaktivní mapou obsahující více než 1500 úžasných lokalit. Ať už hledáte rajské písečné pobřeží, skryté zátoky nebo oblíbené destinace pro surfování, najdete si perfektní pláž. Zkontrolujte popisy, hodnocení a fotografie a naplánujte si své další vysněné dobrodružství u moře!',
            ],
            'hu' => [
                'title' => 'Böngésszen a térképen több mint 1500 stranddal',
                'description' => 'Fedezze fel a világ legszebb strandjait egy interaktív térképpel, amely több mint 1500 lenyűgöző helyszínt tartalmaz. Akár paradicsomi homokos partokat, rejtett öblöket vagy népszerű szörfözési célpontokat keres, megtalálja a tökéletes strandot. Ellenőrizze a leírásokat, értékeléseket és fotókat, hogy megtervezze következő álomszerű tengeri kalandját!',
            ],
            'bg' => [
                'title' => 'Разгледайте картата с над 1500 плажа',
                'description' => 'Открийте най-красивите плажове по света с интерактивна карта, съдържаща над 1500 зашеметяващи локации. Независимо дали търсите райски пясъчни брегове, скрити заливи или популярни дестинации за сърф, ще намерите перфектния плаж за себе си. Проверете описанията, оценките и снимките, за да планирате следващото си мечтано приключение край морето!',
            ],
            'sk' => [
                'title' => 'Prehliadajte mapu s viac ako 1500 plážami',
                'description' => 'Objavte najkrajšie pláže sveta s interaktívnou mapou obsahujúcou viac ako 1500 úžasných lokalít. Či už hľadáte rajské piesočné pobrežia, skryté zátoky alebo obľúbené destinácie na surfovanie, nájdete si perfektnú pláž. Skontrolujte popisy, hodnotenia a fotografie a naplánujte si svoje ďalšie vysníva dobrodružstvo pri mori!',
            ],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        }

        $this->createMediaRecord($section, 'image', 'img/first-page/worldmap.png', 'worldmap.png');
    }

    private function createSection2(): void
    {
        $section = FirstPageContent::create([
            'code' => 'find_beach',
            'section_type' => 'text_image',
            'order' => 3,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => [
                'title' => 'Find a beach to your taste',
                'description' => 'Looking for the perfect beach? Use advanced filters and quickly find the ideal location! Browse by beach type, sand type, accessibility, popularity, activities, infrastructure and more. Whether you want a quiet corner to relax or a lively beach with plenty of water sports, you\'ll easily find what suits you best.',
            ],
            'sl' => [
                'title' => 'Poišči plažo po svojem okusu',
                'description' => 'Iščeš popolno plažo? Uporabi napredne filtre in hitro najdi idealno lokacijo! Brskaj glede na tip plaže, vrsto peska, dostopnost, priljubljenost, aktivnosti, infrastrukturo in še več. Ne glede na to, ali si želiš mirnega kotička za sprostitev ali živahne plaže s številnimi vodnimi športi, boš zlahka našel tisto, kar ti najbolj ustreza.',
            ],
            'de' => [
                'title' => 'Finden Sie einen Strand nach Ihrem Geschmack',
                'description' => 'Suchen Sie den perfekten Strand? Verwenden Sie erweiterte Filter und finden Sie schnell den idealen Ort! Durchsuchen Sie nach Strandtyp, Sandart, Zugänglichkeit, Beliebtheit, Aktivitäten, Infrastruktur und mehr. Egal, ob Sie eine ruhige Ecke zum Entspannen oder einen lebhaften Strand mit vielen Wassersportarten möchten, Sie finden leicht, was am besten zu Ihnen passt.',
            ],
            'hr' => [
                'title' => 'Pronađite plažu po svom ukusu',
                'description' => 'Tražite savršenu plažu? Koristite napredne filtere i brzo pronađite idealnu lokaciju! Pregledajte prema vrsti plaže, vrsti pijeska, pristupačnosti, popularnosti, aktivnostima, infrastrukturi i još mnogo toga. Bilo da želite miran kutak za opuštanje ili živahnu plažu s mnogo vodenih sportova, lako ćete pronaći ono što vam najviše odgovara.',
            ],
            'fr' => [
                'title' => 'Trouvez une plage à votre goût',
                'description' => 'Vous cherchez la plage parfaite? Utilisez des filtres avancés et trouvez rapidement l\'emplacement idéal! Parcourez par type de plage, type de sable, accessibilité, popularité, activités, infrastructure et plus encore. Que vous souhaitiez un coin tranquille pour vous détendre ou une plage animée avec de nombreux sports nautiques, vous trouverez facilement ce qui vous convient le mieux.',
            ],
            'es' => [
                'title' => 'Encuentra una playa a tu gusto',
                'description' => '¿Buscas la playa perfecta? ¡Usa filtros avanzados y encuentra rápidamente la ubicación ideal! Navega por tipo de playa, tipo de arena, accesibilidad, popularidad, actividades, infraestructura y más. Ya sea que quieras un rincón tranquilo para relajarte o una playa animada con muchos deportes acuáticos, encontrarás fácilmente lo que mejor te convenga.',
            ],
            'it' => [
                'title' => 'Trova una spiaggia di tuo gusto',
                'description' => 'Cerchi la spiaggia perfetta? Usa filtri avanzati e trova rapidamente la posizione ideale! Sfoglia per tipo di spiaggia, tipo di sabbia, accessibilità, popolarità, attività, infrastrutture e altro ancora. Che tu voglia un angolo tranquillo per rilassarti o una spiaggia vivace con molti sport acquatici, troverai facilmente ciò che fa per te.',
            ],
            'ua' => [
                'title' => 'Знайдіть пляж на свій смак',
                'description' => 'Шукаєте ідеальний пляж? Використовуйте розширені фільтри та швидко знайдіть ідеальне місце! Переглядайте за типом пляжу, типом піску, доступністю, популярністю, активностями, інфраструктурою та іншим. Незалежно від того, чи хочете ви тихий куточок для відпочинку чи жвавий пляж з безліччю водних видів спорту, ви легко знайдете те, що вам найбільше підходить.',
            ],
            'ru' => [
                'title' => 'Найдите пляж по своему вкусу',
                'description' => 'Ищете идеальный пляж? Используйте расширенные фильтры и быстро найдите идеальное место! Просматривайте по типу пляжа, типу песка, доступности, популярности, активностям, инфраструктуре и многому другому. Независимо от того, хотите ли вы тихий уголок для отдыха или оживленный пляж с множеством водных видов спорта, вы легко найдете то, что вам больше всего подходит.',
            ],
            'pl' => [
                'title' => 'Znajdź plażę według swojego gustu',
                'description' => 'Szukasz idealnej plaży? Użyj zaawansowanych filtrów i szybko znajdź idealną lokalizację! Przeglądaj według typu plaży, rodzaju piasku, dostępności, popularności, aktywności, infrastruktury i nie tylko. Niezależnie od tego, czy chcesz spokojnego kącika do relaksu, czy tętniącej życiem plaży z wieloma sportami wodnymi, łatwo znajdziesz to, co najbardziej Ci odpowiada.',
            ],
            'cs' => [
                'title' => 'Najděte si pláž podle svého gusta',
                'description' => 'Hledáte perfektní pláž? Použijte pokročilé filtry a rychle najděte ideální místo! Procházejte podle typu pláže, typu písku, přístupnosti, popularity, aktivit, infrastruktury a dalších. Ať už chcete klidný koutek k odpočinku nebo živou pláž s mnoha vodními sporty, snadno najdete to, co vám nejvíce vyhovuje.',
            ],
            'hu' => [
                'title' => 'Találjon strandot az ízlése szerint',
                'description' => 'Keresi a tökéletes strandot? Használjon fejlett szűrőket és gyorsan találja meg az ideális helyet! Böngésszen strand típus, homok típus, hozzáférhetőség, népszerűség, tevékenységek, infrastruktúra és még sok más szerint. Akár csendes zugot keres a pihenéshez, akár élénk strandot sok vízi sporttal, könnyen megtalálja, ami a legjobban megfelel Önnek.',
            ],
            'bg' => [
                'title' => 'Намерете плаж по ваш вкус',
                'description' => 'Търсите перфектния плаж? Използвайте разширени филтри и бързо намерете идеалното място! Разглеждайте по тип плаж, тип пясък, достъпност, популярност, дейности, инфраструктура и още. Независимо дали искате тих кът за почивка или оживен плаж с много водни спортове, лесно ще намерите това, което ви подхожда най-добре.',
            ],
            'sk' => [
                'title' => 'Nájdite si pláž podľa svojho vkusu',
                'description' => 'Hľadáte perfektnú pláž? Použite pokročilé filtre a rýchlo nájdite ideálne miesto! Prehliadajte podľa typu pláže, typu piesku, prístupnosti, popularity, aktivít, infraštruktúry a ďalších. Či už chcete pokojný kútik na oddych alebo živú pláž s mnohými vodnými športmi, ľahko nájdete to, čo vám najviac vyhovuje.',
            ],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        }
    }

    private function createSection3(): void
    {
        $section = FirstPageContent::create([
            'code' => 'beach_views',
            'section_type' => 'repeater',
            'order' => 4,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => ['title' => 'View beaches from all angles'],
            'sl' => ['title' => 'Oglej si plaže v vseh pogledih'],
            'de' => ['title' => 'Sehen Sie Strände aus allen Blickwinkeln'],
            'hr' => ['title' => 'Pogledajte plaže iz svih kutova'],
            'fr' => ['title' => 'Voir les plages sous tous les angles'],
            'es' => ['title' => 'Ver playas desde todos los ángulos'],
            'it' => ['title' => 'Visualizza le spiagge da tutte le angolazioni'],
            'ua' => ['title' => 'Переглядайте пляжі з усіх кутів'],
            'ru' => ['title' => 'Просматривайте пляжи со всех ракурсов'],
            'pl' => ['title' => 'Zobacz plaże ze wszystkich kątów'],
            'cs' => ['title' => 'Prohlédněte si pláže ze všech úhlů'],
            'hu' => ['title' => 'Nézze meg a strandokat minden szögből'],
            'bg' => ['title' => 'Вижте плажовете от всички ъгли'],
            'sk' => ['title' => 'Pozrite si pláže zo všetkých uhlov'],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
            ]);
        }

        // Create repeater items
        $items = [
            [
                'icon' => 'img/icon/gallery-icon.png',
                'image' => 'img/first-page/beach-gallery.jpg',
                'translations' => [
                    'en' => 'Gallery', 'sl' => 'Galerija', 'de' => 'Galerie', 'hr' => 'Galerija',
                    'fr' => 'Galerie', 'es' => 'Galería', 'it' => 'Galleria', 'ua' => 'Галерея',
                    'ru' => 'Галерея', 'pl' => 'Galeria', 'cs' => 'Galerie', 'hu' => 'Galéria',
                    'bg' => 'Галерия', 'sk' => 'Galéria',
                ],
            ],
            [
                'icon' => 'img/icon/play-button-arrowhead.png',
                'image' => 'img/first-page/beach-gallery.jpg',
                'translations' => [
                    'en' => 'Video', 'sl' => 'Video', 'de' => 'Video', 'hr' => 'Video',
                    'fr' => 'Vidéo', 'es' => 'Vídeo', 'it' => 'Video', 'ua' => 'Відео',
                    'ru' => 'Видео', 'pl' => 'Wideo', 'cs' => 'Video', 'hu' => 'Videó',
                    'bg' => 'Видео', 'sk' => 'Video',
                ],
            ],
            [
                'icon' => 'img/icon/360-degree.png',
                'image' => 'img/first-page/beach-gallery.jpg',
                'translations' => [
                    'en' => 'Spheres', 'sl' => 'Sfere', 'de' => 'Sphären', 'hr' => 'Sfere',
                    'fr' => 'Sphères', 'es' => 'Esferas', 'it' => 'Sfere', 'ua' => 'Сфери',
                    'ru' => 'Сферы', 'pl' => 'Sfery', 'cs' => 'Sféry', 'hu' => 'Szférák',
                    'bg' => 'Сфери', 'sk' => 'Sféry',
                ],
            ],
            [
                'icon' => 'img/icon/video-icon.png',
                'image' => 'img/first-page/beach-gallery.jpg',
                'translations' => [
                    'en' => 'Live', 'sl' => 'V živo', 'de' => 'Live', 'hr' => 'Uživo',
                    'fr' => 'En direct', 'es' => 'En vivo', 'it' => 'Dal vivo', 'ua' => 'Наживо',
                    'ru' => 'Прямой эфир', 'pl' => 'Na żywo', 'cs' => 'Živě', 'hu' => 'Élő',
                    'bg' => 'На живо', 'sk' => 'Naživo',
                ],
            ],
        ];

        foreach ($items as $index => $itemData) {
            $item = FirstPageContentItem::create([
                'first_page_content_id' => $section->id,
                'order' => $index + 1,
                'status_id' => 1,
            ]);

            // Create translations for all languages
            foreach ($itemData['translations'] as $locale => $title) {
                FirstPageContentItemTranslation::create([
                    'first_page_content_item_id' => $item->id,
                    'locale' => $locale,
                    'title' => $title,
                ]);
            }

            // Create media records for icon and image
            $this->createMediaRecord($item, 'icon', $itemData['icon'], basename($itemData['icon']), 'icon');
            $this->createMediaRecord($item, 'image', $itemData['image'], basename($itemData['image']), 'main_image');
        }
    }

    private function createSection4(): void
    {
        $section = FirstPageContent::create([
            'code' => 'accommodation',
            'section_type' => 'text_image',
            'order' => 5,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => [
                'title' => 'Relax in comfortable beach accommodation',
                'description' => 'Experience complete relaxation in accommodation right by the sea! Choose from luxury resorts, charming bungalows or apartments with a view of the waves. Regardless of your travel style, comfort, proximity to the beach and an unforgettable experience by the sea await you.',
            ],
            'sl' => [
                'title' => 'Odpočij si v udobni plažni nastanitvi',
                'description' => 'Doživi popolno sprostitev v nastanitvi tik ob morju! Izberi med luksuznimi letovišči, prikupnimi bungalovi ali apartmaji s pogledom na valove. Ne glede na tvoj slog potovanja te čaka udobje, bližina plaže in nepozabno doživetje ob morski obali.',
            ],
            'de' => [
                'title' => 'Entspannen Sie sich in komfortabler Strandunterkunft',
                'description' => 'Erleben Sie vollständige Entspannung in einer Unterkunft direkt am Meer! Wählen Sie aus Luxusresorts, charmanten Bungalows oder Apartments mit Blick auf die Wellen. Unabhängig von Ihrem Reisestil erwarten Sie Komfort, Strandnähe und ein unvergessliches Erlebnis am Meer.',
            ],
            'hr' => [
                'title' => 'Opustite se u udobnom smještaju na plaži',
                'description' => 'Doživite potpuno opuštanje u smještaju tik uz more! Birajte između luksuznih odmarališta, šarmantnih bungalova ili apartmana s pogledom na valove. Bez obzira na vaš stil putovanja, čekaju vas udobnost, blizina plaže i nezaboravno iskustvo uz more.',
            ],
            'fr' => [
                'title' => 'Détendez-vous dans un hébergement de plage confortable',
                'description' => 'Vivez une détente complète dans un hébergement au bord de la mer! Choisissez parmi des complexes de luxe, des bungalows charmants ou des appartements avec vue sur les vagues. Quel que soit votre style de voyage, le confort, la proximité de la plage et une expérience inoubliable au bord de la mer vous attendent.',
            ],
            'es' => [
                'title' => 'Relájate en un alojamiento de playa cómodo',
                'description' => '¡Experimenta una relajación completa en un alojamiento junto al mar! Elige entre resorts de lujo, bungalows encantadores o apartamentos con vista a las olas. Independientemente de tu estilo de viaje, te esperan comodidad, proximidad a la playa y una experiencia inolvidable junto al mar.',
            ],
            'it' => [
                'title' => 'Rilassati in un comodo alloggio sulla spiaggia',
                'description' => 'Vivi un completo relax in un alloggio proprio sul mare! Scegli tra resort di lusso, bungalow affascinanti o appartamenti con vista sulle onde. Indipendentemente dal tuo stile di viaggio, ti aspettano comfort, vicinanza alla spiaggia e un\'esperienza indimenticabile al mare.',
            ],
            'ua' => [
                'title' => 'Відпочиньте в комфортному пляжному житлі',
                'description' => 'Відчуйте повне розслаблення в житлі прямо біля моря! Виберіть з розкішних курортів, чарівних бунгало або апартаментів з видом на хвилі. Незалежно від вашого стилю подорожі, на вас чекають комфорт, близькість до пляжу та незабутній досвід біля моря.',
            ],
            'ru' => [
                'title' => 'Отдохните в комфортном пляжном жилье',
                'description' => 'Испытайте полное расслабление в жилье прямо у моря! Выбирайте из роскошных курортов, очаровательных бунгало или апартаментов с видом на волны. Независимо от вашего стиля путешествия, вас ждут комфорт, близость к пляжу и незабываемый опыт у моря.',
            ],
            'pl' => [
                'title' => 'Zrelaksuj się w wygodnym zakwaterowaniu na plaży',
                'description' => 'Doświadcz pełnego relaksu w zakwaterowaniu tuż przy morzu! Wybieraj spośród luksusowych kurortów, uroczych bungalowów lub apartamentów z widokiem na fale. Niezależnie od Twojego stylu podróżowania, czekają na Ciebie wygoda, bliskość plaży i niezapomniane przeżycia nad morzem.',
            ],
            'cs' => [
                'title' => 'Odpočiňte si v pohodlném plážovém ubytování',
                'description' => 'Zažijte úplnou relaxaci v ubytování přímo u moře! Vyberte si z luxusních resortů, okouzlujících bungalovů nebo apartmánů s výhledem na vlny. Bez ohledu na váš styl cestování vás čeká pohodlí, blízkost pláže a nezapomenutelný zážitek u moře.',
            ],
            'hu' => [
                'title' => 'Pihenjen kényelmes tengerparti szálláson',
                'description' => 'Tapasztalja meg a teljes kikapcsolódást a tenger melletti szálláson! Válasszon luxus üdülőhelyek, bájos bungalók vagy hullámokra néző apartmanok közül. Utazási stílusától függetlenül kényelem, strandközelség és felejthetetlen élmény várja a tenger mellett.',
            ],
            'bg' => [
                'title' => 'Отпуснете се в удобно плажно настаняване',
                'description' => 'Изпитайте пълна релаксация в настаняване точно до морето! Изберете от луксозни курорти, очарователни бунгала или апартаменти с изглед към вълните. Независимо от вашия стил на пътуване, ви очакват комфорт, близост до плажа и незабравимо изживяване край морето.',
            ],
            'sk' => [
                'title' => 'Oddýchnite si v pohodlnom plážovom ubytovaní',
                'description' => 'Zažite úplnú relaxáciu v ubytovaní priamo pri mori! Vyberte si z luxusných rezortov, pôvabných bungalovov alebo apartmánov s výhľadom na vlny. Bez ohľadu na váš štýl cestovania vás čaká pohodlie, blízkosť pláže a nezabudnuteľný zážitok pri mori.',
            ],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        }

        // Add accommodation images
        $accommodationImages = [
            ['path' => 'img/first-page/accommodation-1.png', 'name' => 'accommodation-1.png'],
            ['path' => 'img/first-page/accommodation-2.png', 'name' => 'accommodation-2.png'],
            ['path' => 'img/first-page/accommodation-3.png', 'name' => 'accommodation-3.png'],
            ['path' => 'img/first-page/accommodation-4.png', 'name' => 'accommodation-4.png'],
            ['path' => 'img/first-page/accommodation-5.png', 'name' => 'accommodation-5.png'],
            ['path' => 'img/first-page/accommodation-6.png', 'name' => 'accommodation-6.png'],
            ['path' => 'img/first-page/accommodation-7.png', 'name' => 'accommodation-7.png'],
            ['path' => 'img/first-page/accommodation-8.png', 'name' => 'accommodation-8.png'],
        ];

        foreach ($accommodationImages as $index => $image) {
            $this->createMediaRecordWithOrder($section, 'image', $image['path'], $image['name'], null, $index === 0, $index);
        }
    }

    private function createSection5(): void
    {
        $section = FirstPageContent::create([
            'code' => 'coastal_destinations',
            'section_type' => 'text_only',
            'order' => 6,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => ['title' => 'The most beautiful coastal destinations in the world'],
            'sl' => ['title' => 'Najlepše obalne destinacije sveta'],
            'de' => ['title' => 'Die schönsten Küstenziele der Welt'],
            'hr' => ['title' => 'Najljepše obalne destinacije svijeta'],
            'fr' => ['title' => 'Les plus belles destinations côtières du monde'],
            'es' => ['title' => 'Los destinos costeros más hermosos del mundo'],
            'it' => ['title' => 'Le più belle destinazioni costiere del mondo'],
            'ua' => ['title' => 'Найкрасивіші прибережні напрямки світу'],
            'ru' => ['title' => 'Самые красивые прибрежные направления мира'],
            'pl' => ['title' => 'Najpiękniejsze nadmorskie destynacje świata'],
            'cs' => ['title' => 'Nejkrásnější pobřežní destinace světa'],
            'hu' => ['title' => 'A világ legszebb tengerparti célpontjai'],
            'bg' => ['title' => 'Най-красивите крайбрежни дестинации в света'],
            'sk' => ['title' => 'Najkrajšie pobrežné destinácie sveta'],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
            ]);
        }
    }

    private function createSection6(): void
    {
        $section = FirstPageContent::create([
            'code' => 'featured_sections',
            'section_type' => 'repeater',
            'order' => 7,
            'status_id' => 1,
        ]);

        // Create repeater items
        $items = [
            [
                'image' => 'img/first-page/best.jpg',
                'translations' => [
                    'en' => '100 most beautiful beaches in the world',
                    'sl' => '100 najlepših plaž na svetu',
                    'de' => '100 schönste Strände der Welt',
                    'hr' => '100 najljepših plaža na svijetu',
                    'fr' => '100 plus belles plages du monde',
                    'es' => '100 playas más hermosas del mundo',
                    'it' => '100 spiagge più belle del mondo',
                    'ua' => '100 найкрасивіших пляжів світу',
                    'ru' => '100 самых красивых пляжей мира',
                    'pl' => '100 najpiękniejszych plaż świata',
                    'cs' => '100 nejkrásnějších pláží světa',
                    'hu' => '100 legszebb strand a világon',
                    'bg' => '100 най-красиви плажа в света',
                    'sk' => '100 najkrajších pláží sveta',
                ],
            ],
            [
                'image' => 'img/first-page/discover.jpg',
                'translations' => [
                    'en' => 'Discover beach stories and tips',
                    'sl' => 'Odkrij plažne zgodbe in nasvete',
                    'de' => 'Entdecken Sie Strandgeschichten und Tipps',
                    'hr' => 'Otkrijte priče i savjete o plažama',
                    'fr' => 'Découvrez des histoires et conseils de plage',
                    'es' => 'Descubre historias y consejos de playa',
                    'it' => 'Scopri storie e consigli sulla spiaggia',
                    'ua' => 'Відкрийте пляжні історії та поради',
                    'ru' => 'Откройте пляжные истории и советы',
                    'pl' => 'Odkryj historie i porady plażowe',
                    'cs' => 'Objevte plážové příběhy a tipy',
                    'hu' => 'Fedezze fel a strand történeteket és tippeket',
                    'bg' => 'Открийте плажни истории и съвети',
                    'sk' => 'Objavte plážové príbehy a tipy',
                ],
            ],
            [
                'image' => 'img/first-page/relax.jpg',
                'translations' => [
                    'en' => 'Relax in comfortable accommodation',
                    'sl' => 'Odpočijte si v udobni nastanitvi',
                    'de' => 'Entspannen Sie sich in komfortabler Unterkunft',
                    'hr' => 'Opustite se u udobnom smještaju',
                    'fr' => 'Détendez-vous dans un hébergement confortable',
                    'es' => 'Relájate en un alojamiento cómodo',
                    'it' => 'Rilassati in un alloggio confortevole',
                    'ua' => 'Відпочиньте в комфортному житлі',
                    'ru' => 'Отдохните в комфортном жилье',
                    'pl' => 'Zrelaksuj się w wygodnym zakwaterowaniu',
                    'cs' => 'Odpočiňte si v pohodlném ubytování',
                    'hu' => 'Pihenjen kényelmes szálláson',
                    'bg' => 'Отпуснете се в удобно настаняване',
                    'sk' => 'Oddýchnite si v pohodlnom ubytovaní',
                ],
            ],
            [
                'image' => 'img/first-page/taste.jpg',
                'translations' => [
                    'en' => 'Discover a beach to your taste',
                    'sl' => 'Odkrij plažo po svojem okusu',
                    'de' => 'Entdecken Sie einen Strand nach Ihrem Geschmack',
                    'hr' => 'Otkrijte plažu po svom ukusu',
                    'fr' => 'Découvrez une plage à votre goût',
                    'es' => 'Descubre una playa a tu gusto',
                    'it' => 'Scopri una spiaggia di tuo gusto',
                    'ua' => 'Відкрийте пляж на свій смак',
                    'ru' => 'Откройте пляж по своему вкусу',
                    'pl' => 'Odkryj plażę według swojego gustu',
                    'cs' => 'Objevte pláž podle svého gusta',
                    'hu' => 'Fedezzen fel egy strandot az ízlése szerint',
                    'bg' => 'Открийте плаж по ваш вкус',
                    'sk' => 'Objavte pláž podľa svojho vkusu',
                ],
            ],
        ];

        foreach ($items as $index => $itemData) {
            $item = FirstPageContentItem::create([
                'first_page_content_id' => $section->id,
                'order' => $index + 1,
                'status_id' => 1,
            ]);

            // Create translations for all languages
            foreach ($itemData['translations'] as $locale => $title) {
                FirstPageContentItemTranslation::create([
                    'first_page_content_item_id' => $item->id,
                    'locale' => $locale,
                    'title' => $title,
                ]);
            }

            // Create media record for image
            $this->createMediaRecord($item, 'image', $itemData['image'], basename($itemData['image']));
        }
    }

    private function createSection7(): void
    {
        $section = FirstPageContent::create([
            'code' => 'footer_text',
            'section_type' => 'text_only',
            'order' => 8,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => ['title' => 'Explore, dream, discover paradise beaches!'],
            'sl' => ['title' => 'Raziskuj, sanjaj, odkrivaj rajske plaže!'],
            'de' => ['title' => 'Erkunden, träumen, paradiesische Strände entdecken!'],
            'hr' => ['title' => 'Istražujte, sanjajte, otkrivajte rajske plaže!'],
            'fr' => ['title' => 'Explorez, rêvez, découvrez des plages paradisiaques!'],
            'es' => ['title' => '¡Explora, sueña, descubre playas paradisíacas!'],
            'it' => ['title' => 'Esplora, sogna, scopri spiagge paradisiache!'],
            'ua' => ['title' => 'Досліджуйте, мрійте, відкривайте райські пляжі!'],
            'ru' => ['title' => 'Исследуйте, мечтайте, открывайте райские пляжи!'],
            'pl' => ['title' => 'Odkrywaj, marz, odkrywaj rajskie plaże!'],
            'cs' => ['title' => 'Prozkoumejte, sněte, objevujte rajské pláže!'],
            'hu' => ['title' => 'Fedezze fel, álmodjon, fedezze fel a paradicsomi strandokat!'],
            'bg' => ['title' => 'Изследвайте, мечтайте, открийте райски плажове!'],
            'sk' => ['title' => 'Preskúmajte, snívajte, objavujte rajské pláže!'],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
            ]);
        }
    }

    /**
     * Create a media record for the given model
     */
    private function createMediaRecord($model, string $typeCode, string $filePath, string $fileName, string $collectionCode = null): void
    {
        $this->createMediaRecordWithOrder($model, $typeCode, $filePath, $fileName, $collectionCode, true, 0);
    }

    /**
     * Create a media record with custom order and is_primary
     */
    private function createMediaRecordWithOrder($model, string $typeCode, string $filePath, string $fileName, string $collectionCode = null, bool $isPrimary = true, int $order = 0): void
    {
        $mediaType = MediaType::where('code', $typeCode)->first();

        if (!$mediaType) {
            $this->command->warn("Media type '{$typeCode}' not found. Skipping media creation.");
            return;
        }

        $mediaCollection = null;
        if ($collectionCode) {
            $mediaCollection = MediaCollection::where('code', $collectionCode)->first();
        }

        Media::create([
            'mediable_type' => get_class($model),
            'mediable_id' => $model->id,
            'media_type_id' => $mediaType->id,
            'media_collection_id' => $mediaCollection?->id,
            'user_id' => null,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'disk' => 'spaces', // Using DigitalOcean Spaces
            'mime_type' => $this->getMimeType($fileName),
            'size' => 0, // Size unknown for external files
            'width' => null,
            'height' => null,
            'is_primary' => $isPrimary,
            'order' => $order,
            'status_id' => 1,
        ]);
    }

    /**
     * Get MIME type based on file extension
     */
    private function getMimeType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CulinaryOfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('culinary_offer')->count() > 0) {
                $this->command->info('culinary_offer table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $culinaryOffers = [
            ['id' => 1, 'code' => 'no_service'],
            ['id' => 2, 'code' => 'restaurant'],
            ['id' => 3, 'code' => 'bistro'],
            ['id' => 4, 'code' => 'fast_food'],
            ['id' => 5, 'code' => 'pizzeria'],
            ['id' => 6, 'code' => 'bakery'],
            ['id' => 7, 'code' => 'caffe_bar'],
            ['id' => 8, 'code' => 'biffe'],
            ['id' => 9, 'code' => 'beach_bar'],
            ['id' => 10, 'code' => 'cake_shop'],
            ['id' => 11, 'code' => 'club_lounge_bar_dance_floor'],
            ['id' => 12, 'code' => 'improvised_catering_supplies'],
        ];

        $translations = [
            'no_service' => [
                'en' => ['name' => 'No Service', 'slug' => 'no-service'],
                'sl' => ['name' => 'Brez Storitve', 'slug' => 'brez-storitve'],
                'de' => ['name' => 'Kein Service', 'slug' => 'kein-service'],
                'hr' => ['name' => 'Bez Usluge', 'slug' => 'bez-usluge'],
                'fr' => ['name' => 'Pas de Service', 'slug' => 'pas-de-service'],
                'es' => ['name' => 'Sin Servicio', 'slug' => 'sin-servicio'],
                'it' => ['name' => 'Nessun Servizio', 'slug' => 'nessun-servizio'],
                'ua' => ['name' => 'Без Послуги', 'slug' => 'bez-poslugy'],
                'ru' => ['name' => 'Без Услуги', 'slug' => 'bez-uslugi'],
                'pl' => ['name' => 'Bez Usługi', 'slug' => 'bez-uslugi'],
                'cs' => ['name' => 'Bez Služby', 'slug' => 'bez-sluzby'],
                'hu' => ['name' => 'Nincs Szolgáltatás', 'slug' => 'nincs-szolgaltatas'],
                'bg' => ['name' => 'Без Услуга', 'slug' => 'bez-usluga'],
                'sk' => ['name' => 'Bez Služby', 'slug' => 'bez-sluzby'],
            ],
            'restaurant' => [
                'en' => ['name' => 'Restaurant', 'slug' => 'restaurant'],
                'sl' => ['name' => 'Restavracija', 'slug' => 'restavracija'],
                'de' => ['name' => 'Restaurant', 'slug' => 'restaurant'],
                'hr' => ['name' => 'Restoran', 'slug' => 'restoran'],
                'fr' => ['name' => 'Restaurant', 'slug' => 'restaurant'],
                'es' => ['name' => 'Restaurante', 'slug' => 'restaurante'],
                'it' => ['name' => 'Ristorante', 'slug' => 'ristorante'],
                'ua' => ['name' => 'Ресторан', 'slug' => 'restoran'],
                'ru' => ['name' => 'Ресторан', 'slug' => 'restoran'],
                'pl' => ['name' => 'Restauracja', 'slug' => 'restauracja'],
                'cs' => ['name' => 'Restaurace', 'slug' => 'restaurace'],
                'hu' => ['name' => 'Étterem', 'slug' => 'etterem'],
                'bg' => ['name' => 'Ресторант', 'slug' => 'restorant'],
                'sk' => ['name' => 'Reštaurácia', 'slug' => 'restauracia'],
            ],
            'bistro' => [
                'en' => ['name' => 'Bistro', 'slug' => 'bistro'],
                'sl' => ['name' => 'Bistro', 'slug' => 'bistro'],
                'de' => ['name' => 'Bistro', 'slug' => 'bistro'],
                'hr' => ['name' => 'Bistro', 'slug' => 'bistro'],
                'fr' => ['name' => 'Bistrot', 'slug' => 'bistrot'],
                'es' => ['name' => 'Bistró', 'slug' => 'bistro'],
                'it' => ['name' => 'Bistrot', 'slug' => 'bistrot'],
                'ua' => ['name' => 'Бістро', 'slug' => 'bistro'],
                'ru' => ['name' => 'Бистро', 'slug' => 'bistro'],
                'pl' => ['name' => 'Bistro', 'slug' => 'bistro'],
                'cs' => ['name' => 'Bistro', 'slug' => 'bistro'],
                'hu' => ['name' => 'Bisztró', 'slug' => 'bisztro'],
                'bg' => ['name' => 'Бистро', 'slug' => 'bistro'],
                'sk' => ['name' => 'Bistro', 'slug' => 'bistro'],
            ],
            'fast_food' => [
                'en' => ['name' => 'Fast Food', 'slug' => 'fast-food'],
                'sl' => ['name' => 'Hitra Hrana', 'slug' => 'hitra-hrana'],
                'de' => ['name' => 'Schnellimbiss', 'slug' => 'schnellimbiss'],
                'hr' => ['name' => 'Brza Hrana', 'slug' => 'brza-hrana'],
                'fr' => ['name' => 'Restauration Rapide', 'slug' => 'restauration-rapide'],
                'es' => ['name' => 'Comida Rápida', 'slug' => 'comida-rapida'],
                'it' => ['name' => 'Fast Food', 'slug' => 'fast-food'],
                'ua' => ['name' => 'Фастфуд', 'slug' => 'fastfud'],
                'ru' => ['name' => 'Фастфуд', 'slug' => 'fastfud'],
                'pl' => ['name' => 'Fast Food', 'slug' => 'fast-food'],
                'cs' => ['name' => 'Rychlé Občerstvení', 'slug' => 'rychle-obcerstveni'],
                'hu' => ['name' => 'Gyorsétterem', 'slug' => 'gyorsetterem'],
                'bg' => ['name' => 'Бързо Хранене', 'slug' => 'barzo-hranene'],
                'sk' => ['name' => 'Rýchle Občerstvenie', 'slug' => 'rychle-obcerstvenie'],
            ],
            'pizzeria' => [
                'en' => ['name' => 'Pizzeria', 'slug' => 'pizzeria'],
                'sl' => ['name' => 'Picerija', 'slug' => 'picerija'],
                'de' => ['name' => 'Pizzeria', 'slug' => 'pizzeria'],
                'hr' => ['name' => 'Pizzeria', 'slug' => 'pizzeria'],
                'fr' => ['name' => 'Pizzeria', 'slug' => 'pizzeria'],
                'es' => ['name' => 'Pizzería', 'slug' => 'pizzeria'],
                'it' => ['name' => 'Pizzeria', 'slug' => 'pizzeria'],
                'ua' => ['name' => 'Піцерія', 'slug' => 'pitseriia'],
                'ru' => ['name' => 'Пиццерия', 'slug' => 'pitstseriya'],
                'pl' => ['name' => 'Pizzeria', 'slug' => 'pizzeria'],
                'cs' => ['name' => 'Pizzerie', 'slug' => 'pizzerie'],
                'hu' => ['name' => 'Pizzéria', 'slug' => 'pizzeria'],
                'bg' => ['name' => 'Пицария', 'slug' => 'pitsariya'],
                'sk' => ['name' => 'Pizzeria', 'slug' => 'pizzeria'],
            ],
            'bakery' => [
                'en' => ['name' => 'Bakery', 'slug' => 'bakery'],
                'sl' => ['name' => 'Pekarna', 'slug' => 'pekarna'],
                'de' => ['name' => 'Bäckerei', 'slug' => 'backerei'],
                'hr' => ['name' => 'Pekara', 'slug' => 'pekara'],
                'fr' => ['name' => 'Boulangerie', 'slug' => 'boulangerie'],
                'es' => ['name' => 'Panadería', 'slug' => 'panaderia'],
                'it' => ['name' => 'Panetteria', 'slug' => 'panetteria'],
                'ua' => ['name' => 'Пекарня', 'slug' => 'pekarnia'],
                'ru' => ['name' => 'Пекарня', 'slug' => 'pekarnya'],
                'pl' => ['name' => 'Piekarnia', 'slug' => 'piekarnia'],
                'cs' => ['name' => 'Pekárna', 'slug' => 'pekarna'],
                'hu' => ['name' => 'Pékség', 'slug' => 'pekseg'],
                'bg' => ['name' => 'Пекарна', 'slug' => 'pekarna'],
                'sk' => ['name' => 'Pekáreň', 'slug' => 'pekaren'],
            ],
            'caffe_bar' => [
                'en' => ['name' => 'Caffe Bar', 'slug' => 'caffe-bar'],
                'sl' => ['name' => 'Kavarna', 'slug' => 'kavarna'],
                'de' => ['name' => 'Café Bar', 'slug' => 'cafe-bar'],
                'hr' => ['name' => 'Caffe Bar', 'slug' => 'caffe-bar'],
                'fr' => ['name' => 'Café Bar', 'slug' => 'cafe-bar'],
                'es' => ['name' => 'Cafetería Bar', 'slug' => 'cafeteria-bar'],
                'it' => ['name' => 'Caffè Bar', 'slug' => 'caffe-bar'],
                'ua' => ['name' => 'Кафе Бар', 'slug' => 'kafe-bar'],
                'ru' => ['name' => 'Кафе Бар', 'slug' => 'kafe-bar'],
                'pl' => ['name' => 'Kawiarnia Bar', 'slug' => 'kawiarnia-bar'],
                'cs' => ['name' => 'Kavárna Bar', 'slug' => 'kavarna-bar'],
                'hu' => ['name' => 'Kávézó Bár', 'slug' => 'kavezo-bar'],
                'bg' => ['name' => 'Кафе Бар', 'slug' => 'kafe-bar'],
                'sk' => ['name' => 'Kaviareň Bar', 'slug' => 'kaviaren-bar'],
            ],
            'biffe' => [
                'en' => ['name' => 'Buffet', 'slug' => 'buffet'],
                'sl' => ['name' => 'Bifé', 'slug' => 'bife'],
                'de' => ['name' => 'Buffet', 'slug' => 'buffet'],
                'hr' => ['name' => 'Bife', 'slug' => 'bife'],
                'fr' => ['name' => 'Buffet', 'slug' => 'buffet'],
                'es' => ['name' => 'Bufé', 'slug' => 'bufe'],
                'it' => ['name' => 'Buffet', 'slug' => 'buffet'],
                'ua' => ['name' => 'Буфет', 'slug' => 'bufet'],
                'ru' => ['name' => 'Буфет', 'slug' => 'bufet'],
                'pl' => ['name' => 'Bufet', 'slug' => 'bufet'],
                'cs' => ['name' => 'Bufet', 'slug' => 'bufet'],
                'hu' => ['name' => 'Büfé', 'slug' => 'bufe'],
                'bg' => ['name' => 'Бюфет', 'slug' => 'biufet'],
                'sk' => ['name' => 'Bufet', 'slug' => 'bufet'],
            ],
            'beach_bar' => [
                'en' => ['name' => 'Beach Bar', 'slug' => 'beach-bar'],
                'sl' => ['name' => 'Plažni Bar', 'slug' => 'plazni-bar'],
                'de' => ['name' => 'Strandbar', 'slug' => 'strandbar'],
                'hr' => ['name' => 'Plaža Bar', 'slug' => 'plaza-bar'],
                'fr' => ['name' => 'Bar de Plage', 'slug' => 'bar-de-plage'],
                'es' => ['name' => 'Chiringuito', 'slug' => 'chiringuito'],
                'it' => ['name' => 'Bar sulla Spiaggia', 'slug' => 'bar-sulla-spiaggia'],
                'ua' => ['name' => 'Пляжний Бар', 'slug' => 'pliaznyi-bar'],
                'ru' => ['name' => 'Пляжный Бар', 'slug' => 'pliaznyi-bar'],
                'pl' => ['name' => 'Bar Plażowy', 'slug' => 'bar-plazowy'],
                'cs' => ['name' => 'Plážový Bar', 'slug' => 'plazovy-bar'],
                'hu' => ['name' => 'Strandbár', 'slug' => 'strandbar'],
                'bg' => ['name' => 'Плажен Бар', 'slug' => 'plazhen-bar'],
                'sk' => ['name' => 'Plážový Bar', 'slug' => 'plazovy-bar'],
            ],
            'cake_shop' => [
                'en' => ['name' => 'Cake Shop', 'slug' => 'cake-shop'],
                'sl' => ['name' => 'Slaščičarna', 'slug' => 'slascicarna'],
                'de' => ['name' => 'Konditorei', 'slug' => 'konditorei'],
                'hr' => ['name' => 'Slastičarna', 'slug' => 'slasticarna'],
                'fr' => ['name' => 'Pâtisserie', 'slug' => 'patisserie'],
                'es' => ['name' => 'Pastelería', 'slug' => 'pasteleria'],
                'it' => ['name' => 'Pasticceria', 'slug' => 'pasticceria'],
                'ua' => ['name' => 'Кондитерська', 'slug' => 'kondyterska'],
                'ru' => ['name' => 'Кондитерская', 'slug' => 'konditerskaya'],
                'pl' => ['name' => 'Cukiernia', 'slug' => 'cukiernia'],
                'cs' => ['name' => 'Cukrárna', 'slug' => 'cukrarna'],
                'hu' => ['name' => 'Cukrászda', 'slug' => 'cukraszda'],
                'bg' => ['name' => 'Сладкарница', 'slug' => 'sladkarnitsa'],
                'sk' => ['name' => 'Cukráreň', 'slug' => 'cukraren'],
            ],
            'club_lounge_bar_dance_floor' => [
                'en' => ['name' => 'Club/Lounge Bar/Dance Floor', 'slug' => 'club-lounge-bar-dance-floor'],
                'sl' => ['name' => 'Klub/Lounge Bar/Plesišče', 'slug' => 'klub-lounge-bar-plesisce'],
                'de' => ['name' => 'Club/Lounge Bar/Tanzfläche', 'slug' => 'club-lounge-bar-tanzflache'],
                'hr' => ['name' => 'Klub/Lounge Bar/Plesni Podij', 'slug' => 'klub-lounge-bar-plesni-podij'],
                'fr' => ['name' => 'Club/Lounge Bar/Piste de Danse', 'slug' => 'club-lounge-bar-piste-de-danse'],
                'es' => ['name' => 'Club/Lounge Bar/Pista de Baile', 'slug' => 'club-lounge-bar-pista-de-baile'],
                'it' => ['name' => 'Club/Lounge Bar/Pista da Ballo', 'slug' => 'club-lounge-bar-pista-da-ballo'],
                'ua' => ['name' => 'Клуб/Лаунж Бар/Танцпол', 'slug' => 'klub-launzh-bar-tantspol'],
                'ru' => ['name' => 'Клуб/Лаунж Бар/Танцпол', 'slug' => 'klub-launzh-bar-tantspol'],
                'pl' => ['name' => 'Klub/Lounge Bar/Parkiet Taneczny', 'slug' => 'klub-lounge-bar-parkiet-taneczny'],
                'cs' => ['name' => 'Klub/Lounge Bar/Taneční Parket', 'slug' => 'klub-lounge-bar-tanecni-parket'],
                'hu' => ['name' => 'Klub/Lounge Bár/Táncparkett', 'slug' => 'klub-lounge-bar-tancparkett'],
                'bg' => ['name' => 'Клуб/Лаундж Бар/Танцов Под', 'slug' => 'klub-laundzh-bar-tantsov-pod'],
                'sk' => ['name' => 'Klub/Lounge Bar/Tanečný Parket', 'slug' => 'klub-lounge-bar-tanecny-parket'],
            ],
            'improvised_catering_supplies' => [
                'en' => ['name' => 'Improvised Catering Supplies', 'slug' => 'improvised-catering-supplies'],
                'sl' => ['name' => 'Improvizirana Gostinska Ponudba', 'slug' => 'improvizirana-gostinska-ponudba'],
                'de' => ['name' => 'Improvisierte Verpflegung', 'slug' => 'improvisierte-verpflegung'],
                'hr' => ['name' => 'Improvizirana Ugostiteljska Ponuda', 'slug' => 'improvizirana-ugostiteljska-ponuda'],
                'fr' => ['name' => 'Restauration Improvisée', 'slug' => 'restauration-improvisee'],
                'es' => ['name' => 'Suministros de Catering Improvisados', 'slug' => 'suministros-catering-improvisados'],
                'it' => ['name' => 'Forniture di Ristorazione Improvvisate', 'slug' => 'forniture-ristorazione-improvvisate'],
                'ua' => ['name' => 'Імпровізоване Харчування', 'slug' => 'improvizovane-kharchuvannia'],
                'ru' => ['name' => 'Импровизированное Питание', 'slug' => 'improvizirovannoe-pitanie'],
                'pl' => ['name' => 'Improwizowane Zaopatrzenie Gastronomiczne', 'slug' => 'improwizowane-zaopatrzenie-gastronomiczne'],
                'cs' => ['name' => 'Improvizované Stravování', 'slug' => 'improvizovane-stravovani'],
                'hu' => ['name' => 'Rögtönzött Vendéglátás', 'slug' => 'rogtonzott-vendeglatas'],
                'bg' => ['name' => 'Импровизирано Кетъринг', 'slug' => 'improvizirano-ketyring'],
                'sk' => ['name' => 'Improvizované Stravovanie', 'slug' => 'improvizovane-stravovanie'],
            ],
        ];

        foreach ($culinaryOffers as $offerData) {
            // Insert culinary offer with explicit ID
            DB::table('culinary_offer')->insert([
                'id' => $offerData['id'],
                'code' => $offerData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($translations[$offerData['code']] as $locale => $translation) {
                DB::table('culinary_offer_translation')->insert([
                    'culinary_offer_id' => $offerData['id'],
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Culinary offers seeded successfully with translations in 14 languages!');
    }
}


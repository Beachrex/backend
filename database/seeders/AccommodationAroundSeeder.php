<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccommodationAroundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('accommodation_around')->count() > 0) {
                $this->command->info('accommodation_around table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $accommodationTypes = [
            ['id' => 1, 'code' => 'no_service'],
            ['id' => 2, 'code' => 'hotel'],
            ['id' => 3, 'code' => 'hotel_complex'],
            ['id' => 4, 'code' => 'apartments'],
            ['id' => 5, 'code' => 'apartments_complex'],
            ['id' => 6, 'code' => 'rooms'],
            ['id' => 7, 'code' => 'motel'],
            ['id' => 8, 'code' => 'hostel'],
            ['id' => 9, 'code' => 'camp'],
            ['id' => 10, 'code' => 'autocamping'],
            ['id' => 11, 'code' => 'naturist_camp'],
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
            'hotel' => [
                'en' => ['name' => 'Hotel', 'slug' => 'hotel'],
                'sl' => ['name' => 'Hotel', 'slug' => 'hotel'],
                'de' => ['name' => 'Hotel', 'slug' => 'hotel'],
                'hr' => ['name' => 'Hotel', 'slug' => 'hotel'],
                'fr' => ['name' => 'Hôtel', 'slug' => 'hotel'],
                'es' => ['name' => 'Hotel', 'slug' => 'hotel'],
                'it' => ['name' => 'Hotel', 'slug' => 'hotel'],
                'ua' => ['name' => 'Готель', 'slug' => 'gotel'],
                'ru' => ['name' => 'Отель', 'slug' => 'otel'],
                'pl' => ['name' => 'Hotel', 'slug' => 'hotel'],
                'cs' => ['name' => 'Hotel', 'slug' => 'hotel'],
                'hu' => ['name' => 'Szálloda', 'slug' => 'szalloda'],
                'bg' => ['name' => 'Хотел', 'slug' => 'hotel'],
                'sk' => ['name' => 'Hotel', 'slug' => 'hotel'],
            ],
            'hotel_complex' => [
                'en' => ['name' => 'Hotel Complex', 'slug' => 'hotel-complex'],
                'sl' => ['name' => 'Hotelski Kompleks', 'slug' => 'hotelski-kompleks'],
                'de' => ['name' => 'Hotelkomplex', 'slug' => 'hotelkomplex'],
                'hr' => ['name' => 'Hotelski Kompleks', 'slug' => 'hotelski-kompleks'],
                'fr' => ['name' => 'Complexe Hôtelier', 'slug' => 'complexe-hotelier'],
                'es' => ['name' => 'Complejo Hotelero', 'slug' => 'complejo-hotelero'],
                'it' => ['name' => 'Complesso Alberghiero', 'slug' => 'complesso-alberghiero'],
                'ua' => ['name' => 'Готельний Комплекс', 'slug' => 'gotelnyi-kompleks'],
                'ru' => ['name' => 'Гостиничный Комплекс', 'slug' => 'gostinichnyi-kompleks'],
                'pl' => ['name' => 'Kompleks Hotelowy', 'slug' => 'kompleks-hotelowy'],
                'cs' => ['name' => 'Hotelový Komplex', 'slug' => 'hotelovy-komplex'],
                'hu' => ['name' => 'Szállodakomplexum', 'slug' => 'szallodakomplexum'],
                'bg' => ['name' => 'Хотелски Комплекс', 'slug' => 'hotelski-kompleks'],
                'sk' => ['name' => 'Hotelový Komplex', 'slug' => 'hotelovy-komplex'],
            ],
            'apartments' => [
                'en' => ['name' => 'Apartments', 'slug' => 'apartments'],
                'sl' => ['name' => 'Apartmaji', 'slug' => 'apartmaji'],
                'de' => ['name' => 'Apartments', 'slug' => 'apartments'],
                'hr' => ['name' => 'Apartmani', 'slug' => 'apartmani'],
                'fr' => ['name' => 'Appartements', 'slug' => 'appartements'],
                'es' => ['name' => 'Apartamentos', 'slug' => 'apartamentos'],
                'it' => ['name' => 'Appartamenti', 'slug' => 'appartamenti'],
                'ua' => ['name' => 'Апартаменти', 'slug' => 'apartamenty'],
                'ru' => ['name' => 'Апартаменты', 'slug' => 'apartamenty'],
                'pl' => ['name' => 'Apartamenty', 'slug' => 'apartamenty'],
                'cs' => ['name' => 'Apartmány', 'slug' => 'apartmany'],
                'hu' => ['name' => 'Apartmanok', 'slug' => 'apartmanok'],
                'bg' => ['name' => 'Апартаменти', 'slug' => 'apartamenti'],
                'sk' => ['name' => 'Apartmány', 'slug' => 'apartmany'],
            ],
            'apartments_complex' => [
                'en' => ['name' => 'Apartments Complex', 'slug' => 'apartments-complex'],
                'sl' => ['name' => 'Apartmajski Kompleks', 'slug' => 'apartmajski-kompleks'],
                'de' => ['name' => 'Apartmentkomplex', 'slug' => 'apartmentkomplex'],
                'hr' => ['name' => 'Apartmanski Kompleks', 'slug' => 'apartmanski-kompleks'],
                'fr' => ['name' => 'Complexe d\'Appartements', 'slug' => 'complexe-appartements'],
                'es' => ['name' => 'Complejo de Apartamentos', 'slug' => 'complejo-apartamentos'],
                'it' => ['name' => 'Complesso di Appartamenti', 'slug' => 'complesso-appartamenti'],
                'ua' => ['name' => 'Апартаментний Комплекс', 'slug' => 'apartamentnyi-kompleks'],
                'ru' => ['name' => 'Апартаментный Комплекс', 'slug' => 'apartamentnyi-kompleks'],
                'pl' => ['name' => 'Kompleks Apartamentów', 'slug' => 'kompleks-apartamentow'],
                'cs' => ['name' => 'Apartmánový Komplex', 'slug' => 'apartmanovy-komplex'],
                'hu' => ['name' => 'Apartmankomplexum', 'slug' => 'apartmankomplexum'],
                'bg' => ['name' => 'Апартаментен Комплекс', 'slug' => 'apartamenten-kompleks'],
                'sk' => ['name' => 'Apartmánový Komplex', 'slug' => 'apartmanovy-komplex'],
            ],
            'rooms' => [
                'en' => ['name' => 'Rooms', 'slug' => 'rooms'],
                'sl' => ['name' => 'Sobe', 'slug' => 'sobe'],
                'de' => ['name' => 'Zimmer', 'slug' => 'zimmer'],
                'hr' => ['name' => 'Sobe', 'slug' => 'sobe'],
                'fr' => ['name' => 'Chambres', 'slug' => 'chambres'],
                'es' => ['name' => 'Habitaciones', 'slug' => 'habitaciones'],
                'it' => ['name' => 'Camere', 'slug' => 'camere'],
                'ua' => ['name' => 'Кімнати', 'slug' => 'kimnaty'],
                'ru' => ['name' => 'Комнаты', 'slug' => 'komnaty'],
                'pl' => ['name' => 'Pokoje', 'slug' => 'pokoje'],
                'cs' => ['name' => 'Pokoje', 'slug' => 'pokoje'],
                'hu' => ['name' => 'Szobák', 'slug' => 'szobak'],
                'bg' => ['name' => 'Стаи', 'slug' => 'stai'],
                'sk' => ['name' => 'Izby', 'slug' => 'izby'],
            ],
            'motel' => [
                'en' => ['name' => 'Motel', 'slug' => 'motel'],
                'sl' => ['name' => 'Motel', 'slug' => 'motel'],
                'de' => ['name' => 'Motel', 'slug' => 'motel'],
                'hr' => ['name' => 'Motel', 'slug' => 'motel'],
                'fr' => ['name' => 'Motel', 'slug' => 'motel'],
                'es' => ['name' => 'Motel', 'slug' => 'motel'],
                'it' => ['name' => 'Motel', 'slug' => 'motel'],
                'ua' => ['name' => 'Мотель', 'slug' => 'motel'],
                'ru' => ['name' => 'Мотель', 'slug' => 'motel'],
                'pl' => ['name' => 'Motel', 'slug' => 'motel'],
                'cs' => ['name' => 'Motel', 'slug' => 'motel'],
                'hu' => ['name' => 'Motel', 'slug' => 'motel'],
                'bg' => ['name' => 'Мотел', 'slug' => 'motel'],
                'sk' => ['name' => 'Motel', 'slug' => 'motel'],
            ],
            'hostel' => [
                'en' => ['name' => 'Hostel', 'slug' => 'hostel'],
                'sl' => ['name' => 'Hostel', 'slug' => 'hostel'],
                'de' => ['name' => 'Hostel', 'slug' => 'hostel'],
                'hr' => ['name' => 'Hostel', 'slug' => 'hostel'],
                'fr' => ['name' => 'Auberge', 'slug' => 'auberge'],
                'es' => ['name' => 'Albergue', 'slug' => 'albergue'],
                'it' => ['name' => 'Ostello', 'slug' => 'ostello'],
                'ua' => ['name' => 'Хостел', 'slug' => 'hostel'],
                'ru' => ['name' => 'Хостел', 'slug' => 'hostel'],
                'pl' => ['name' => 'Hostel', 'slug' => 'hostel'],
                'cs' => ['name' => 'Hostel', 'slug' => 'hostel'],
                'hu' => ['name' => 'Hostel', 'slug' => 'hostel'],
                'bg' => ['name' => 'Хостел', 'slug' => 'hostel'],
                'sk' => ['name' => 'Hostel', 'slug' => 'hostel'],
            ],
            'camp' => [
                'en' => ['name' => 'Camp', 'slug' => 'camp'],
                'sl' => ['name' => 'Kamp', 'slug' => 'kamp'],
                'de' => ['name' => 'Camp', 'slug' => 'camp'],
                'hr' => ['name' => 'Kamp', 'slug' => 'kamp'],
                'fr' => ['name' => 'Camping', 'slug' => 'camping'],
                'es' => ['name' => 'Camping', 'slug' => 'camping'],
                'it' => ['name' => 'Campeggio', 'slug' => 'campeggio'],
                'ua' => ['name' => 'Кемпінг', 'slug' => 'kemping'],
                'ru' => ['name' => 'Кемпинг', 'slug' => 'kemping'],
                'pl' => ['name' => 'Kemping', 'slug' => 'kemping'],
                'cs' => ['name' => 'Kemp', 'slug' => 'kemp'],
                'hu' => ['name' => 'Kemping', 'slug' => 'kemping'],
                'bg' => ['name' => 'Къмпинг', 'slug' => 'kamping'],
                'sk' => ['name' => 'Kemp', 'slug' => 'kemp'],
            ],
            'autocamping' => [
                'en' => ['name' => 'Autocamping', 'slug' => 'autocamping'],
                'sl' => ['name' => 'Avtokamp', 'slug' => 'avtokamp'],
                'de' => ['name' => 'Autocamping', 'slug' => 'autocamping'],
                'hr' => ['name' => 'Autokamp', 'slug' => 'autokamp'],
                'fr' => ['name' => 'Camping pour Voitures', 'slug' => 'camping-voitures'],
                'es' => ['name' => 'Autocamping', 'slug' => 'autocamping'],
                'it' => ['name' => 'Autocampeggio', 'slug' => 'autocampeggio'],
                'ua' => ['name' => 'Автокемпінг', 'slug' => 'avtokemping'],
                'ru' => ['name' => 'Автокемпинг', 'slug' => 'avtokemping'],
                'pl' => ['name' => 'Autokemping', 'slug' => 'autokemping'],
                'cs' => ['name' => 'Autokemp', 'slug' => 'autokemp'],
                'hu' => ['name' => 'Autókemping', 'slug' => 'autokemping'],
                'bg' => ['name' => 'Автокъмпинг', 'slug' => 'avtokamping'],
                'sk' => ['name' => 'Autokemp', 'slug' => 'autokemp'],
            ],
            'naturist_camp' => [
                'en' => ['name' => 'Naturist Camp', 'slug' => 'naturist-camp'],
                'sl' => ['name' => 'Nudistični Kamp', 'slug' => 'nudisticni-kamp'],
                'de' => ['name' => 'FKK-Camp', 'slug' => 'fkk-camp'],
                'hr' => ['name' => 'Nudistički Kamp', 'slug' => 'nudisticki-kamp'],
                'fr' => ['name' => 'Camping Naturiste', 'slug' => 'camping-naturiste'],
                'es' => ['name' => 'Camping Naturista', 'slug' => 'camping-naturista'],
                'it' => ['name' => 'Campeggio Naturista', 'slug' => 'campeggio-naturista'],
                'ua' => ['name' => 'Нудистський Кемпінг', 'slug' => 'nudystskyi-kemping'],
                'ru' => ['name' => 'Нудистский Кемпинг', 'slug' => 'nudistskyi-kemping'],
                'pl' => ['name' => 'Kemping Naturystyczny', 'slug' => 'kemping-naturystyczny'],
                'cs' => ['name' => 'Nudistický Kemp', 'slug' => 'nudisticky-kemp'],
                'hu' => ['name' => 'Nudista Kemping', 'slug' => 'nudista-kemping'],
                'bg' => ['name' => 'Нудистки Къмпинг', 'slug' => 'nudistki-kamping'],
                'sk' => ['name' => 'Nudistický Kemp', 'slug' => 'nudisticky-kemp'],
            ],
        ];

        foreach ($accommodationTypes as $typeData) {
            // Insert accommodation type with explicit ID
            DB::table('accommodation_around')->insert([
                'id' => $typeData['id'],
                'code' => $typeData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert translations
            foreach ($translations[$typeData['code']] as $locale => $translation) {
                DB::table('accommodation_around_translation')->insert([
                    'accommodation_around_id' => $typeData['id'],
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Accommodation types seeded successfully with translations in 14 languages!');
    }
}


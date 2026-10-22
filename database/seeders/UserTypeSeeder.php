<?php

namespace Database\Seeders;

use App\Models\UserType;
use App\Models\UserTypeTranslation;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (UserType::count() > 0) {
            $this->command->info('User type table already seeded. Skipping.');
            return;
        }

        // Filter titles for all locales
        $filterTitles = [
            'en' => 'User Type',
            'sl' => 'Tip uporabnika',
            'de' => 'Benutzertyp',
            'hr' => 'Tip korisnika',
            'fr' => 'Type d\'utilisateur',
            'es' => 'Tipo de usuario',
            'it' => 'Tipo di utente',
            'ua' => 'Тип користувача',
            'ru' => 'Тип пользователя',
            'pl' => 'Typ użytkownika',
            'cs' => 'Typ uživatele',
            'hu' => 'Felhasználó típusa',
            'bg' => 'Тип потребител',
            'sk' => 'Typ používateľa',
            'da' => 'Brugertype',
            'pt' => 'Tipo de usuário',
            'nl' => 'Gebruikerstype',
            'el' => 'Τύπος χρήστη',
            'sq' => 'Lloji i përdoruesit',
        ];

        $userTypes = [
            [
                'id' => 1,
                'code' => 'adventurer',
                'show_filter' => true,
                'icon' => '/icons/filters/user_type.svg',
                'filter_order' => 1,
                'input_type' => 'checkbox',
            ],
            [
                'id' => 2,
                'code' => 'family',
                'show_filter' => true,
                'icon' => '/icons/filters/user_type.svg',
                'filter_order' => 2,
                'input_type' => 'checkbox',
            ],
            [
                'id' => 3,
                'code' => 'youth',
                'show_filter' => true,
                'icon' => '/icons/filters/user_type.svg',
                'filter_order' => 3,
                'input_type' => 'checkbox',
            ],
        ];

        $translations = [
            'adventurer' => [
                'en' => ['name' => 'Adventurer', 'slug' => 'adventurer'],
                'sl' => ['name' => 'Pustolov', 'slug' => 'pustolov'],
                'de' => ['name' => 'Abenteurer', 'slug' => 'abenteurer'],
                'hr' => ['name' => 'Pustolov', 'slug' => 'pustolov'],
                'fr' => ['name' => 'Aventurier', 'slug' => 'aventurier'],
                'es' => ['name' => 'Aventurero', 'slug' => 'aventurero'],
                'it' => ['name' => 'Avventuriero', 'slug' => 'avventuriero'],
                'ua' => ['name' => 'Шукач пригод', 'slug' => 'shukach-pryhod'],
                'ru' => ['name' => 'Искатель приключений', 'slug' => 'iskatel-prikliuchenii'],
                'pl' => ['name' => 'Poszukiwacz przygód', 'slug' => 'poszukiwacz-przygod'],
                'cs' => ['name' => 'Dobrodruh', 'slug' => 'dobrodruh'],
                'hu' => ['name' => 'Kalandor', 'slug' => 'kalandor'],
                'bg' => ['name' => 'Авантюрист', 'slug' => 'avantiurist'],
                'sk' => ['name' => 'Dobrodruh', 'slug' => 'dobrodruh'],
                'da' => ['name' => 'Eventyrer', 'slug' => 'eventyrer'],
                'pt' => ['name' => 'Aventureiro', 'slug' => 'aventureiro'],
                'nl' => ['name' => 'Avonturier', 'slug' => 'avonturier'],
                'el' => ['name' => 'Τυχοδιώκτης', 'slug' => 'tychodioktis'],
                'sq' => ['name' => 'Aventurier', 'slug' => 'aventurier'],
            ],
            'family' => [
                'en' => ['name' => 'Family', 'slug' => 'family'],
                'sl' => ['name' => 'Družina', 'slug' => 'druzina'],
                'de' => ['name' => 'Familie', 'slug' => 'familie'],
                'hr' => ['name' => 'Obitelj', 'slug' => 'obitelj'],
                'fr' => ['name' => 'Famille', 'slug' => 'famille'],
                'es' => ['name' => 'Familia', 'slug' => 'familia'],
                'it' => ['name' => 'Famiglia', 'slug' => 'famiglia'],
                'ua' => ['name' => 'Сім\'я', 'slug' => 'simia'],
                'ru' => ['name' => 'Семья', 'slug' => 'semia'],
                'pl' => ['name' => 'Rodzina', 'slug' => 'rodzina'],
                'cs' => ['name' => 'Rodina', 'slug' => 'rodina'],
                'hu' => ['name' => 'Család', 'slug' => 'csalad'],
                'bg' => ['name' => 'Семейство', 'slug' => 'semeistvo'],
                'sk' => ['name' => 'Rodina', 'slug' => 'rodina'],
                'da' => ['name' => 'Familie', 'slug' => 'familie'],
                'pt' => ['name' => 'Família', 'slug' => 'familia'],
                'nl' => ['name' => 'Familie', 'slug' => 'familie'],
                'el' => ['name' => 'Οικογένεια', 'slug' => 'oikogeneia'],
                'sq' => ['name' => 'Familje', 'slug' => 'familje'],
            ],
            'youth' => [
                'en' => ['name' => 'Youth', 'slug' => 'youth'],
                'sl' => ['name' => 'Mladina', 'slug' => 'mladina'],
                'de' => ['name' => 'Jugend', 'slug' => 'jugend'],
                'hr' => ['name' => 'Mladi', 'slug' => 'mladi'],
                'fr' => ['name' => 'Jeunesse', 'slug' => 'jeunesse'],
                'es' => ['name' => 'Juventud', 'slug' => 'juventud'],
                'it' => ['name' => 'Gioventù', 'slug' => 'gioventu'],
                'ua' => ['name' => 'Молодь', 'slug' => 'molod'],
                'ru' => ['name' => 'Молодежь', 'slug' => 'molodezh'],
                'pl' => ['name' => 'Młodzież', 'slug' => 'mlodziez'],
                'cs' => ['name' => 'Mládež', 'slug' => 'mladez'],
                'hu' => ['name' => 'Fiatalok', 'slug' => 'fiatalok'],
                'bg' => ['name' => 'Младежи', 'slug' => 'mladezhi'],
                'sk' => ['name' => 'Mládež', 'slug' => 'mladez'],
                'da' => ['name' => 'Ungdom', 'slug' => 'ungdom'],
                'pt' => ['name' => 'Juventude', 'slug' => 'juventude'],
                'nl' => ['name' => 'Jeugd', 'slug' => 'jeugd'],
                'el' => ['name' => 'Νεολαία', 'slug' => 'neolaia'],
                'sq' => ['name' => 'Rinia', 'slug' => 'rinia'],
            ],
        ];

        foreach ($userTypes as $userTypeData) {
            // Create or update user type with explicit ID
            $userType = UserType::updateOrCreate(
                ['id' => $userTypeData['id']],
                [
                    'code' => $userTypeData['code'],
                    'status_id' => 1,
                    'show_filter' => $userTypeData['show_filter'],
                    'icon' => $userTypeData['icon'],
                    'filter_order' => $userTypeData['filter_order'],
                    'input_type' => $userTypeData['input_type'],
                ]
            );

            // Create or update translations
            if (isset($translations[$userTypeData['code']])) {
                foreach ($translations[$userTypeData['code']] as $locale => $translation) {
                    UserTypeTranslation::updateOrCreate(
                        [
                            'user_type_id' => $userType->id,
                            'locale' => $locale,
                        ],
                        [
                            'name' => $translation['name'],
                            'slug' => $translation['slug'],
                            'filter_title' => $filterTitles[$locale] ?? $filterTitles['en'],
                        ]
                    );
                }
            }
        }

        $this->command->info('User types seeded successfully with translations in 14 languages!');
    }
}


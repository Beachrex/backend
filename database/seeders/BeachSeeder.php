<?php

namespace Database\Seeders;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\User;
use Illuminate\Database\Seeder;

class BeachSeeder extends Seeder
{
    public function run(): void
    {
        // Check if beaches already exist
        if (Beach::count() > 0) {
            $this->command->info('Beaches already seeded. Skipping.');
            return;
        }

        // Get the demo user ID
        $demoUser = User::where('email', 'demo@example.com')->first();
        $userId = $demoUser ? $demoUser->id : null;

        // Define comprehensive beach data
        $beaches = [
            [
                'name' => 'Copacabana Beach',
                'latitude' => -22.9711,
                'longitude' => -43.1822,
                'country_code' => 'BR',
                'continent_code' => 'SA',
                'translations' => [
                    'en' => [
                        'name' => 'Copacabana Beach',
                        'description' => 'One of the most famous beaches in the world, located in Rio de Janeiro. Known for its vibrant atmosphere, beautiful white sand, and iconic promenade.',
                        'slug' => 'copacabana-beach-rio'
                    ],
                    'si' => [
                        'name' => 'Plaža Copacabana',
                        'description' => 'Ena najbolj znanih plaž na svetu, ki se nahaja v Rio de Janeiru. Znana po živahni atmosferi, čudovitem belem pesku in ikonični promenadi.',
                        'slug' => 'plaza-copacabana-rio'
                    ]
                ]
            ],
            [
                'name' => 'Bondi Beach',
                'latitude' => -33.8915,
                'longitude' => 151.2767,
                'country_code' => 'AU',
                'continent_code' => 'OC',
                'translations' => [
                    'en' => [
                        'name' => 'Bondi Beach',
                        'description' => 'Sydney\'s most famous beach, perfect for surfing and sunbathing. Features golden sand, excellent surf conditions, and a vibrant beachside culture.',
                        'slug' => 'bondi-beach-sydney'
                    ],
                    'si' => [
                        'name' => 'Plaža Bondi',
                        'description' => 'Najbolj znana plaža v Sydneyju, popolna za deskanje na valovih in sončenje. Odlikuje se po zlatem pesku, odličnih pogojih za deskanje in živahni obmorski kulturi.',
                        'slug' => 'plaza-bondi-sydney'
                    ]
                ]
            ],
            [
                'name' => 'Red Beach Santorini',
                'latitude' => 36.3932,
                'longitude' => 25.4615,
                'country_code' => 'GR',
                'continent_code' => 'EU',
                'translations' => [
                    'en' => [
                        'name' => 'Red Beach Santorini',
                        'description' => 'A unique volcanic beach with striking red cliffs and crystal-clear waters. One of the most photographed beaches in Greece.',
                        'slug' => 'red-beach-santorini'
                    ],
                    'si' => [
                        'name' => 'Rdeča plaža Santorini',
                        'description' => 'Edinstvena vulkanska plaža z osupljivimi rdečimi pečinami in kristalno čisto vodo. Ena najbolj fotografiranih plaž v Grčiji.',
                        'slug' => 'rdeca-plaza-santorini'
                    ]
                ]
            ],
            [
                'name' => 'Waikiki Beach',
                'latitude' => 21.2793,
                'longitude' => -157.8293,
                'country_code' => 'US',
                'continent_code' => 'NA',
                'translations' => [
                    'en' => [
                        'name' => 'Waikiki Beach',
                        'description' => 'World-famous beach in Honolulu, Hawaii. Perfect for learning to surf with gentle waves and stunning Diamond Head backdrop.',
                        'slug' => 'waikiki-beach-hawaii'
                    ],
                    'si' => [
                        'name' => 'Plaža Waikiki',
                        'description' => 'Svetovno znana plaža v Honoluluju na Havajih. Popolna za učenje deskanja z nežnimi valovi in osupljivim ozadjem Diamond Head.',
                        'slug' => 'plaza-waikiki-havaji'
                    ]
                ]
            ],
            [
                'name' => 'Camps Bay Beach',
                'latitude' => -33.9553,
                'longitude' => 18.3773,
                'country_code' => 'ZA',
                'continent_code' => 'AF',
                'translations' => [
                    'en' => [
                        'name' => 'Camps Bay Beach',
                        'description' => 'Stunning white sand beach in Cape Town with the dramatic Twelve Apostles mountain range as backdrop. Popular for sunset viewing.',
                        'slug' => 'camps-bay-beach-cape-town'
                    ],
                    'si' => [
                        'name' => 'Plaža Camps Bay',
                        'description' => 'Osupljiva plaža z belim peskom v Cape Townu z dramatičnim gorskim vencem Twelve Apostles v ozadju. Priljubljena za opazovanje sončnega zahoda.',
                        'slug' => 'plaza-camps-bay-cape-town'
                    ]
                ]
            ],
            [
                'name' => 'Zlatni Rat Beach',
                'latitude' => 43.2569,
                'longitude' => 16.6378,
                'country_code' => 'HR',
                'continent_code' => 'EU',
                'translations' => [
                    'en' => [
                        'name' => 'Zlatni Rat Beach',
                        'description' => 'Famous golden pebble beach on Brač island, Croatia. Known for its unique shape that changes with wind and currents.',
                        'slug' => 'zlatni-rat-beach-brac'
                    ],
                    'si' => [
                        'name' => 'Plaža Zlatni Rat',
                        'description' => 'Znana zlata prodna plaža na otoku Brač na Hrvaškem. Znana po svoji edinstveni obliki, ki se spreminja z vetrom in tokovi.',
                        'slug' => 'plaza-zlatni-rat-brac'
                    ]
                ]
            ],
            [
                'name' => 'Portofino Beach',
                'latitude' => 44.3034,
                'longitude' => 9.2097,
                'country_code' => 'IT',
                'continent_code' => 'EU',
                'translations' => [
                    'en' => [
                        'name' => 'Portofino Beach',
                        'description' => 'Picturesque beach in the charming Italian Riviera town of Portofino. Crystal clear waters surrounded by colorful houses.',
                        'slug' => 'portofino-beach-italy'
                    ],
                    'si' => [
                        'name' => 'Plaža Portofino',
                        'description' => 'Slikovita plaža v čudovitem mestecu Portofino na italijanski rivieri. Kristalno čista voda obkrožena z barvitimi hišami.',
                        'slug' => 'plaza-portofino-italija'
                    ]
                ]
            ],
            [
                'name' => 'Bled Lake Beach',
                'latitude' => 46.3683,
                'longitude' => 14.1147,
                'country_code' => 'SI',
                'continent_code' => 'EU',
                'translations' => [
                    'en' => [
                        'name' => 'Lake Bled Beach',
                        'description' => 'Beautiful alpine lake beach in Slovenia with the iconic Bled Castle and island church. Perfect for swimming in crystal-clear mountain water.',
                        'slug' => 'lake-bled-beach-slovenia'
                    ],
                    'si' => [
                        'name' => 'Blejska plaža',
                        'description' => 'Čudovita alpska jezerska plaža v Sloveniji z ikoničnim Blejskim gradom in cerkvico na otoku. Popolna za kopanje v kristalno čisti gorski vodi.',
                        'slug' => 'blejska-plaza-slovenija'
                    ]
                ]
            ]
        ];

        foreach ($beaches as $beachData) {
            $beach = Beach::create([
                'user_id' => $userId,
                'name' => $beachData['name'],
                'latitude' => $beachData['latitude'],
                'longitude' => $beachData['longitude'],
                'country_code' => $beachData['country_code'],
                'continent_code' => $beachData['continent_code'],
                'status_id' => 1,
            ]);

            foreach ($beachData['translations'] as $locale => $translation) {
                BeachTranslation::create([
                    'beach_id' => $beach->id,
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'description' => $translation['description'],
                    'slug' => $translation['slug'],
                ]);
            }
        }

        $this->command->info('Beaches seeded successfully.');
    }
}

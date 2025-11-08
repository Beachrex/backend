<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IslandFilterTitleSeeder extends Seeder
{
    public function run(): void
    {
        $filterTitles = [
            'en' => 'Island',
            'sl' => 'Otok',
            'de' => 'Insel',
            'hr' => 'Otok',
            'fr' => 'Île',
            'es' => 'Isla',
            'it' => 'Isola',
            'ua' => 'Острів',
            'ru' => 'Остров',
            'pl' => 'Wyspa',
            'cs' => 'Ostrov',
            'hu' => 'Sziget',
            'bg' => 'Остров',
            'sk' => 'Ostrov',
        ];

        foreach ($filterTitles as $locale => $title) {
            DB::table('island_translation')
                ->where('locale', $locale)
                ->update(['filter_title' => $title]);
        }

        $this->command->info('Updated filter_title for all island translations.');
    }
}

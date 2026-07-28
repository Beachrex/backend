<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountryFilterTitleSeeder extends Seeder
{
    public function run(): void
    {
        $filterTitles = [
            'en' => 'Country',
            'sl' => 'Država',
            'de' => 'Land',
            'hr' => 'Država',
            'fr' => 'Pays',
            'es' => 'País',
            'it' => 'Paese',
            'ua' => 'Країна',
            'ru' => 'Страна',
            'pl' => 'Kraj',
            'cs' => 'Země',
            'hu' => 'Ország',
            'bg' => 'Държава',
            'sk' => 'Krajina',
            'da' => 'Land',
            'pt' => 'País',
            'nl' => 'Land',
            'el' => 'Χώρα',
            'sq' => 'Shteti',
        ];

        foreach ($filterTitles as $locale => $title) {
            DB::table('country_translation')
                ->where('locale', $locale)
                ->update(['filter_title' => $title]);
        }

        $this->command->info('Updated filter_title for all country translations.');
    }
}

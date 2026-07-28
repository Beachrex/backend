<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionFilterTitleSeeder extends Seeder
{
    public function run(): void
    {
        $filterTitles = [
            'en' => 'Region',
            'sl' => 'Regija',
            'de' => 'Region',
            'hr' => 'Regija',
            'fr' => 'Région',
            'es' => 'Región',
            'it' => 'Regione',
            'ua' => 'Регіон',
            'ru' => 'Регион',
            'pl' => 'Region',
            'cs' => 'Region',
            'hu' => 'Régió',
            'bg' => 'Регион',
            'sk' => 'Región',
            'da' => 'Region',
            'pt' => 'Região',
            'nl' => 'Regio',
            'el' => 'Περιοχή',
            'sq' => 'Rajoni',
        ];

        foreach ($filterTitles as $locale => $title) {
            DB::table('region_translation')
                ->where('locale', $locale)
                ->update(['filter_title' => $title]);
        }

        $this->command->info('Updated filter_title for all region translations.');
    }
}


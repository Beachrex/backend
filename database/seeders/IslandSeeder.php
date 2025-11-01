<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IslandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $islands = [
            'Als',
            'Euboea',
            'Fehmarn',
            'Funen',
            'Island Brač',
            'Island Čiovo',
            'Island Cres',
            'Island Hvar',
            'Island Korčula',
            'Island Krk',
            'island Lido',
            'Island Lošinj',
            'Island Murter',
            'Island Pag',
            'Island Pašman',
            'island Pellestrina',
            'Island Rab',
            'Island Šolta',
            'Island Ugljan',
            'Island Vir',
            'Island Vis',
            'Langeland',
            'Lefkada',
            'Poel',
            'Rügen',
            'Rømø',
            'Sardegna',
            'Sylt',
            'Usedom',
        ];

        foreach ($islands as $index => $islandName) {
            DB::table('island')->insert([
                'id' => $index + 1,
                'name' => $islandName,
                'slug' => Str::slug($islandName),
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContinentSeeder extends Seeder
{
    public function run(): void
    {
        // Check if already populated
        if (DB::table('continents')->count() > 0) {
            $this->command->info('Continents already seeded. Skipping.');
            return;
        }

        // Insert continents using Laravel's database abstraction (PostgreSQL compatible)
        $continents = [
            ['code' => 'AF', 'name' => 'Africa'],
            ['code' => 'AN', 'name' => 'Antarctica'],
            ['code' => 'AS', 'name' => 'Asia'],
            ['code' => 'EU', 'name' => 'Europe'],
            ['code' => 'NA', 'name' => 'North America'],
            ['code' => 'OC', 'name' => 'Oceania'],
            ['code' => 'SA', 'name' => 'South America'],
        ];

        DB::table('continents')->insert($continents);
        $this->command->info('Continents seeded successfully.');
    }
}

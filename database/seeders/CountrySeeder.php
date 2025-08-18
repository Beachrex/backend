<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        // Path to SQL file
        $path = database_path('sql/countries.sql');

        // Check if table exists
        if (Schema::hasTable('countries')) {
            // Optional: skip if already populated
            if (DB::table('countries')->count() > 0) {
                $this->command->info('Countries already seeded. Skipping.');
                return;
            }
        }

        // Check if file exists and run it
        if (File::exists($path)) {
            DB::unprepared(File::get($path));
            $this->command->info('Countries seeded from SQL.');
        } else {
            $this->command->error("SQL file not found: $path");
        }
    }
}

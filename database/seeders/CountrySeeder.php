<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        // Check if already populated
        if (DB::table('countries')->count() > 0) {
            $this->command->info('Countries already seeded. Skipping.');
            return;
        }

        // Path to SQL file
        $path = database_path('sql/countries.sql');

        if (File::exists($path)) {
            // Read the SQL file and convert MySQL syntax to PostgreSQL
            $sql = File::get($path);

            // Convert MySQL backticks to PostgreSQL double quotes
            $sql = str_replace('`', '"', $sql);

            // Fix escaped single quotes for PostgreSQL (MySQL uses \' but PostgreSQL uses '')
            $sql = str_replace("\\'", "''", $sql);

            // Remove MySQL-specific commands that PostgreSQL doesn't support
            $sql = preg_replace('/SET SQL_MODE.*?;/', '', $sql);
            $sql = preg_replace('/START TRANSACTION;/', '', $sql);
            $sql = preg_replace('/SET time_zone.*?;/', '', $sql);
            $sql = preg_replace('/\/\*.*?\*\/;?/s', '', $sql);

            // Extract only the INSERT statement
            preg_match('/INSERT INTO.*?;/s', $sql, $matches);

            if (!empty($matches[0])) {
                DB::unprepared($matches[0]);
                $this->command->info('Countries seeded successfully.');
            } else {
                $this->command->error('Could not find INSERT statement in SQL file.');
            }
        } else {
            $this->command->error("SQL file not found: $path");
        }
    }
}

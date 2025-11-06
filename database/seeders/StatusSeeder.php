<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        try {
            if (DB::table('status')->count() > 0) {
                $this->command->info('Status table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        DB::table('status')->insert([
            ['id' => 1, 'name' => 'Active'],
            ['id' => 2, 'name' => 'Disabled'],
            ['id' => 3, 'name' => 'Deleted'],
            ['id' => 4, 'name' => 'Draft'],
            ['id' => 5, 'name' => 'Local review'],
            ['id' => 6, 'name' => 'Main Review'],
            ['id' => 7, 'name' => 'Payment waiting'],
            ['id' => 8, 'name' => 'Payment canceled'],
            ['id' => 9, 'name' => 'Payment received'],
            ['id' => 10, 'name' => 'Demo apartment - for preview'],
            ['id' => 11, 'name' => 'Mobile review'],
            ['id' => 12, 'name' => 'Mobile active'],
            ['id' => 13, 'name' => 'Media Uploaded'],
        ]);

        $this->command->info('Status table seeded.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Beach;
use App\Models\BeachTranslation;
use Illuminate\Database\Seeder;

class BeachSeeder extends Seeder
{
    public function run(): void
    {
        $beach = Beach::create([
            'user_id' => 1,
            'name' => 'Fallback Beach Name',
            'latitude' => 45.1234567,
            'longitude' => 13.1234567,
            'status_id' => 1,
        ]);

        BeachTranslation::create([
            'beach_id' => $beach->id,
            'locale' => 'en',
            'name' => 'Children’s Paradise Beach',
            'description' => 'A beautiful family beach...',
            'slug' => 'childrens-paradise-beach',
        ]);
    }
}

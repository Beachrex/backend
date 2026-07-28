<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SwimmingDepthOfOneMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('swimming_depth_of_one_m')->count() > 0) {
                $this->command->info('swimming_depth_of_one_m table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $swimmingDepths = [
            ['id' => 1, 'code' => '0m'],
            ['id' => 2, 'code' => '3m'],
            ['id' => 3, 'code' => '5m'],
            ['id' => 4, 'code' => '10m'],
            ['id' => 5, 'code' => '15m'],
            ['id' => 6, 'code' => '20m'],
            ['id' => 7, 'code' => '30m'],
            ['id' => 8, 'code' => '40m'],
            ['id' => 9, 'code' => '50m'],
            ['id' => 10, 'code' => '75m'],
            ['id' => 11, 'code' => '100m'],
            ['id' => 12, 'code' => 'more_100m'],
            ['id' => 13, 'code' => '0m_5m'],
            ['id' => 14, 'code' => '0m_10m'],
            ['id' => 15, 'code' => '0m_15m'],
            ['id' => 16, 'code' => '0m_20m'],
            ['id' => 17, 'code' => '0m_25m'],
            ['id' => 18, 'code' => '0m_25m_plus'],
        ];

        $languages = ['en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq'];

        foreach ($swimmingDepths as $depthData) {
            // Insert swimming depth with explicit ID
            DB::table('swimming_depth_of_one_m')->insert([
                'id' => $depthData['id'],
                'code' => $depthData['code'],
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Generate translations for each language
            foreach ($languages as $locale) {
                $name = $this->getTranslatedName($depthData['code'], $locale);
                $slug = $this->getSlug($depthData['code']);

                DB::table('swimming_depth_of_one_m_translation')->insert([
                    'swimming_depth_of_one_m_id' => $depthData['id'],
                    'locale' => $locale,
                    'name' => $name,
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Swimming depths seeded successfully with translations in 19 languages!');
    }

    /**
     * Get translated name for swimming depth
     */
    private function getTranslatedName(string $code, string $locale): string
    {
        // For Cyrillic languages (ua, ru, bg), replace 'm' with 'м'
        $isCyrillic = in_array($locale, ['ua', 'ru', 'bg']);

        // Handle special cases
        if ($code === 'more_100m') {
            return $isCyrillic ? '>100м' : '>100m';
        }

        if ($code === '0m_5m') {
            return $isCyrillic ? '0м - 5м' : '0m - 5m';
        }

        if ($code === '0m_10m') {
            return $isCyrillic ? '0м - 10м' : '0m - 10m';
        }

        if ($code === '0m_15m') {
            return $isCyrillic ? '0м - 15м' : '0m - 15m';
        }

        if ($code === '0m_20m') {
            return $isCyrillic ? '0м - 20м' : '0m - 20m';
        }

        if ($code === '0m_25m') {
            return $isCyrillic ? '0м - 25м' : '0m - 25m';
        }

        if ($code === '0m_25m_plus') {
            return $isCyrillic ? '0м - 25м+' : '0m - 25m+';
        }

        // For simple numeric values (0m, 3m, 5m, etc.)
        return $isCyrillic ? str_replace('m', 'м', $code) : $code;
    }

    /**
     * Get slug for swimming depth
     */
    private function getSlug(string $code): string
    {
        return str_replace('_', '-', $code);
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CityBadgeArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Assigns badges and articles to cities.
     * This seeder should run AFTER BadgeSeeder and ArticleSeeder.
     */
    public function run(): void
    {
        // City-specific badges:
        // 19: city-marina
        // 20: city-promenade
        // 21: city-old-town
        // 22: city-nightlife
        // 23: city-local-market
        // 24: city-cultural-events

        // Assign badges to major Croatian coastal cities

        // Split (ID: 60) - Major city with marina, promenade, old town, nightlife, market, cultural events
        $this->assignBadgesToCity(60, [19, 20, 21, 22, 23, 24]);

        // Dubrovnik (ID: 29) - Historic city with old town, marina, promenade, cultural events, market
        $this->assignBadgesToCity(29, [19, 20, 21, 23, 24]);

        // Zadar (ID: 90) - City with old town, promenade, marina, nightlife, market
        $this->assignBadgesToCity(90, [19, 20, 21, 22, 23]);

        // Pula (ID: 9) - Historic city with old town, marina, cultural events, market
        $this->assignBadgesToCity(9, [19, 21, 23, 24]);

        // Rovinj (ID: 20) - Charming town with old town, marina, promenade, market
        $this->assignBadgesToCity(20, [19, 20, 21, 23]);

        // Poreč (ID: 18) - Tourist city with marina, promenade, old town, nightlife
        $this->assignBadgesToCity(18, [19, 20, 21, 22]);

        // Hvar (ID: 148) - Island city with marina, promenade, nightlife, old town
        $this->assignBadgesToCity(148, [19, 20, 21, 22]);

        // Krk (ID: 48) - Island city with old town, marina, promenade
        $this->assignBadgesToCity(48, [19, 20, 21]);

        // Rab (ID: 187) - Island city with old town, marina
        $this->assignBadgesToCity(187, [19, 21]);

        // Korčula (ID: 130) - Historic island city with old town, marina
        $this->assignBadgesToCity(130, [19, 21]);

        // Assign articles to cities
        // Articles 13-16 are city-related articles

        // Split - Major city gets multiple articles
        $this->assignArticlesToCity(60, [13, 14]);

        // Dubrovnik - Historic city
        $this->assignArticlesToCity(29, [13, 15]);

        // Zadar - Cultural city
        $this->assignArticlesToCity(90, [14, 16]);

        // Pula - Historic city
        $this->assignArticlesToCity(9, [13]);

        // Rovinj - Charming town
        $this->assignArticlesToCity(20, [14]);

        // Hvar - Island city
        $this->assignArticlesToCity(148, [15]);

        // Poreč - Tourist city
        $this->assignArticlesToCity(18, [16]);

        $this->command->info('✅ Assigned badges and articles to cities');
    }

    /**
     * Helper method to assign badges to a city
     */
    private function assignBadgesToCity(int $cityId, array $badgeIds): void
    {
        foreach ($badgeIds as $badgeId) {
            DB::table('badge_city')->insert([
                'badge_id' => $badgeId,
                'city_id' => $cityId,
            ]);
        }
    }

    /**
     * Helper method to assign articles to a city
     */
    private function assignArticlesToCity(int $cityId, array $articleIds): void
    {
        foreach ($articleIds as $articleId) {
            DB::table('article_city')->insert([
                'article_id' => $articleId,
                'city_id' => $cityId,
            ]);
        }
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionBadgeArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Assigns badges and articles to regions.
     * This seeder should run AFTER BadgeSeeder and ArticleSeeder.
     */
    public function run(): void
    {
        // Check if regions exist (they come from old database, not from seeders)
        $kvarnerExists = DB::table('region')->where('id', 7)->exists();
        $istriaExists = DB::table('region')->where('id', 5)->exists();
        $dalmatiaExists = DB::table('region')->where('id', 4)->exists();

        if (!$kvarnerExists && !$istriaExists && !$dalmatiaExists) {
            $this->command->warn('⚠️  Skipping RegionBadgeArticleSeeder - no regions found in database');
            $this->command->info('   Regions are populated from the old database, not from seeders');
            return;
        }

        // Assign Krk-specific badges to Kvarner region (ID: 7)
        // Badges: 13 (Krk Lamb), 14 (Žlahtina Wine), 15 (Crna Ovca Festival)
        if ($kvarnerExists) {
            $krkBadges = [13, 14, 15];
            foreach ($krkBadges as $badgeId) {
                $this->assignBadgeToRegion($badgeId, 7);
            }
        }

        // Assign all other badges to Istria region (ID: 5)
        // General badges that can represent a popular coastal region
        if ($istriaExists) {
            $allBadges = DB::table('badge')->pluck('id')->toArray();
            $krkBadges = [13, 14, 15];
            $istriaBadges = array_diff($allBadges, $krkBadges);
            foreach ($istriaBadges as $badgeId) {
                $this->assignBadgeToRegion($badgeId, 5);
            }
        }

        // Assign articles to regions based on their content:
        // Articles 1-4: General region articles → Istria (ID: 5)
        if ($istriaExists) {
            $istriaArticles = [1, 2, 3, 4];
            foreach ($istriaArticles as $articleId) {
                $this->assignArticleToRegion($articleId, 5);
            }
        }

        // Articles 5-8: Regional articles → Kvarner (ID: 7)
        if ($kvarnerExists) {
            $kvarnerArticles = [5, 6, 7, 8];
            foreach ($kvarnerArticles as $articleId) {
                $this->assignArticleToRegion($articleId, 7);
            }
        }

        // Articles 9-12: Island articles → Kvarner (ID: 7) - has many islands including Krk
        if ($kvarnerExists) {
            $islandArticles = [9, 10, 11, 12];
            foreach ($islandArticles as $articleId) {
                $this->assignArticleToRegion($articleId, 7);
            }
        }

        // Articles 13-16: City articles → Dalmatia-Split (ID: 4) - largest region with major cities
        if ($dalmatiaExists) {
            $cityArticles = [13, 14, 15, 16];
            foreach ($cityArticles as $articleId) {
                $this->assignArticleToRegion($articleId, 4);
            }
        }

        $this->command->info('✅ Assigned badges and articles to regions');
    }

    /**
     * Assign a badge to a region (skip if already exists)
     */
    private function assignBadgeToRegion(int $badgeId, int $regionId): void
    {
        $exists = DB::table('badge_region')
            ->where('badge_id', $badgeId)
            ->where('region_id', $regionId)
            ->exists();

        if (!$exists) {
            DB::table('badge_region')->insert([
                'badge_id' => $badgeId,
                'region_id' => $regionId,
            ]);
        }
    }

    /**
     * Assign an article to a region (skip if already exists)
     */
    private function assignArticleToRegion(int $articleId, int $regionId): void
    {
        $exists = DB::table('article_region')
            ->where('article_id', $articleId)
            ->where('region_id', $regionId)
            ->exists();

        if (!$exists) {
            DB::table('article_region')->insert([
                'article_id' => $articleId,
                'region_id' => $regionId,
            ]);
        }
    }
}


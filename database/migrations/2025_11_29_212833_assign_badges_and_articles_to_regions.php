<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip this migration in testing environment (no seeded data)
        if (app()->environment('testing')) {
            return;
        }

        // Assign Krk-specific badges to Kvarner region (ID: 7)
        // Badges: 13 (Krk Lamb), 14 (Žlahtina Wine), 15 (Crna Ovca Festival)
        $krkBadges = [13, 14, 15];
        foreach ($krkBadges as $badgeId) {
            DB::table('badge_region')->insert([
                'badge_id' => $badgeId,
                'region_id' => 7,
            ]);
        }

        // Assign all other badges to Istria region (ID: 5)
        // General badges that can represent a popular coastal region
        $allBadges = DB::table('badge')->pluck('id')->toArray();
        $istriaBadges = array_diff($allBadges, $krkBadges);
        foreach ($istriaBadges as $badgeId) {
            DB::table('badge_region')->insert([
                'badge_id' => $badgeId,
                'region_id' => 5,
            ]);
        }

        // Assign articles to regions based on their content:
        // Articles 1-4: General region articles → Istria (ID: 5)
        $istriaArticles = [1, 2, 3, 4];
        foreach ($istriaArticles as $articleId) {
            DB::table('article_region')->insert([
                'article_id' => $articleId,
                'region_id' => 5,
            ]);
        }

        // Articles 5-8: Regional articles → Kvarner (ID: 7)
        $kvarnerArticles = [5, 6, 7, 8];
        foreach ($kvarnerArticles as $articleId) {
            DB::table('article_region')->insert([
                'article_id' => $articleId,
                'region_id' => 7,
            ]);
        }

        // Articles 9-12: Island articles → Kvarner (ID: 7) - has many islands including Krk
        $islandArticles = [9, 10, 11, 12];
        foreach ($islandArticles as $articleId) {
            DB::table('article_region')->insert([
                'article_id' => $articleId,
                'region_id' => 7,
            ]);
        }

        // Articles 13-16: City articles → Dalmatia-Split (ID: 4) - largest region with major cities
        $cityArticles = [13, 14, 15, 16];
        foreach ($cityArticles as $articleId) {
            DB::table('article_region')->insert([
                'article_id' => $articleId,
                'region_id' => 4,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all badge-region and article-region associations created by this migration
        DB::table('badge_region')->whereIn('region_id', [5, 7])->delete();
        DB::table('article_region')->whereIn('region_id', [4, 5, 7])->delete();
    }
};

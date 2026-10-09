<?php

namespace App\Filament\BrAdmin\Widgets;

use App\Filament\BrAdmin\Resources\ArticleResource;
use App\Filament\BrAdmin\Resources\BadgeResource;
use App\Filament\BrAdmin\Resources\BeachResource;
use App\Models\Article;
use App\Models\Badge;
use App\Models\Beach;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TranslationCompletenessWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    /**
     * All supported locales in the system
     */
    private const SUPPORTED_LOCALES = \App\Http\Middleware\SetLocaleFromHeader::SUPPORTED_LOCALES;

    protected function getStats(): array
    {
        // Get translation completeness for beaches
        $beachStats = $this->getBeachTranslationStats();

        // Get translation completeness for articles
        $articleStats = $this->getArticleTranslationStats();

        // Get translation completeness for badges
        $badgeStats = $this->getBadgeTranslationStats();

        return [
            Stat::make('Beach Translations', number_format($beachStats['missing']) . ' / ' . number_format($beachStats['total']))
                ->description(
                    number_format($beachStats['missing']) . ' beaches missing translations (' .
                    round($beachStats['complete_percentage'], 1) . '% complete across all ' . count(self::SUPPORTED_LOCALES) . ' languages) - Click to view'
                )
                ->descriptionIcon('heroicon-m-language')
                ->color($beachStats['missing'] > 0 ? 'warning' : 'success')
                ->url(BeachResource::getUrl('index', ['tableFilters' => ['missing_translations' => ['value' => 0]]])),

            Stat::make('Article Translations', number_format($articleStats['missing']) . ' / ' . number_format($articleStats['total']))
                ->description(
                    number_format($articleStats['missing']) . ' articles missing translations (' .
                    round($articleStats['complete_percentage'], 1) . '% complete across all ' . count(self::SUPPORTED_LOCALES) . ' languages) - Click to view'
                )
                ->descriptionIcon('heroicon-m-document-text')
                ->color($articleStats['missing'] > 0 ? 'warning' : 'success')
                ->url(ArticleResource::getUrl('index', ['tableFilters' => ['missing_translations' => ['value' => 0]]])),

            Stat::make('Badge Translations', number_format($badgeStats['missing']) . ' / ' . number_format($badgeStats['total']))
                ->description(
                    number_format($badgeStats['missing']) . ' badges missing translations (' .
                    round($badgeStats['complete_percentage'], 1) . '% complete across all ' . count(self::SUPPORTED_LOCALES) . ' languages) - Click to view'
                )
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($badgeStats['missing'] > 0 ? 'warning' : 'success')
                ->url(BadgeResource::getUrl('index', ['tableFilters' => ['missing_translations' => ['value' => 0]]])),
        ];
    }

    /**
     * Get beach translation statistics
     */
    private function getBeachTranslationStats(): array
    {
        $totalBeaches = Beach::count();

        // Count beaches that have all 14 language translations
        $beachesWithAllTranslations = Beach::has('translations', '=', count(self::SUPPORTED_LOCALES))->count();

        // Beaches missing at least one translation
        $beachesMissingTranslations = $totalBeaches - $beachesWithAllTranslations;

        // Calculate overall completion percentage
        $totalPossibleTranslations = $totalBeaches * count(self::SUPPORTED_LOCALES);
        $actualTranslations = DB::table('beach_translation')->count();
        $completePercentage = $totalPossibleTranslations > 0
            ? ($actualTranslations / $totalPossibleTranslations) * 100
            : 0;

        return [
            'total' => $totalBeaches,
            'complete' => $beachesWithAllTranslations,
            'missing' => $beachesMissingTranslations,
            'complete_percentage' => $completePercentage,
        ];
    }

    /**
     * Get article translation statistics
     */
    private function getArticleTranslationStats(): array
    {
        $totalArticles = Article::count();

        // Count articles that have all 14 language translations
        $articlesWithAllTranslations = Article::has('translations', '=', count(self::SUPPORTED_LOCALES))->count();

        // Articles missing at least one translation
        $articlesMissingTranslations = $totalArticles - $articlesWithAllTranslations;

        // Calculate overall completion percentage
        $totalPossibleTranslations = $totalArticles * count(self::SUPPORTED_LOCALES);
        $actualTranslations = DB::table('article_translation')->count();
        $completePercentage = $totalPossibleTranslations > 0
            ? ($actualTranslations / $totalPossibleTranslations) * 100
            : 0;

        return [
            'total' => $totalArticles,
            'complete' => $articlesWithAllTranslations,
            'missing' => $articlesMissingTranslations,
            'complete_percentage' => $completePercentage,
        ];
    }

    /**
     * Get badge translation statistics
     */
    private function getBadgeTranslationStats(): array
    {
        $totalBadges = Badge::count();

        // Count badges that have all 14 language translations
        $badgesWithAllTranslations = Badge::has('translations', '=', count(self::SUPPORTED_LOCALES))->count();

        // Badges missing at least one translation
        $badgesMissingTranslations = $totalBadges - $badgesWithAllTranslations;

        // Calculate overall completion percentage
        $totalPossibleTranslations = $totalBadges * count(self::SUPPORTED_LOCALES);
        $actualTranslations = DB::table('badge_translation')->count();
        $completePercentage = $totalPossibleTranslations > 0
            ? ($actualTranslations / $totalPossibleTranslations) * 100
            : 0;

        return [
            'total' => $totalBadges,
            'complete' => $badgesWithAllTranslations,
            'missing' => $badgesMissingTranslations,
            'complete_percentage' => $completePercentage,
        ];
    }
}


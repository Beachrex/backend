<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Models\BeachTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FixDuplicateBeachSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beaches:fix-duplicate-slugs {--dry-run : Show what would be changed without making changes} {--locale= : Specific locale to fix (default: all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find and fix duplicate beach slugs by appending city name for all languages';

    /**
     * All supported locales
     */
    private const LOCALES = \App\Http\Middleware\SetLocaleFromHeader::SUPPORTED_LOCALES;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $specificLocale = $this->option('locale');
        $dryRun = $this->option('dry-run');

        // Determine which locales to process
        $locales = $specificLocale ? [$specificLocale] : self::LOCALES;

        $this->info("🔍 Fixing duplicate beach slugs");
        $this->info("Languages: " . implode(', ', $locales));
        if ($dryRun) {
            $this->warn("⚠️  DRY RUN MODE - No changes will be made");
        }
        $this->newLine();

        $totalFixedAll = 0;

        foreach ($locales as $locale) {
            $totalFixedAll += $this->processLocale($locale, $dryRun);
        }

        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        if ($dryRun) {
            $this->info("✓ DRY RUN COMPLETE - Would fix {$totalFixedAll} slugs across all languages");
            $this->info("Run without --dry-run to apply changes");
        } else {
            $this->info("✅ COMPLETE - Fixed {$totalFixedAll} duplicate slugs across all languages!");
            $this->warn("⚠️  Remember to clear API cache: php artisan cache:clear");
        }

        return 0;
    }

    /**
     * Process a single locale
     */
    private function processLocale(string $locale, bool $dryRun): int
    {
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Processing locale: {$locale}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Find duplicate slugs
        $duplicates = DB::table('beach_translation')
            ->select('slug', DB::raw('COUNT(*) as slug_count'))
            ->where('locale', $locale)
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info("  ✓ No duplicate slugs found for {$locale}");
            return 0;
        }

        $this->warn("  Found {$duplicates->count()} duplicate slug(s) for {$locale}");

        $totalFixed = 0;

        foreach ($duplicates as $duplicate) {
            $this->newLine();
            $this->error("  Duplicate slug: {$duplicate->slug} ({$duplicate->slug_count} beaches)");

            // Get all translations with this slug
            $translations = BeachTranslation::where('slug', $duplicate->slug)
                ->where('locale', $locale)
                ->with(['beach.city.translations', 'beach.region.translations'])
                ->get();

            $this->table(
                ['Beach ID', 'Name', 'Region', 'City', 'Current Slug'],
                $translations->map(function ($translation) use ($locale) {
                    $cityTranslation = $translation->beach->city?->translations->firstWhere('locale', $locale);
                    $regionTranslation = $translation->beach->region?->translations->firstWhere('locale', $locale);

                    return [
                        $translation->beach_id,
                        $translation->name,
                        $regionTranslation?->name ?? 'N/A',
                        $cityTranslation?->name ?? 'N/A',
                        $translation->slug,
                    ];
                })
            );

            // Fix each duplicate by appending city name
            foreach ($translations as $translation) {
                $beach = $translation->beach;
                $cityTranslation = $beach->city?->translations->firstWhere('locale', $locale);
                $citySlug = $cityTranslation?->slug ?? null;

                if (!$citySlug) {
                    $this->warn("  ⚠ Beach {$beach->id} has no city - skipping");
                    continue;
                }

                // Generate new unique slug
                $newSlug = $this->generateUniqueSlug($duplicate->slug, $citySlug, $locale, $translation->beach_id);

                if ($dryRun) {
                    $this->info("  [DRY RUN] Would update Beach {$beach->id}: {$translation->slug} → {$newSlug}");
                } else {
                    $oldSlug = $translation->slug;

                    // Update the slug
                    $translation->slug = $newSlug;
                    $translation->save();

                    // Create redirect record (optional - for handling old URLs)
                    DB::table('beach_slug_redirects')->updateOrInsert(
                        [
                            'old_slug' => $oldSlug,
                            'locale' => $locale,
                        ],
                        [
                            'new_slug' => $newSlug,
                            'beach_id' => $beach->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $this->info("  ✓ Updated Beach {$beach->id}: {$oldSlug} → {$newSlug}");
                    $totalFixed++;
                }
            }
        }

        if ($totalFixed > 0) {
            $this->info("  ✓ Fixed {$totalFixed} slug(s) for {$locale}");
        }

        return $totalFixed;
    }

    /**
     * Generate a unique slug by appending city name
     */
    private function generateUniqueSlug(string $baseSlug, string $citySlug, string $locale, int $beachId): string
    {
        // Try appending city name first
        $newSlug = $baseSlug . '-' . $citySlug;

        // Check if this slug already exists (excluding current beach)
        $exists = BeachTranslation::where('slug', $newSlug)
            ->where('locale', $locale)
            ->where('beach_id', '!=', $beachId)
            ->exists();

        if (!$exists) {
            return $newSlug;
        }

        // If still duplicate, append a number
        $counter = 2;
        while (true) {
            $attemptSlug = $newSlug . '-' . $counter;

            $exists = BeachTranslation::where('slug', $attemptSlug)
                ->where('locale', $locale)
                ->where('beach_id', '!=', $beachId)
                ->exists();

            if (!$exists) {
                return $attemptSlug;
            }

            $counter++;
        }
    }
}


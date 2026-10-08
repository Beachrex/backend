<?php

namespace App\Console\Commands;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\StaticPage;
use App\Models\StaticPageTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the footer CMS pages from `database/content/static-pages/{locale}.json` (TASK-004 step 3).
 *
 * One JSON file per backend locale, keyed by page `code`:
 * { "privacy-policy": { "title", "slug", "meta_title", "meta_description", "content" }, ... }
 *
 * Idempotent: pages are matched by `code`, translations by (page, locale). A missing page is
 * created (active, in the footer); existing pages keep their status, footer flag and order.
 * Nothing is deleted, and pages that are not in the files are not touched. Cache keys are
 * cleared by the StaticPage / StaticPageTranslation model events.
 */
class SyncStaticPages extends Command
{
    protected $signature = 'static-pages:sync
                            {--dry-run : Show what would change, change nothing}
                            {--locale=* : Only these backend locales (default: every file)}
                            {--path=database/content/static-pages : Folder with the {locale}.json files}';

    protected $description = 'Create or update the footer CMS pages and their translations from the content files';

    /** Footer order for pages created by this command. */
    private const SORT_ORDER = [
        'about-us' => 1,
        'privacy-policy' => 2,
        'terms-of-use' => 3,
        'contribution-rules' => 4,
        'contact' => 5,
        'cookie-policy' => 6,
        'ai-assistant-connector' => 7,
    ];

    private const FIELDS = ['title', 'slug', 'meta_title', 'meta_description', 'content'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $content = $this->loadContent();
        if ($content === null) {
            return self::FAILURE;
        }

        $errors = $this->validateContent($content);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $stats = ['pages_created' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0];

        $apply = function () use ($content, $dryRun, &$stats) {
            $codes = array_unique(array_merge(...array_map('array_keys', array_values($content))));
            foreach ($codes as $code) {
                $page = StaticPage::withTrashed()->where('code', $code)->first();
                if ($page?->trashed()) {
                    // Deleted in the admin on purpose: leave it alone.
                    $this->warn("skip {$code}: the page is deleted in the admin");
                    continue;
                }
                if (!$page) {
                    $stats['pages_created']++;
                    $this->line("<info>create page</info> {$code}");
                    if (!$dryRun) {
                        $page = StaticPage::create([
                            'code' => $code,
                            'sort_order' => self::SORT_ORDER[$code] ?? 99,
                            'is_in_footer' => true,
                            'status_id' => 1,
                        ]);
                    }
                }

                foreach ($content as $locale => $pages) {
                    if (!isset($pages[$code])) {
                        continue;
                    }
                    $values = array_intersect_key($pages[$code], array_flip(self::FIELDS));
                    $existing = $page
                        ? StaticPageTranslation::where('static_page_id', $page->id)->where('locale', $locale)->first()
                        : null;

                    if (!$existing) {
                        $stats['created']++;
                        $this->line("  <info>+</info> {$code} [{$locale}] /p/{$values['slug']}");
                        if (!$dryRun) {
                            StaticPageTranslation::create(['static_page_id' => $page->id, 'locale' => $locale] + $values);
                        }
                        continue;
                    }

                    $changed = array_keys(array_filter($values, fn ($v, $k) => (string) $existing->{$k} !== (string) $v, ARRAY_FILTER_USE_BOTH));
                    if ($changed === []) {
                        $stats['unchanged']++;
                        continue;
                    }
                    $stats['updated']++;
                    $this->line("  <comment>~</comment> {$code} [{$locale}] " . implode(', ', $changed));
                    if (!$dryRun) {
                        $existing->fill($values)->save();
                    }
                }
            }
        };

        $dryRun ? $apply() : DB::transaction($apply);

        $this->newLine();
        $this->info(($dryRun ? '[dry run] ' : '') . sprintf(
            'Pages created: %d · translations created: %d, updated: %d, unchanged: %d',
            $stats['pages_created'], $stats['created'], $stats['updated'], $stats['unchanged'],
        ));

        return self::SUCCESS;
    }

    /** @return array<string,array<string,array<string,string>>>|null locale => code => fields */
    private function loadContent(): ?array
    {
        $dir = base_path($this->option('path'));
        $only = (array) $this->option('locale');
        $content = [];

        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $locale = basename($file, '.json');
            if ($only !== [] && !in_array($locale, $only, true)) {
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                $this->error("Invalid JSON: {$file}");
                return null;
            }
            $content[$locale] = $data;
        }

        if ($content === []) {
            $this->error("No content files found in {$dir}");
            return null;
        }
        ksort($content);
        return $content;
    }

    /** @return array<int,string> */
    private function validateContent(array $content): array
    {
        $errors = [];
        foreach ($content as $locale => $pages) {
            if (!in_array($locale, SetLocaleFromHeader::SUPPORTED_LOCALES, true)) {
                $errors[] = "{$locale}.json: unknown locale (supported: " . implode(', ', SetLocaleFromHeader::SUPPORTED_LOCALES) . ')';
                continue;
            }
            $slugs = [];
            foreach ($pages as $code => $page) {
                foreach (self::FIELDS as $field) {
                    if (!is_string($page[$field] ?? null) || trim($page[$field]) === '') {
                        $errors[] = "{$locale}.json {$code}: missing {$field}";
                    }
                }
                $slug = (string) ($page['slug'] ?? '');
                if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                    $errors[] = "{$locale}.json {$code}: slug '{$slug}' must be lowercase ASCII words joined by hyphens";
                }
                if (isset($slugs[$slug])) {
                    $errors[] = "{$locale}.json: slug '{$slug}' used by {$slugs[$slug]} and {$code}";
                }
                $slugs[$slug] = $code;

                // The slug must not belong to another page in the same locale (a page outside the files).
                $taken = StaticPageTranslation::where('locale', $locale)->where('slug', $slug)
                    ->whereHas('staticPage', fn ($q) => $q->where('code', '!=', $code))
                    ->exists();
                if ($taken) {
                    $errors[] = "{$locale}.json {$code}: slug '{$slug}' already belongs to another page";
                }
            }
        }
        return $errors;
    }
}

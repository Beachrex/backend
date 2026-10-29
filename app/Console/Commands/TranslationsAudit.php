<?php

namespace App\Console\Commands;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Support\TranslationTables;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only report of translation gaps in the database (owner 2026-10-12: Greek, Albanian, Russian,
 * Ukrainian, Bulgarian pages showed English names and raw codes such as "small_stones").
 *
 * For every `*_translation` table with a `name` column and every supported locale it counts:
 *  - missing: parent rows with no row in that locale (the API then falls back to English or the code);
 *  - same_as_en: rows whose name is identical to the English name (often an untranslated copy).
 */
class TranslationsAudit extends Command
{
    protected $signature = 'translations:audit
                            {--table=* : Only these translation tables}
                            {--examples=0 : Show up to N untranslated names per table and locale}';

    protected $description = 'Read-only report: missing or English-only translations per table and locale';

    public function handle(): int
    {
        $locales = array_values(array_diff(SetLocaleFromHeader::SUPPORTED_LOCALES, ['en']));
        $examples = (int) $this->option('examples');
        $rows = [];

        foreach (TranslationTables::all($this->option('table')) as ['table' => $table, 'parent' => $parent, 'fk' => $fk]) {
            $total = DB::table($parent)->count();
            $en = DB::table($table)->where('locale', 'en')->pluck('name', $fk);
            foreach ($locales as $locale) {
                $names = DB::table($table)->where('locale', $locale)->pluck('name', $fk);
                $missing = $total - $names->filter(fn ($n) => trim((string) $n) !== '')->count();
                $same = $names->filter(fn ($n, $id) => $n !== null && $n !== '' && isset($en[$id]) && $n === $en[$id]);
                if ($missing === 0 && $same->isEmpty()) {
                    continue;
                }
                $rows[] = [$table, $locale, $total, $missing, $same->count()];
                if ($examples > 0 && $same->isNotEmpty()) {
                    $this->line("  {$table} {$locale}: ".$same->take($examples)->implode(', '));
                }
            }
        }

        $this->table(['Table', 'Locale', 'Rows', 'Missing', 'Same as en'], $rows);

        return self::SUCCESS;
    }
}

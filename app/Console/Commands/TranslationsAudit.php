<?php

namespace App\Console\Commands;

use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        foreach ($this->tables() as [$table, $parent, $fk]) {
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

    /** @return list<array{0: string, 1: string, 2: string}> [translation table, parent table, foreign key] */
    private function tables(): array
    {
        $only = $this->option('table');
        $result = [];
        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if (! str_ends_with($table, '_translation') || ($only && ! in_array($table, $only, true))) {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            $parent = substr($table, 0, -strlen('_translation'));
            $fk = collect([$parent.'_id', rtrim($parent, 's').'_id'])->first(fn ($c) => in_array($c, $columns, true));
            if (! in_array('name', $columns, true) || ! in_array('locale', $columns, true) || ! $fk || ! Schema::hasTable($parent)) {
                continue;
            }
            $result[] = [$table, $parent, $fk];
        }
        sort($result);

        return $result;
    }
}

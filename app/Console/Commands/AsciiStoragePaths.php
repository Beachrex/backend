<?php

namespace App\Console\Commands;

use App\Support\AsciiPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * TASK-002: find and (in later steps) migrate non-ASCII storage paths.
 *
 * --inventory is read-only: it lists non-ASCII keys in the Spaces bucket, non-ASCII values in
 * path-like DB columns and vtour text files that reference non-ASCII names, and writes an
 * old → new CSV with collision detection. Nothing is copied, written or deleted.
 */
class AsciiStoragePaths extends Command
{
    protected $signature = 'storage:ascii-paths
                            {--inventory : Read-only scan of Spaces keys and DB path columns}
                            {--disk=spaces : Storage disk that holds the images}
                            {--prefix=public/img : Key prefix to scan}
                            {--output= : CSV path (default: storage/app/private/ascii-paths/inventory-<env>-<time>.csv)}';

    protected $description = 'TASK-002: inventory of non-ASCII storage paths (Spaces keys + DB) with an old → new mapping';

    /** Column names that hold a path, folder, file name or URL. */
    private const COLUMN_PATTERN = '/(path|url|folder|image|icon|file_name|photo|logo|thumbnail|avatar)/i';

    /** Text files inside a 360° tour that can reference other files by name. */
    private const VTOUR_TEXT_EXTENSIONS = ['xml', 'html', 'htm', 'js', 'json', 'txt'];

    private const CSV_HEADER = ['source', 'location', 'old', 'new', 'form', 'issue', 'detail'];

    public function handle(): int
    {
        if (! $this->option('inventory')) {
            $this->error('Choose a step. Only --inventory is available so far.');

            return self::INVALID;
        }

        return $this->inventory();
    }

    private function inventory(): int
    {
        $disk = Storage::disk($this->option('disk'));
        $prefix = trim((string) $this->option('prefix'), '/');

        $this->info("Listing keys under '{$prefix}/' on disk '{$this->option('disk')}' …");
        $allKeys = $disk->allFiles($prefix);
        $existing = array_fill_keys($allKeys, true);

        $rows = [];
        $asciiButUnsafe = 0;

        // 1. Spaces keys
        $targets = [];
        foreach ($allKeys as $key) {
            if (AsciiPath::isAscii($key)) {
                if (AsciiPath::fromPath($key) !== $key) {
                    $asciiButUnsafe++;
                }
                continue;
            }
            $new = AsciiPath::fromPath($key);
            $targets[$new][] = $key;
            $rows[] = ['spaces', $key, $key, $new, AsciiPath::unicodeForm($key), '', ''];
        }

        // Collisions: two old keys → one new key, or the new key already exists in the bucket.
        $collisions = 0;
        foreach ($rows as $i => $row) {
            $others = array_values(array_diff($targets[$row[3]], [$row[2]]));
            if ($others !== []) {
                $rows[$i][5] = 'collision';
                $rows[$i][6] = 'same new key as: '.implode(' | ', $others);
                $collisions++;
            } elseif (isset($existing[$row[3]])) {
                $rows[$i][5] = 'collision';
                $rows[$i][6] = 'new key already exists in the bucket';
                $collisions++;
            }
        }
        $spacesCount = count($rows);

        // 2. vtour text files that reference non-ASCII names (their own key may be ASCII)
        $vtourFiles = 0;
        foreach ($allKeys as $key) {
            $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
            if (! str_contains($key, '/vtour/') || ! in_array($ext, self::VTOUR_TEXT_EXTENSIONS, true)) {
                continue;
            }
            $content = (string) $disk->get($key);
            if (AsciiPath::isAscii($content)) {
                continue;
            }
            preg_match_all('/[^\s"\'<>=]*[^\x00-\x7F][^\s"\'<>=]*/u', $content, $m);
            $names = array_values(array_unique($m[0] ?? []));
            $rows[] = ['vtour_ref', $key, '', '', '', 'rewrite_refs',
                count($names).' non-ASCII token(s): '.implode(' | ', array_slice($names, 0, 10))];
            $vtourFiles++;
        }

        // 3. DB path columns
        $dbCount = 0;
        foreach ($this->pathColumns() as [$table, $column, $isJson, $keyColumn]) {
            $query = DB::table($table)->whereNotNull($column)->select([$keyColumn, $column])->orderBy($keyColumn);
            foreach ($query->cursor() as $record) {
                $value = (string) $record->{$column};
                $location = "{$table}.{$column}#{$record->{$keyColumn}}";
                $strings = $isJson ? $this->jsonStrings(json_decode($value, true)) : ['' => $value];
                foreach ($strings as $pointer => $string) {
                    if (! is_string($string) || AsciiPath::isAscii($string)) {
                        continue;
                    }
                    $rows[] = ['db', $location.($pointer === '' ? '' : ':'.$pointer), $string,
                        AsciiPath::fromPath($string), AsciiPath::unicodeForm($string), '', ''];
                    $dbCount++;
                }
            }
        }

        $output = $this->option('output')
            ?: storage_path('app/private/ascii-paths/inventory-'.app()->environment().'-'.now()->format('Ymd-His').'.csv');
        $this->writeCsv($output, $rows);

        $this->newLine();
        $this->table(['What', 'Count'], [
            ['Keys scanned under '.$prefix.'/', count($allKeys)],
            ['Non-ASCII Spaces keys', $spacesCount],
            ['  of which NFD', count(array_filter($rows, fn ($r) => $r[0] === 'spaces' && $r[4] === 'NFD'))],
            ['  of which NFC', count(array_filter($rows, fn ($r) => $r[0] === 'spaces' && $r[4] === 'NFC'))],
            ['  of which mixed', count(array_filter($rows, fn ($r) => $r[0] === 'spaces' && $r[4] === 'mixed'))],
            ['Collisions (must be resolved first)', $collisions],
            ['vtour text files with non-ASCII references', $vtourFiles],
            ['Non-ASCII DB values', $dbCount],
            ['ASCII keys with spaces/other chars (info, not migrated)', $asciiButUnsafe],
        ]);

        foreach ($this->dbSummary($rows) as $column => $count) {
            $this->line("  DB {$column}: {$count}");
        }
        foreach (array_filter($rows, fn ($r) => $r[5] === 'collision') as $row) {
            $this->warn("COLLISION {$row[2]} → {$row[3]} ({$row[6]})");
        }

        $this->newLine();
        $this->info("CSV written to {$output}");
        $this->line('Read-only: nothing was copied, changed or deleted.');

        return self::SUCCESS;
    }

    /**
     * Text/JSON columns whose name looks like a path, in every table of the current schema.
     *
     * @return list<array{0: string, 1: string, 2: bool, 3: string}> [table, column, isJson, keyColumn]
     */
    private function pathColumns(): array
    {
        $columns = [];
        foreach (Schema::getTables(Schema::getCurrentSchemaName()) as $table) {
            $name = $table['name'];
            $tableColumns = Schema::getColumns($name);
            $names = array_column($tableColumns, 'name');
            $keyColumn = in_array('id', $names, true) ? 'id' : $names[0];
            foreach ($tableColumns as $column) {
                $type = strtolower($column['type_name']);
                $isText = in_array($type, ['varchar', 'text', 'bpchar', 'char', 'string'], true);
                $isJson = in_array($type, ['json', 'jsonb'], true);
                if (($isText || $isJson) && preg_match(self::COLUMN_PATTERN, $column['name'])) {
                    $columns[] = [$name, $column['name'], $isJson, $keyColumn];
                }
            }
        }

        return $columns;
    }

    /** @return array<string, mixed> JSON pointer → leaf value */
    private function jsonStrings(mixed $data, string $pointer = '$'): array
    {
        if (! is_array($data)) {
            return [$pointer => $data];
        }
        $out = [];
        foreach ($data as $key => $value) {
            $out += $this->jsonStrings($value, $pointer.'.'.$key);
        }

        return $out;
    }

    /** @return array<string, int> "table.column" → count */
    private function dbSummary(array $rows): array
    {
        $summary = [];
        foreach ($rows as $row) {
            if ($row[0] === 'db') {
                $column = strtok($row[1], '#');
                $summary[$column] = ($summary[$column] ?? 0) + 1;
            }
        }
        ksort($summary);

        return $summary;
    }

    private function writeCsv(string $path, array $rows): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $handle = fopen($path, 'w');
        fputcsv($handle, self::CSV_HEADER);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }
}

<?php

namespace App\Console\Commands;

use App\Support\AsciiPath;
use Aws\CommandPool;
use Aws\ResultInterface;
use Illuminate\Console\Command;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

/**
 * TASK-002: find and migrate non-ASCII storage paths. Runbook: docs/runbooks/ascii-storage-paths.md
 *
 * Order (the bucket is shared by dev, uat and prod):
 *   --inventory   read-only scan of Spaces keys, DB path columns and vtour references → CSV
 *   --copy        copy every non-ASCII key to its ASCII key (old keys stay) and rewrite vtour references
 *   --update-db   rewrite non-ASCII DB paths in the current environment's DB (run once per env)
 *   --delete-old  delete the old non-ASCII keys (needs --confirm-delete; only after every env DB is updated)
 * --copy, --update-db and --delete-old accept --dry-run.
 */
class AsciiStoragePaths extends Command
{
    protected $signature = 'storage:ascii-paths
                            {--inventory : Read-only scan of Spaces keys and DB path columns}
                            {--copy : Copy non-ASCII keys to ASCII keys and rewrite vtour references}
                            {--update-db : Rewrite non-ASCII paths in this environment\'s DB}
                            {--delete-old : Delete old non-ASCII keys that were copied}
                            {--dry-run : Show what --copy, --update-db or --delete-old would do, change nothing}
                            {--confirm-delete : Required by --delete-old (owner-confirmed step)}
                            {--disk=spaces : Storage disk that holds the images}
                            {--prefix=public/img : Key prefix to scan}
                            {--progress=10000 : Print a progress line every N listed keys (0 = off)}
                            {--concurrency=32 : Parallel GETs when reading vtour text files from S3}
                            {--output= : Inventory CSV path (default: storage/app/private/ascii-paths/inventory-<env>-<time>.csv)}';

    protected $description = 'TASK-002: inventory and migration of non-ASCII storage paths (Spaces keys + DB)';

    /** @var array<string, string> key => ETag from the last listing (S3 only) */
    private array $etags = [];

    /** Column names that hold a path, folder, file name or URL. */
    private const COLUMN_PATTERN = '/(path|url|folder|image|icon|file_name|photo|logo|thumbnail|avatar)/i';

    /** Text files inside a 360° tour that can reference other files by name. */
    private const VTOUR_TEXT_EXTENSIONS = ['xml', 'html', 'htm', 'js', 'json', 'txt'];

    private const CSV_HEADER = ['source', 'location', 'old', 'new', 'form', 'issue', 'detail'];

    private const STEPS = ['inventory', 'copy', 'update-db', 'delete-old'];

    public function handle(): int
    {
        $steps = array_values(array_filter(self::STEPS, fn ($step) => $this->option($step)));
        if (count($steps) !== 1) {
            $this->error('Choose exactly one step: --'.implode(', --', self::STEPS).'.');

            return self::INVALID;
        }

        return match ($steps[0]) {
            'inventory' => $this->inventory(),
            'copy' => $this->copy(),
            'update-db' => $this->updateDb(),
            'delete-old' => $this->deleteOld(),
        };
    }

    // ------------------------------------------------------------------ steps

    private function inventory(): int
    {
        $disk = $this->disk();
        [$sizes, $plan] = $this->keyPlan($disk);
        $rows = [];
        $asciiButUnsafe = 0;

        foreach (array_keys($sizes) as $key) {
            if (AsciiPath::isAscii($key) && AsciiPath::fromPath($key) !== $key) {
                $asciiButUnsafe++;
            }
        }
        foreach ($plan as $item) {
            $rows[] = ['spaces', $item['old'], $item['old'], $item['new'], AsciiPath::unicodeForm($item['old']),
                $item['status'], $item['detail']];
        }

        $vtour = $this->vtourRewrites($disk, $sizes, $plan);
        foreach ($vtour as $file) {
            $rows[] = ['vtour_ref', $file['key'], '', $file['target'], '', 'rewrite_refs',
                $file['tokenCount'].' non-ASCII token(s): '.implode(' | ', $file['tokens'])];
        }

        $db = $this->dbChanges();
        foreach ($db as $change) {
            $rows[] = ['db', $change['location'], $change['old'], $change['new'], AsciiPath::unicodeForm($change['old']),
                $change['skip'] ? 'skipped_url' : '', ''];
        }

        $output = $this->option('output')
            ?: storage_path('app/private/ascii-paths/inventory-'.app()->environment().'-'.now()->format('Ymd-His').'.csv');
        $this->writeCsv($output, $rows);

        $count = fn (callable $filter) => count(array_filter($plan, $filter));
        $this->newLine();
        $this->table(['What', 'Count'], [
            ['Keys scanned under '.$this->prefix().'/', count($sizes)],
            ['Non-ASCII Spaces keys', count($plan)],
            ['  of which NFD', $count(fn ($i) => AsciiPath::unicodeForm($i['old']) === 'NFD')],
            ['  of which NFC', $count(fn ($i) => AsciiPath::unicodeForm($i['old']) === 'NFC')],
            ['  of which mixed', $count(fn ($i) => AsciiPath::unicodeForm($i['old']) === 'mixed')],
            ['  already copied', $count(fn ($i) => $i['status'] === 'copied')],
            ['Collisions (must be resolved first)', $count(fn ($i) => $i['status'] === 'collision')],
            ['vtour text files with non-ASCII references', count($vtour)],
            ['  of which reference a migrated key (rewritten by --copy)', count(array_filter($vtour, fn ($f) => $f['changed']))],
            ['Non-ASCII DB values', count($db)],
            ['  of which skipped (full URLs, fix by hand)', count(array_filter($db, fn ($c) => $c['skip']))],
            ['ASCII keys with spaces/other chars (info, not migrated)', $asciiButUnsafe],
        ]);

        $perColumn = [];
        foreach ($db as $change) {
            $column = strtok($change['location'], '#');
            $perColumn[$column] = ($perColumn[$column] ?? 0) + 1;
        }
        ksort($perColumn);
        foreach ($perColumn as $column => $n) {
            $this->line("  DB {$column}: {$n}");
        }
        $this->reportCollisions($plan);

        $this->newLine();
        $this->info("CSV written to {$output}");
        $this->line('Read-only: nothing was copied, changed or deleted.');

        return self::SUCCESS;
    }

    private function copy(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = $this->disk();
        [$sizes, $plan] = $this->keyPlan($disk);

        if ($this->reportCollisions($plan) > 0) {
            $this->error('Collisions found. Resolve them before --copy. Nothing was copied.');

            return self::FAILURE;
        }

        $copied = 0;
        foreach ($plan as $item) {
            if ($item['status'] === 'copied') {
                continue;
            }
            $this->line(($dryRun ? '[dry-run] copy ' : 'copy ')."{$item['old']} → {$item['new']}");
            if (! $dryRun) {
                $disk->getDriver()->copy($item['old'], $item['new'], ['visibility' => 'public']);
            }
            $copied++;
        }

        // Rewrite references after the copy, so a rewritten tour only points at keys that exist.
        $rewritten = 0;
        $segments = $this->segmentMap($plan);
        foreach ($this->vtourRewrites($disk, $sizes, $plan) as $file) {
            if (! $file['changed']) {
                continue;
            }
            $this->line(($dryRun ? '[dry-run] rewrite ' : 'rewrite ')."{$file['target']} ({$file['tokenCount']} reference(s))");
            if (! $dryRun) {
                $content = (string) $disk->get($file['key']);
                if ($file['target'] === $file['key']) {
                    $this->backup($file['key'], $content);
                }
                $disk->put($file['target'], strtr($content, $segments), ['visibility' => 'public']);
            }
            $rewritten++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] would copy ' : 'Copied ')."{$copied} key(s) and ".($dryRun ? 'rewrite ' : 'rewrote ')."{$rewritten} vtour file(s). Old keys were kept.");

        return self::SUCCESS;
    }

    private function updateDb(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        [, $plan] = $this->keyPlan($this->disk());

        $pending = array_filter($plan, fn ($i) => $i['status'] !== 'copied');
        if ($pending !== []) {
            $this->error(count($pending).' non-ASCII key(s) are not copied yet. Run --copy first, so the new DB paths resolve. Nothing was changed.');

            return self::FAILURE;
        }

        $changes = $this->dbChanges();
        $updated = 0;
        $skipped = 0;
        DB::transaction(function () use ($changes, $dryRun, &$updated, &$skipped) {
            foreach ($this->groupByRow($changes) as $row) {
                if ($row['skip']) {
                    $skipped++;
                    $this->warn("skip {$row['location']}: full URL, fix by hand ({$row['old']})");

                    continue;
                }
                $this->line(($dryRun ? '[dry-run] ' : '')."{$row['location']}: {$row['old']} → {$row['new']}");
                if (! $dryRun) {
                    DB::table($row['table'])->where($row['keyColumn'], $row['id'])->update([$row['column'] => $row['value']]);
                }
                $updated++;
            }
        });

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] would update ' : 'Updated ')."{$updated} DB value(s) in '".app()->environment()."'; skipped {$skipped}.");
        if (! $dryRun && $updated > 0) {
            $this->line('Next: php artisan cache:clear && php artisan search:index (see the runbook).');
        }

        return self::SUCCESS;
    }

    private function deleteOld(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('confirm-delete')) {
            $this->error('--delete-old deletes keys for every environment. Add --confirm-delete once every env DB is updated and verified.');

            return self::INVALID;
        }

        $disk = $this->disk();
        [, $plan] = $this->keyPlan($disk);

        if ($this->reportCollisions($plan) > 0) {
            $this->error('Collisions found. Nothing was deleted.');

            return self::FAILURE;
        }
        $notCopied = array_filter($plan, fn ($i) => $i['status'] !== 'copied');
        if ($notCopied !== []) {
            $this->error(count($notCopied).' old key(s) have no copy yet. Run --copy first. Nothing was deleted.');

            return self::FAILURE;
        }
        $remaining = array_filter($this->dbChanges(), fn ($c) => ! $c['skip']);
        if ($remaining !== []) {
            $this->error(count($remaining).' non-ASCII DB value(s) remain in \''.app()->environment().'\'. Run --update-db first. Nothing was deleted.');

            return self::FAILURE;
        }

        foreach ($plan as $item) {
            $this->line(($dryRun ? '[dry-run] delete ' : 'delete ').$item['old']);
            if (! $dryRun) {
                $disk->delete($item['old']);
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] would delete ' : 'Deleted ').count($plan).' old key(s).');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- helpers

    private function disk(): FilesystemAdapter
    {
        return Storage::disk($this->option('disk'));
    }

    private function prefix(): string
    {
        return trim((string) $this->option('prefix'), '/');
    }

    /**
     * Every non-ASCII key under the prefix with its ASCII target and a status:
     * '' (to copy), 'copied' (the target holds the same file, or is a rewritten vtour text file)
     * or 'collision' (two old keys share a target, or a different file already sits at the target).
     *
     * @return array{0: array<string, int>, 1: list<array{old: string, new: string, status: string, detail: string}>}
     */
    private function keyPlan(FilesystemAdapter $disk): array
    {
        $this->info("Listing keys under '{$this->prefix()}/' on disk '{$this->option('disk')}' …");
        $sizes = [];
        $this->etags = [];
        $every = max(0, (int) $this->option('progress'));
        $started = microtime(true);
        foreach ($disk->getDriver()->listContents($this->prefix(), true) as $item) {
            if ($item instanceof FileAttributes) {
                $sizes[$item->path()] = (int) $item->fileSize();
                // S3 listings carry the ETag; keeping it saves one HEAD request per key in sameFile().
                $etag = $item->extraMetadata()['ETag'] ?? null;
                if (is_string($etag) && $etag !== '') {
                    $this->etags[$item->path()] = trim($etag, '"');
                }

                // The bucket holds hundreds of thousands of tour tiles; show that the scan is alive.
                if ($every > 0 && count($sizes) % $every === 0) {
                    $this->line(sprintf('  listed %d keys (%ds, %d MB) … last: %s',
                        count($sizes), (int) (microtime(true) - $started),
                        intdiv(memory_get_usage(true), 1024 * 1024), $item->path()));
                }
            }
        }
        ksort($sizes);
        $this->line(sprintf('  listing done: %d keys in %ds', count($sizes), (int) (microtime(true) - $started)));

        $targets = [];
        foreach (array_keys($sizes) as $key) {
            if (! AsciiPath::isAscii($key)) {
                $targets[AsciiPath::fromPath($key)][] = $key;
            }
        }

        $plan = [];
        foreach ($targets as $new => $olds) {
            foreach ($olds as $old) {
                $status = '';
                $detail = '';
                if (count($olds) > 1) {
                    $status = 'collision';
                    $detail = 'same new key as: '.implode(' | ', array_diff($olds, [$old]));
                } elseif (isset($sizes[$new])) {
                    if ($this->isVtourText($old) || $this->sameFile($disk, $sizes, $old, $new)) {
                        $status = 'copied';
                    } else {
                        $status = 'collision';
                        $detail = 'a different file already exists at the new key';
                    }
                }
                $plan[] = ['old' => $old, 'new' => $new, 'status' => $status, 'detail' => $detail];
            }
        }

        return [$sizes, $plan];
    }

    /**
     * Same size and same checksum (ETag on S3). The ETags come from the listing when available
     * (on dev, ~10 700 HEAD requests one by one took 5–15 minutes); otherwise they are fetched.
     */
    private function sameFile(FilesystemAdapter $disk, array $sizes, string $a, string $b): bool
    {
        if ($sizes[$a] !== $sizes[$b]) {
            return false;
        }

        return ($this->etags[$a] ?? $disk->checksum($a)) === ($this->etags[$b] ?? $disk->checksum($b));
    }

    private function isVtourText(string $key): bool
    {
        return str_contains($key, '/vtour/')
            && in_array(strtolower(pathinfo($key, PATHINFO_EXTENSION)), self::VTOUR_TEXT_EXTENSIONS, true);
    }

    /**
     * vtour text files that reference non-ASCII names. References are rewritten only where they match a
     * non-ASCII key segment (folder or file name), so other text such as titles stays unchanged.
     * File contents are not kept (51 000+ tour files ran out of memory on dev); --copy re-reads the files to rewrite.
     *
     * @return list<array{key: string, target: string, tokens: list<string>, tokenCount: int, changed: bool}>
     */
    private function vtourRewrites(FilesystemAdapter $disk, array $sizes, array $plan): array
    {
        $segments = $this->segmentMap($plan);
        $keys = array_values(array_filter(array_keys($sizes), fn ($key) => $this->isVtourText($key)));

        $files = [];
        // A file at a non-ASCII key is read from there and written to its ASCII copy (target);
        // a file at an ASCII key is rewritten in place (target === key).
        foreach ($this->readTexts($disk, $keys) as $key => $content) {
            if (AsciiPath::isAscii($content)) {
                continue;
            }
            preg_match_all('/[^\s"\'<>=]*[^\x00-\x7F][^\s"\'<>=]*/u', $content, $m);
            $tokens = array_values(array_unique($m[0] ?? []));
            $files[] = [
                'key' => $key,
                'target' => AsciiPath::fromPath($key),
                'tokens' => array_slice($tokens, 0, 10),
                'tokenCount' => count($tokens),
                'changed' => $segments !== [] && strtr($content, $segments) !== $content,
            ];
        }

        return $files;
    }

    /**
     * Every non-ASCII key segment (NFC and NFD form) → its ASCII form.
     *
     * @return array<string, string>
     */
    private function segmentMap(array $plan): array
    {
        $segments = [];
        foreach ($plan as $item) {
            foreach (explode('/', $item['old']) as $segment) {
                if (! AsciiPath::isAscii($segment)) {
                    $ascii = AsciiPath::fromPath($segment);
                    $segments[\Normalizer::normalize($segment, \Normalizer::FORM_C)] = $ascii;
                    $segments[\Normalizer::normalize($segment, \Normalizer::FORM_D)] = $ascii;
                }
            }
        }

        return $segments;
    }

    /**
     * Contents of the given keys, keyed by key, in chunks so memory stays flat.
     * On S3 the GETs run in parallel (one GET at a time took over an hour for the ~tens of thousands
     * of vtour text files); other disks (local, tests) read sequentially.
     *
     * @param  list<string>  $keys
     * @return \Generator<string, string>
     */
    private function readTexts(FilesystemAdapter $disk, array $keys): \Generator
    {
        $total = count($keys);
        $this->info("Reading {$total} vtour text files …");
        $started = microtime(true);
        $done = 0;

        foreach (array_chunk($keys, 100) as $chunk) {
            if ($disk instanceof AwsS3V3Adapter) {
                yield from $this->readTextsFromS3($disk, $chunk);
            } else {
                foreach ($chunk as $key) {
                    yield $key => (string) $disk->get($key);
                }
            }

            $done += count($chunk);
            if ((int) $this->option('progress') > 0) {
                $this->line(sprintf('  read %d/%d vtour text files (%ds)', $done, $total, (int) (microtime(true) - $started)));
            }
        }
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function readTextsFromS3(AwsS3V3Adapter $disk, array $keys): array
    {
        $client = $disk->getClient();
        $bucket = $disk->getConfig()['bucket'];
        $commands = array_map(
            fn ($key) => $client->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $disk->path($key)]),
            $keys,
        );
        $results = CommandPool::batch($client, $commands, ['concurrency' => max(1, (int) $this->option('concurrency'))]);

        $contents = [];
        foreach ($keys as $i => $key) {
            $result = $results[$i];
            if (! $result instanceof ResultInterface) {
                throw new \RuntimeException("Could not read {$key}: ".($result instanceof \Throwable ? $result->getMessage() : 'unknown error'));
            }
            $contents[$key] = (string) $result['Body'];
            unset($results[$i]);
        }

        return $contents;
    }

    /**
     * Non-ASCII values in path-like DB columns. Values that contain "://" are full URLs; AsciiPath would
     * break them, so they are reported with skip=true and left for a manual fix.
     *
     * @return list<array{location: string, table: string, column: string, keyColumn: string, id: mixed, isJson: bool, raw: string, pointer: string, old: string, new: string, skip: bool}>
     */
    private function dbChanges(): array
    {
        $changes = [];
        foreach ($this->pathColumns() as [$table, $column, $isJson, $keyColumn]) {
            $query = DB::table($table)->whereNotNull($column)->select([$keyColumn, $column])->orderBy($keyColumn);
            foreach ($query->cursor() as $record) {
                $raw = (string) $record->{$column};
                $location = "{$table}.{$column}#{$record->{$keyColumn}}";
                $strings = $isJson ? $this->jsonStrings(json_decode($raw, true)) : ['' => $raw];
                foreach ($strings as $pointer => $string) {
                    if (! is_string($string) || AsciiPath::isAscii($string)) {
                        continue;
                    }
                    $changes[] = [
                        'location' => $location.($pointer === '' ? '' : ':'.$pointer),
                        'table' => $table, 'column' => $column, 'keyColumn' => $keyColumn,
                        'id' => $record->{$keyColumn}, 'isJson' => $isJson, 'raw' => $raw, 'pointer' => (string) $pointer,
                        'old' => $string, 'new' => AsciiPath::fromPath($string), 'skip' => str_contains($string, '://'),
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Folds the per-value changes into one update per DB row and column (a JSON column can hold several values).
     *
     * @return list<array{location: string, table: string, column: string, keyColumn: string, id: mixed, old: string, new: string, value: string, skip: bool}>
     */
    private function groupByRow(array $changes): array
    {
        $rows = [];
        foreach ($changes as $change) {
            $id = "{$change['table']}.{$change['column']}#{$change['id']}";
            if ($change['skip']) {
                $rows[] = $change + ['value' => $change['raw']];

                continue;
            }
            if (! $change['isJson']) {
                $rows[$id] = $change + ['value' => $change['new']];

                continue;
            }
            $data = isset($rows[$id]) ? json_decode($rows[$id]['value'], true) : json_decode($change['raw'], true);
            data_set($data, substr($change['pointer'], 2), $change['new']);
            $rows[$id] = ['location' => $id, 'old' => $change['raw'], 'value' => json_encode($data), 'skip' => false] + $change;
            $rows[$id]['new'] = $rows[$id]['value'];
        }

        return array_values($rows);
    }

    private function reportCollisions(array $plan): int
    {
        $collisions = array_filter($plan, fn ($i) => $i['status'] === 'collision');
        foreach ($collisions as $item) {
            $this->warn("COLLISION {$item['old']} → {$item['new']} ({$item['detail']})");
        }

        return count($collisions);
    }

    /** Keeps the original of a tour file that is overwritten in place (local disk, not public). */
    private function backup(string $key, string $content): void
    {
        $path = storage_path('app/private/ascii-paths/vtour-backup/'.now()->format('Ymd-His').'/'.$key);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);
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

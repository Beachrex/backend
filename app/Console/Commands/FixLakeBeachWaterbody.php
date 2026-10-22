<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Models\Lake;
use App\Models\LakeTranslation;
use App\Models\Waterbody;
use App\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-off data fix for beaches imported from the old database.
 *
 * In the old database every beach had waterbody_id = 3 (sea). Lake beaches were only marked by
 * location_id = 3 ('l_lake') and lake_name, and `beaches:migrate-from-old-db` copied the sea waterbody.
 * This command sets the lake waterbody on those beaches and makes sure each one is linked to its lake.
 *
 * Dry run by default; writes only with --apply. Safe to run more than once.
 */
class FixLakeBeachWaterbody extends Command
{
    protected $signature = 'beaches:fix-lake-waterbody
                            {--apply : Write the changes (default: dry run, nothing is written)}
                            {--file= : JSON list of lake beaches (default: database/legacy/lake-beaches.json)}';

    protected $description = 'Set the lake waterbody (and lake link) on lake beaches imported from the old database as sea';

    public function handle(CacheService $cacheService): int
    {
        $apply = (bool) $this->option('apply');
        $file = $this->option('file') ?: database_path('legacy/lake-beaches.json');

        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $entries = json_decode((string) file_get_contents($file), true)['beaches'] ?? null;
        if (! is_array($entries) || $entries === []) {
            $this->error("No beaches in {$file}");

            return self::FAILURE;
        }

        $lakeWaterbody = Waterbody::where('code', 'lake')->first();
        if (! $lakeWaterbody) {
            $this->error("Waterbody with code 'lake' does not exist.");

            return self::FAILURE;
        }

        $this->info($apply ? 'APPLY - changes will be written' : 'DRY RUN - nothing will be written (use --apply)');
        $this->newLine();

        $stats = ['beaches' => count($entries), 'missing' => 0, 'waterbody' => 0, 'lake_linked' => 0, 'lake_created' => 0, 'lake_mismatch' => 0, 'unchanged' => 0];
        $rows = [];
        $oldWaterbodyIds = [];
        $lakeCache = [];

        DB::transaction(function () use ($entries, $lakeWaterbody, $apply, &$stats, &$rows, &$oldWaterbodyIds, &$lakeCache) {
            foreach ($entries as $entry) {
                $beachId = (int) $entry['beach_id'];
                $lakeName = trim((string) $entry['lake_name']);
                $lakeSlug = $entry['lake_slug'] ?? null;

                $beach = Beach::find($beachId);
                if (! $beach) {
                    $stats['missing']++;
                    $rows[] = [$beachId, $entry['name'] ?? '', '-', '-', 'beach not found - skipped'];

                    continue;
                }

                $notes = [];
                $before = $beach->waterbody_id;

                if ((int) $beach->waterbody_id !== $lakeWaterbody->id) {
                    $oldWaterbodyIds[] = $beach->waterbody_id;
                    $beach->waterbody_id = $lakeWaterbody->id;
                    $stats['waterbody']++;
                    $notes[] = 'waterbody -> lake';
                }

                if ($beach->lake_id === null) {
                    $key = Str::lower($lakeName);
                    $lake = $lakeCache[$key] ??= $this->findLake($lakeName, $lakeSlug);

                    if (! $lake) {
                        $stats['lake_created']++;
                        $notes[] = "create lake '{$lakeName}'";
                        if ($apply) {
                            $lake = $lakeCache[$key] = $this->createLake($lakeName, $lakeSlug);
                        }
                    }

                    $stats['lake_linked']++;
                    $notes[] = "link lake '{$lakeName}'";
                    if ($lake) {
                        $beach->lake_id = $lake->id;
                    }
                } else {
                    $currentName = LakeTranslation::where('lake_id', $beach->lake_id)->where('locale', 'en')->value('name');
                    if ($currentName !== null && Str::lower($currentName) !== Str::lower($lakeName)) {
                        $stats['lake_mismatch']++;
                        $notes[] = "lake is '{$currentName}', old DB says '{$lakeName}' (not changed)";
                    }
                }

                if ($beach->isDirty()) {
                    if ($apply) {
                        $beach->save();
                    }
                } else {
                    $stats['unchanged']++;
                }

                $rows[] = [$beachId, $beach->translations()->where('locale', 'en')->value('name') ?? ($entry['name'] ?? ''), $before ?? 'null', $beach->waterbody_id, $notes ? implode('; ', $notes) : 'ok, nothing to do'];
            }
        });

        $this->table(['Beach ID', 'Name', 'Waterbody before', 'Waterbody after', 'Result'], $rows);

        $this->newLine();
        $this->line("Beaches in list:          {$stats['beaches']}");
        $this->line("Not found:                {$stats['missing']}");
        $this->line("Waterbody set to lake:    {$stats['waterbody']}");
        $this->line("Lake linked:              {$stats['lake_linked']}");
        $this->line("New lakes created:        {$stats['lake_created']}");
        $this->line("Lake name differs:        {$stats['lake_mismatch']}");
        $this->line("Already correct:          {$stats['unchanged']}");
        $this->newLine();

        if (! $apply) {
            $this->info('DRY RUN COMPLETE - run with --apply to write the changes');

            return self::SUCCESS;
        }

        if ($stats['waterbody'] + $stats['lake_linked'] > 0) {
            foreach (array_unique(array_filter($oldWaterbodyIds)) as $oldId) {
                Cache::forget("waterbody:show:{$oldId}");
            }
            $cacheService->clearAll();
            $this->info('API cache cleared.');
        }

        $this->info('COMPLETE - lake beaches updated');

        return self::SUCCESS;
    }

    private function findLake(string $name, ?string $slug): ?Lake
    {
        $lake = Lake::whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', $name))->first();

        if (! $lake && $slug) {
            $lake = Lake::where('code', $slug)->first();
        }

        return $lake;
    }

    private function createLake(string $name, ?string $slug): Lake
    {
        $base = $slug ?: Str::slug($name);
        $code = $base;
        $i = 2;
        while (Lake::where('code', $code)->exists()) {
            $code = "{$base}-{$i}";
            $i++;
        }

        $lake = Lake::create(['code' => $code, 'status_id' => 1]);
        LakeTranslation::create(['lake_id' => $lake->id, 'locale' => 'en', 'name' => $name, 'slug' => $code]);

        return $lake;
    }
}

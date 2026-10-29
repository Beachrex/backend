<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Support\SphereTour;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Records whether each beach with a 360° panorama has its krpano tour in storage (`beach.has_vtour`).
 * Owner report 2026-10-29 (Plage du Port Vieux). Default: only beaches never checked; `--all` re-checks everyone.
 * Read-only on storage; writes only the flag. Scheduled daily (routes/console.php).
 */
class CheckSphereTours extends Command
{
    protected $signature = 'media:check-vtours {--all : Re-check beaches already checked} {--dry-run : Report only, do not store}';

    protected $description = 'Check which beaches with a 360° panorama have a generated tour (sets beach.has_vtour)';

    public function handle(): int
    {
        $collectionId = DB::table('media_collections')->where('code', 'beach_360_sphere')->value('id');
        if (!$collectionId) {
            $this->error('media_collections: beach_360_sphere not found.');

            return self::FAILURE;
        }

        $ids = DB::table('media')
            ->where('media_collection_id', $collectionId)
            ->where('status_id', 1)
            ->where('mediable_type', 'like', '%Beach')
            ->distinct()
            ->pluck('mediable_id');

        $query = Beach::query()->whereIn('id', $ids)->whereNotNull('folder_name')->orderBy('id');
        if (!$this->option('all')) {
            $query->whereNull('has_vtour');
        }

        $dry = (bool) $this->option('dry-run');
        $counts = ['present' => 0, 'missing' => 0, 'unknown' => 0];
        $missing = [];

        $query->select(['id', 'name', 'folder_name', 'mobile_upload', 'has_vtour'])->chunkById(200, function ($beaches) use ($dry, &$counts, &$missing) {
            foreach ($beaches as $beach) {
                $exists = SphereTour::existsInStorage($beach);
                if ($exists === null) {
                    $counts['unknown']++;
                    continue;
                }
                $counts[$exists ? 'present' : 'missing']++;
                if (!$exists) {
                    $missing[] = [$beach->id, $beach->name, SphereTour::path($beach)];
                }
                if (!$dry && $beach->has_vtour !== $exists) {
                    SphereTour::remember($beach, $exists);
                }
            }
        });

        if ($missing !== []) {
            $this->table(['Beach ID', 'Name', 'Expected tour'], $missing);
        }
        $this->info(sprintf(
            '%s: %d with tour, %d without tour, %d could not be checked.',
            $dry ? 'Dry run' : 'Done',
            $counts['present'],
            $counts['missing'],
            $counts['unknown']
        ));

        return self::SUCCESS;
    }
}

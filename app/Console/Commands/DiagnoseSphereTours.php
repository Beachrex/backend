<?php

namespace App\Console\Commands;

use App\Models\Beach;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnoses beaches that have 360° sphere media records but would generate
 * a wrong vtour URL (mobile_upload mismatch), and beaches where the tour.html
 * file actually exists on DigitalOcean Spaces but the beach has no sphere
 * media record in the database.
 *
 * Usage:
 *   php artisan beaches:diagnose-spheres
 */
class DiagnoseSphereTours extends Command
{
    protected $signature = 'beaches:diagnose-spheres';
    protected $description = 'Find beaches with missing/mismatched 360° sphere tour data';

    public function handle(): int
    {
        $this->info('Scanning beaches for sphere tour issues...');

        $sphereCollectionId = \DB::table('media_collections')
            ->where('code', 'beach_360_sphere')->value('id');

        if (!$sphereCollectionId) {
            $this->error('media_collections: beach_360_sphere not found.');
            return self::FAILURE;
        }

        // Beaches that HAVE a sphere media record
        $beachesWithSphere = \DB::table('media')
            ->where('media_collection_id', $sphereCollectionId)
            ->where('status_id', 1)
            ->pluck('mediable_id')
            ->unique();

        $this->info("Beaches with sphere media record: {$beachesWithSphere->count()}");

        // Check each: does the tour.html exist on Spaces at the correct path?
        $cdnBase = config('filesystems.disks.spaces.url')
            ?? rtrim(env('DO_SPACES_CDN_ENDPOINT', 'https://beachrex.fra1.digitaloceanspaces.com'), '/');

        $mismatched = [];
        $missingOnSpaces = [];

        $bar = $this->output->createProgressBar($beachesWithSphere->count());
        $bar->start();

        foreach ($beachesWithSphere as $beachId) {
            $beach = \DB::table('beaches')->where('id', $beachId)->first();
            if (!$beach || !$beach->folder_name) {
                $bar->advance();
                continue;
            }

            $baseFolder = $beach->mobile_upload ? 'beaches_mobile' : 'beaches';
            $tourPath = "img/{$baseFolder}/{$beach->folder_name}/vtour/tour.html";
            $fullUrl = "{$cdnBase}/{$tourPath}";

            try {
                $resp = Http::timeout(5)->head($fullUrl);
                if (!$resp->successful()) {
                    // Try the other folder as fallback
                    $altFolder = $beach->mobile_upload ? 'beaches' : 'beaches_mobile';
                    $altPath = "img/{$altFolder}/{$beach->folder_name}/vtour/tour.html";
                    $altUrl = "{$cdnBase}/{$altPath}";
                    $altResp = Http::timeout(5)->head($altUrl);

                    if ($altResp->successful()) {
                        $mismatched[] = [
                            'id' => $beach->id,
                            'folder' => $beach->folder_name,
                            'mobile_upload' => (bool) $beach->mobile_upload,
                            'correct_path' => $altPath,
                        ];
                    } else {
                        $missingOnSpaces[] = $beach->id;
                    }
                }
            } catch (\Exception $e) {
                $missingOnSpaces[] = $beach->id;
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        if (count($mismatched) > 0) {
            $this->warn("\n=== MOBILE_UPLOAD MISMATCH (tour exists at other path) ===");
            $this->table(['Beach ID', 'Folder', 'mobile_upload', 'Should be'], array_map(fn($m) => [
                $m['id'], $m['folder'], $m['mobile_upload'] ? 'true' : 'false', $m['correct_path'],
            ], $mismatched));
        }

        if (count($missingOnSpaces) > 0) {
            $this->warn("\n=== TOUR.HTML MISSING ON SPACES (media record exists but file not found) ===");
            $this->line(implode(', ', $missingOnSpaces));
        }

        if (count($mismatched) === 0 && count($missingOnSpaces) === 0) {
            $this->info("\nAll beaches with sphere media records have valid tour.html files on Spaces. ✅");
        }

        // Now find beaches WITHOUT a sphere record but where tour.html might exist
        $this->info("\nChecking beaches WITHOUT sphere media records for orphaned tour.html files...");
        $beachesWithoutSphere = \DB::table('beaches')
            ->where('status_id', 1)
            ->whereNotIn('id', $beachesWithSphere->toArray())
            ->whereNotNull('folder_name')
            ->get(['id', 'folder_name', 'mobile_upload']);

        $orphaned = [];
        $bar2 = $this->output->createProgressBar($beachesWithoutSphere->count());
        $bar2->start();

        foreach ($beachesWithoutSphere as $beach) {
            $baseFolder = $beach->mobile_upload ? 'beaches_mobile' : 'beaches';
            $tourPath = "img/{$baseFolder}/{$beach->folder_name}/vtour/tour.html";
            $fullUrl = "{$cdnBase}/{$tourPath}";

            try {
                $resp = Http::timeout(5)->head($fullUrl);
                if ($resp->successful()) {
                    $orphaned[] = [
                        'id' => $beach->id,
                        'folder' => $beach->folder_name,
                        'mobile_upload' => $beach->mobile_upload,
                        'tour_path' => $tourPath,
                    ];
                }
            } catch (\Exception $e) {
                // skip
            }
            $bar2->advance();
        }
        $bar2->finish();
        $this->newLine(2);

        if (count($orphaned) > 0) {
            $this->warn("\n=== ORPHANED TOUR.HTML (file exists on Spaces but no media record in DB) ===");
            $this->table(['Beach ID', 'Folder', 'mobile_upload', 'Tour path'], array_map(fn($o) => [
                $o['id'], $o['folder'], $o['mobile_upload'] ? 'true' : 'false', $o['tour_path'],
            ], $orphaned));

            if ($this->confirm('Would you like to create missing sphere media records for these beaches?')) {
                $this->createMissingMediaRecords($orphaned, $sphereCollectionId);
            }
        } else {
            $this->info("\nNo orphaned tour.html files found. ✅");
        }

        return self::SUCCESS;
    }

    private function createMissingMediaRecords(array $orphaned, int $collectionId): void
    {
        $sphereTypeId = \DB::table('media_types')->where('code', 'sphere_360')->value('id');
        if (!$sphereTypeId) {
            $this->error('media_types: sphere_360 not found.');
            return;
        }

        $created = 0;
        foreach ($orphaned as $beach) {
            $baseFolder = $beach['mobile_upload'] ? 'beaches_mobile' : 'beaches';
            $fileName = $beach['folder'] . '-Panorama.jpg';
            $filePath = "img/{$baseFolder}/{$beach['folder']}/{$fileName}";

            \DB::table('media')->insert([
                'mediable_type' => 'App\\Models\\Beach',
                'mediable_id' => $beach['id'],
                'media_type_id' => $sphereTypeId,
                'media_collection_id' => $collectionId,
                'user_id' => null,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'disk' => 'spaces',
                'mime_type' => 'image/jpeg',
                'size' => 0,
                'width' => null,
                'height' => null,
                'duration' => null,
                'is_primary' => false,
                'order' => 0,
                'metadata' => null,
                'status_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        $this->info("Created {$created} sphere media records.");
        $this->warn('Remember to clear the beach details cache: php artisan cache:clear');
    }
}

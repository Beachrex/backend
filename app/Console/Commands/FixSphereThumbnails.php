<?php

namespace App\Console\Commands;

use App\Models\Beach;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Fixes sphere media records where the panorama image file does not actually
 * exist on DigitalOcean Spaces. Sets file_path to empty string so the frontend
 * falls back to the first gallery image instead of showing a broken image.
 *
 * Usage:
 *   php artisan beaches:fix-sphere-thumbnails
 */
class FixSphereThumbnails extends Command
{
    protected $signature = 'beaches:fix-sphere-thumbnails';
    protected $description = 'Fix sphere media records with missing panorama images on Spaces';

    public function handle(): int
    {
        $sphereCollectionId = \DB::table('media_collections')
            ->where('code', 'beach_360_sphere')->value('id');

        if (!$sphereCollectionId) {
            $this->error('media_collections: beach_360_sphere not found.');
            return self::FAILURE;
        }

        $sphereRecords = \DB::table('media')
            ->where('media_collection_id', $sphereCollectionId)
            ->where('status_id', 1)
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->get(['id', 'mediable_id', 'file_name', 'file_path']);

        $this->info("Checking {$sphereRecords->count()} sphere media records for missing panorama images...");

        $bar = $this->output->createProgressBar($sphereRecords->count());
        $bar->start();

        $fixed = 0;
        foreach ($sphereRecords as $media) {
            $s3Key = $this->toS3Key($media->file_path);

            if (!Storage::disk('spaces')->exists($s3Key)) {
                // Panorama image doesn't exist — clear file_path so the
                // frontend falls back to the first gallery image.
                \DB::table('media')
                    ->where('id', $media->id)
                    ->update(['file_path' => '']);
                $fixed++;
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Fixed {$fixed} sphere media records (panorama image missing → gallery fallback).");
        if ($fixed > 0) {
            $this->warn('Run: php artisan cache:clear');
        }

        return self::SUCCESS;
    }

    private function toS3Key(string $path): string
    {
        return str_starts_with($path, 'public/') ? $path : 'public/' . $path;
    }
}

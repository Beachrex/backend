<?php

namespace App\Console\Commands;

use App\Support\Watermark;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Puts the watermark PNG on the bucket at its versioned path, public, if it is not there yet.
 *
 * Run by every deploy (all environments share the bucket), so it must be safe to repeat:
 * an existing file is never overwritten. A new design gets a new versioned file name instead.
 */
class PublishWatermark extends Command
{
    protected $signature = 'media:publish-watermark
                            {--disk=spaces : Filesystem disk that Imagor reads}';

    protected $description = 'Upload the Beachrex watermark PNG to the image bucket if it is missing (idempotent)';

    public function handle(): int
    {
        $disk = Storage::disk((string) $this->option('disk'));
        $key = Watermark::storageKey();

        if ($disk->exists($key)) {
            $this->info("Watermark already on the bucket: {$key}");

            return self::SUCCESS;
        }

        $source = Watermark::sourcePath();
        if (!is_file($source)) {
            $this->error('Watermark source file is missing: ' . Watermark::SOURCE);

            return self::FAILURE;
        }

        $written = $disk->put($key, (string) file_get_contents($source), [
            'visibility' => 'public',
            'ContentType' => 'image/png',
        ]);

        // The spaces disk does not throw, so check the result explicitly.
        if (!$written || !$disk->exists($key)) {
            $this->error("Could not write the watermark to {$key}");

            return self::FAILURE;
        }

        $this->info("Watermark uploaded: {$key}");

        return self::SUCCESS;
    }
}

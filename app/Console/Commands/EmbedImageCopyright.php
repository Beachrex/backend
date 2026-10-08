<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Support\ImageCopyright;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the copyright IPTC block (MOJA PLAŽA, d.o.o.) into existing JPEG photos on Spaces
 * (owner, 2026-10-08; new uploads get it on upload).
 *
 * Only beach photos under `img/beaches/` (admin uploads); `img/beaches_mobile/` is skipped because
 * those may be photos by other authors. Files that already carry the notice are skipped, so the
 * command can be run again. Each changed file is rewritten in place on the bucket — run with
 * --dry-run first.
 */
class EmbedImageCopyright extends Command
{
    protected $signature = 'media:embed-copyright
                            {--dry-run : Count what would change, write nothing}
                            {--limit=0 : Stop after this many files (0 = all)}';

    protected $description = 'Write the © MOJA PLAŽA, d.o.o. IPTC block into existing JPEG beach photos on Spaces';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $disk = Storage::disk('spaces');
        $stats = ['checked' => 0, 'written' => 0, 'already' => 0, 'missing' => 0, 'skipped' => 0];

        Media::query()
            ->where('disk', 'spaces')
            ->where('file_path', 'like', 'img/beaches/%')
            ->where(fn ($q) => $q->where('mime_type', 'image/jpeg')
                ->orWhere('file_path', 'ilike', '%.jpg')
                ->orWhere('file_path', 'ilike', '%.jpeg'))
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($disk, $dryRun, $limit, &$stats) {
                foreach ($items as $media) {
                    if ($limit && $stats['checked'] >= $limit) {
                        return false;
                    }
                    $stats['checked']++;
                    // Admin uploads are stored under public/ on the bucket (see MediaRelationManager)
                    $path = 'public/' . $media->file_path;
                    if (!$disk->exists($path)) {
                        $stats['missing']++;
                        continue;
                    }
                    $bytes = (string) $disk->get($path);
                    if (ImageCopyright::hasNotice($bytes)) {
                        $stats['already']++;
                        continue;
                    }
                    $withCopyright = ImageCopyright::embed($bytes);
                    if ($withCopyright === null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $stats['written']++;
                    if (!$dryRun) {
                        $disk->put($path, $withCopyright, 'public');
                        $media->forceFill(['size' => strlen($withCopyright)])->saveQuietly();
                    }
                }

                return true;
            });

        $this->info(($dryRun ? '[dry run] ' : '') . sprintf(
            'Checked: %d · %s: %d · already had it: %d · missing on Spaces: %d · not JPEG: %d',
            $stats['checked'],
            $dryRun ? 'would write' : 'written',
            $stats['written'],
            $stats['already'],
            $stats['missing'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}

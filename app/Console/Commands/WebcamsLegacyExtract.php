<?php

namespace App\Console\Commands;

use App\Services\Webcams\LegacyWebcamExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * TASK-006 §6.2: salvage webcam ids from `beach.webcam` and write a CSV report
 * (storage/app/webcams/legacy-extract-<time>.csv). `beach.webcam` itself is never changed.
 */
class WebcamsLegacyExtract extends Command
{
    protected $signature = 'webcams:legacy-extract
                            {--country=* : Only places in these countries}
                            {--dry-run : No API calls, no player checks, no assignments}';

    protected $description = 'Salvage legacy beach.webcam embeds into place_webcams and report each one';

    public function handle(LegacyWebcamExtractor $extractor): int
    {
        $dry = (bool) $this->option('dry-run');
        try {
            $result = $extractor->run($this->option('country'), $dry);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $path = 'webcams/legacy-extract-' . now()->format('Ymd-His') . ($dry ? '-dry-run' : '') . '.csv';
        $lines = [implode(',', array_keys($result['rows'][0] ?? ['place_id' => 1]))];
        foreach ($result['rows'] as $row) {
            $lines[] = implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row));
        }
        Storage::disk('local')->put($path, implode("\n", $lines) . "\n");

        $this->info(($dry ? '[dry-run] ' : '') . 'webcams:legacy-extract');
        $this->table(['outcome', 'places'], collect($result['counts'])->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->line("API requests used: {$result['requests']}");
        $this->line('Report: ' . Storage::disk('local')->path($path));

        return self::SUCCESS;
    }
}

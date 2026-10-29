<?php

namespace App\Console\Commands;

use App\Services\Webcams\WebcamImporter;
use App\Services\Webcams\WindyApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TASK-006 §6.1: copy Windy webcam metadata into `windy_webcams`. Batch only, budgeted, resumable.
 * Default countries: every country that has places in our DB.
 */
class WebcamsImport extends Command
{
    protected $signature = 'webcams:import
                            {--country=* : ISO-2 countries (default: every country with places)}
                            {--fresh : Start a new run instead of resuming an unfinished one}
                            {--dry-run : Print the plan, call nothing, change nothing}';

    protected $description = 'Import Windy webcam metadata (Windy API, budgeted and resumable)';

    public function handle(WebcamImporter $importer, WindyApiClient $api): int
    {
        $countries = array_map('strtoupper', $this->option('country'))
            ?: DB::table('beach')->whereNotNull('country_code')->distinct()->orderBy('country_code')->pluck('country_code')->all();
        $runKey = $importer->runKey($countries, (bool) $this->option('fresh'));
        $slices = DB::table('windy_import_slices')->where('run_key', $runKey)->get();

        $this->info(($this->option('dry-run') ? '[dry-run] ' : '') . "webcams:import run {$runKey}");
        $this->line('Countries: ' . implode(',', $countries));
        $this->line('Categories: ' . implode(',', config('webcams.import.categories')) . ' (or)');
        $this->line('Budget left today: ' . $api->remainingBudget() . ' of ' . config('webcams.api.daily_request_budget'));
        $this->line('API key set: ' . (config('webcams.api.key') ? 'yes' : 'NO'));
        if ($slices->isNotEmpty()) {
            $this->line('Resuming: ' . $slices->count() . ' slices, ' . $slices->whereIn('status', ['done', 'truncated', 'split'])->count() . ' finished');
        }
        if ($this->option('dry-run')) {
            $this->line('Would request, per country: 1 call per 50 webcams; countries above 1 050 webcams are split by region.');

            return self::SUCCESS;
        }

        try {
            $result = $importer->run($runKey, $countries);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['country', 'new', 'updated', 'missing'], collect($importer->stats)->map(fn ($s, $cc) => [$cc, $s['new'], $s['updated'], $s['missing']])->values()->all());
        $this->table(['slice', 'total', 'fetched', 'status'], DB::table('windy_import_slices')->where('run_key', $runKey)->orderBy('id')->get()
            ->map(fn ($s) => [$s->country_code . ($s->region_code ? " {$s->region_code}" : ''), $s->total, $s->fetched, $s->status])->all());
        $this->line("API requests used: {$result['requests']}");
        if (! $result['finished']) {
            $this->warn('Daily budget reached — run the same command again tomorrow to resume.');
        }

        return self::SUCCESS;
    }
}

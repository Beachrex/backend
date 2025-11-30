<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Models\City;
use App\Models\Country;
use App\Models\Island;
use App\Models\Region;
use Illuminate\Console\Command;

class IndexSearchableModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:index {model?} {--flush}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index all searchable models into Meilisearch. Use --flush to clear existing index first.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $model = $this->argument('model');
        $flush = $this->option('flush');

        $models = [
            'Beach' => Beach::class,
            'City' => City::class,
            'Island' => Island::class,
            'Region' => Region::class,
            'Country' => Country::class,
        ];

        // If specific model is provided, only index that model
        if ($model) {
            if (!isset($models[$model])) {
                $this->error("Model '{$model}' not found. Available models: " . implode(', ', array_keys($models)));
                return self::FAILURE;
            }

            $models = [$model => $models[$model]];
        }

        $this->info('Starting search indexing...');
        $this->newLine();

        foreach ($models as $name => $class) {
            $this->info("Indexing {$name}...");

            if ($flush) {
                $this->warn("  Flushing existing {$name} index...");
                $this->call('scout:flush', ['model' => $class]);
            }

            $this->info("  Importing {$name} records...");
            $this->call('scout:import', ['model' => $class]);

            $this->info("  ✓ {$name} indexed successfully");
            $this->newLine();
        }

        $this->info('All models indexed successfully!');
        $this->newLine();

        $this->info('You can now search using the /api/v1/search endpoint.');

        return self::SUCCESS;
    }
}


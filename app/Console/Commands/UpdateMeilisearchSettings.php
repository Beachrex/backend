<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MeiliSearch\Client;

class UpdateMeilisearchSettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:update-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Meilisearch index settings from config/scout.php';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Updating Meilisearch index settings...');
        $this->newLine();

        $client = new Client(
            config('scout.meilisearch.host'),
            config('scout.meilisearch.key')
        );

        $indexSettings = config('scout.meilisearch.index-settings', []);

        if (empty($indexSettings)) {
            $this->warn('No index settings found in config/scout.php');
            return self::FAILURE;
        }

        foreach ($indexSettings as $indexName => $settings) {
            $this->info("Updating settings for index: {$indexName}");

            try {
                $index = $client->index($indexName);

                // Update searchable attributes
                if (isset($settings['searchableAttributes'])) {
                    $this->info("  - Setting searchableAttributes");
                    $index->updateSearchableAttributes($settings['searchableAttributes']);
                }

                // Update filterable attributes
                if (isset($settings['filterableAttributes'])) {
                    $this->info("  - Setting filterableAttributes");
                    $index->updateFilterableAttributes($settings['filterableAttributes']);
                }

                // Update sortable attributes
                if (isset($settings['sortableAttributes'])) {
                    $this->info("  - Setting sortableAttributes");
                    $index->updateSortableAttributes($settings['sortableAttributes']);
                }

                // Update non-separator tokens (for special characters like č, š, ž, etc.)
                if (isset($settings['nonSeparatorTokens'])) {
                    $this->info("  - Setting nonSeparatorTokens (diacritics support)");
                    $index->updateSettings([
                        'nonSeparatorTokens' => $settings['nonSeparatorTokens']
                    ]);
                }

                // maxTotalHits caps the exhaustive `totalHits` the search counts use (default 1000)
                if (isset($settings['pagination'])) {
                    $this->info("  - Setting pagination");
                    $index->updatePagination($settings['pagination']);
                }

                // Note: ignorePlurals is not supported in Meilisearch
                // It was removed from config/scout.php

                $this->info("  ✓ {$indexName} settings updated successfully");
                $this->newLine();

            } catch (\Exception $e) {
                $this->error("  ✗ Failed to update {$indexName}: " . $e->getMessage());
                $this->newLine();
            }
        }

        $this->info('All index settings updated successfully!');
        $this->newLine();

        $this->warn('Note: You may need to re-index your data for changes to take full effect.');
        $this->info('Run: php artisan search:index --flush');

        return self::SUCCESS;
    }
}


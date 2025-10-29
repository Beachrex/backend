<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckPostGIS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postgis:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check if PostGIS extension is installed and working';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking PostGIS installation...');
        $this->newLine();

        try {
            // Check if PostGIS extension is installed
            $result = DB::select("
                SELECT EXISTS(
                    SELECT 1 FROM pg_extension WHERE extname = 'postgis'
                ) as installed
            ");

            if ($result[0]->installed) {
                $this->info('✅ PostGIS extension is installed');

                // Get PostGIS version
                $version = DB::select("SELECT PostGIS_Version() as version");
                $this->info("   Version: {$version[0]->version}");
            } else {
                $this->error('❌ PostGIS extension is NOT installed');
                $this->newLine();
                $this->warn('To install PostGIS, run:');
                $this->line('   CREATE EXTENSION IF NOT EXISTS postgis;');
                return Command::FAILURE;
            }

            $this->newLine();

            // Check if beach table has location column
            $hasLocationColumn = DB::select("
                SELECT EXISTS(
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'beach' AND column_name = 'location'
                ) as exists
            ");

            if ($hasLocationColumn[0]->exists) {
                $this->info('✅ Beach table has location column');

                // Check if spatial index exists
                $hasIndex = DB::select("
                    SELECT EXISTS(
                        SELECT 1 FROM pg_indexes
                        WHERE tablename = 'beach' AND indexname = 'beach_location_gist_idx'
                    ) as exists
                ");

                if ($hasIndex[0]->exists) {
                    $this->info('✅ Spatial index exists on beach.location');
                } else {
                    $this->warn('⚠️  Spatial index is missing on beach.location');
                }

                // Count beaches with location data
                $count = DB::select("
                    SELECT COUNT(*) as count
                    FROM beach
                    WHERE location IS NOT NULL
                ");

                $this->info("   Beaches with location data: {$count[0]->count}");
            } else {
                $this->error('❌ Beach table does NOT have location column');
                $this->newLine();
                $this->warn('Run migrations to add spatial columns:');
                $this->line('   php artisan migrate');
                return Command::FAILURE;
            }

            $this->newLine();
            $this->info('✅ PostGIS is properly configured!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error checking PostGIS: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}


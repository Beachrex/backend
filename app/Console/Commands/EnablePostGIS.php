<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnablePostGIS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postgis:enable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable PostGIS extension in the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Enabling PostGIS extension...');
        $this->newLine();

        try {
            // Check if already installed
            $result = DB::select("
                SELECT EXISTS(
                    SELECT 1 FROM pg_extension WHERE extname = 'postgis'
                ) as installed
            ");

            if ($result[0]->installed) {
                $this->warn('PostGIS extension is already installed');
                
                // Get version
                $version = DB::select("SELECT PostGIS_Version() as version");
                $this->info("Version: {$version[0]->version}");
                
                return Command::SUCCESS;
            }

            // Enable PostGIS extension
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
            $this->info('✅ PostGIS extension enabled successfully');

            // Verify installation
            $version = DB::select("SELECT PostGIS_Version() as version");
            $this->info("   Version: {$version[0]->version}");

            $this->newLine();
            $this->info('Next steps:');
            $this->line('1. Run migrations: php artisan migrate');
            $this->line('2. Check PostGIS: php artisan postgis:check');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to enable PostGIS: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Possible reasons:');
            $this->line('- PostGIS is not installed on the PostgreSQL server');
            $this->line('- Database user lacks CREATE EXTENSION privileges');
            $this->line('- PostgreSQL version is incompatible');
            $this->newLine();
            $this->warn('Contact your hosting provider (Laravel Cloud) to:');
            $this->line('1. Install PostGIS extension on the PostgreSQL server');
            $this->line('2. Grant CREATE EXTENSION privileges to your database user');
            
            return Command::FAILURE;
        }
    }
}


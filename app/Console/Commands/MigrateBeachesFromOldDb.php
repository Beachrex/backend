<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\Island;
use App\Models\IslandTranslation;
use App\Models\River;
use App\Models\RiverTranslation;
use App\Models\Lake;
use App\Models\LakeTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\City;
use App\Models\CityTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateBeachesFromOldDb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beaches:migrate-from-old-db
                            {--test : Test mode - migrate only first beach}
                            {--limit= : Limit number of beaches to migrate}
                            {--resume : Resume from last migrated beach}
                            {--reset : Reset migration status and start from beginning}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate beaches from old database SQL dump';

    /**
     * SQL file path
     */
    private string $sqlFilePath;

    /**
     * Migration status record
     */
    private $migrationStatus;

    /**
     * Statistics
     */
    private int $successCount = 0;
    private int $failedCount = 0;
    private int $skippedCount = 0;

    /**
     * Cache for islands, rivers, lakes, regions, cities to avoid duplicate lookups
     */
    private array $islandCache = [];
    private array $riverCache = [];
    private array $lakeCache = [];
    private array $regionCache = [];
    private array $cityCache = [];

    // Cached data from SQL file (parsed once)
    private array $allBeaches = [];
    private array $pivotData = [];
    private array $galleryBaseData = [];
    private array $galleryData = [];

    public function __construct()
    {
        parent::__construct();
        $this->sqlFilePath = database_path('sql/beachrex_old.sql');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏖️  Beach Migration from Old Database');
        $this->info('=====================================');
        $this->newLine();

        // Check if SQL file exists
        if (!file_exists($this->sqlFilePath)) {
            $this->error("SQL file not found: {$this->sqlFilePath}");
            return 1;
        }

        // Initialize or get migration status
        $this->initializeMigrationStatus();

        // Handle reset option
        if ($this->option('reset')) {
            if ($this->confirm('Are you sure you want to reset migration status and start from beginning?')) {
                $this->resetMigrationStatus();
                $this->info('✅ Migration status reset successfully');
            } else {
                $this->info('Migration cancelled');
                return 0;
            }
        }

        // Parse and migrate beaches
        $testMode = $this->option('test');
        $limit = $this->option('limit');
        $resume = $this->option('resume');

        if ($testMode) {
            $this->warn('🧪 TEST MODE: Migrating only first beach');
        } elseif ($limit) {
            $this->warn("📊 LIMIT MODE: Migrating only {$limit} beaches");
        }

        if ($resume) {
            $this->info("📍 Resuming from beach ID: {$this->migrationStatus->last_migrated_beach_id}");
        }

        $this->newLine();
        $this->info('📖 Parsing SQL file...');

        try {
            $this->parseAndMigrateBeaches($testMode, $resume);
        } catch (\Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            $this->updateMigrationStatus('failed', $e->getMessage());
            return 1;
        }

        // Final statistics
        $this->newLine();
        $this->info('=====================================');
        $this->info('📊 Migration Statistics:');
        $this->info("✅ Successfully migrated: {$this->successCount}");
        $this->info("❌ Failed: {$this->failedCount}");
        $this->info("⏭️  Skipped: {$this->skippedCount}");
        $this->newLine();

        if ($this->failedCount === 0 && !$testMode) {
            $this->updateMigrationStatus('completed');
            $this->info('🎉 Migration completed successfully!');
        } else {
            $this->updateMigrationStatus('in_progress');
            if ($testMode) {
                $this->info('✅ Test completed successfully!');
            }
        }

        return 0;
    }


    /**
     * Initialize migration status
     */
    private function initializeMigrationStatus(): void
    {
        $this->migrationStatus = DB::table('beach_migration_status')
            ->where('migration_name', 'old_db_import')
            ->first();

        if (!$this->migrationStatus) {
            DB::table('beach_migration_status')->insert([
                'migration_name' => 'old_db_import',
                'status' => 'not_started',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->migrationStatus = DB::table('beach_migration_status')
                ->where('migration_name', 'old_db_import')
                ->first();
        }
    }

    /**
     * Reset migration status
     */
    private function resetMigrationStatus(): void
    {
        DB::table('beach_migration_status')
            ->where('migration_name', 'old_db_import')
            ->update([
                'last_migrated_beach_id' => 0,
                'total_beaches_migrated' => 0,
                'total_beaches_failed' => 0,
                'status' => 'not_started',
                'last_error' => null,
                'started_at' => null,
                'completed_at' => null,
                'updated_at' => now(),
            ]);

        $this->migrationStatus = DB::table('beach_migration_status')
            ->where('migration_name', 'old_db_import')
            ->first();
    }

    /**
     * Update migration status
     */
    private function updateMigrationStatus(string $status, ?string $error = null): void
    {
        $data = [
            'status' => $status,
            'total_beaches_migrated' => $this->successCount,
            'total_beaches_failed' => $this->failedCount,
            'updated_at' => now(),
        ];

        if ($error) {
            $data['last_error'] = $error;
        }

        if ($status === 'in_progress' && $this->migrationStatus->status === 'not_started') {
            $data['started_at'] = now();
        }

        if ($status === 'completed') {
            $data['completed_at'] = now();
        }

        DB::table('beach_migration_status')
            ->where('migration_name', 'old_db_import')
            ->update($data);
    }

    /**
     * Update last migrated beach ID
     */
    private function updateLastMigratedBeachId(int $beachId): void
    {
        DB::table('beach_migration_status')
            ->where('migration_name', 'old_db_import')
            ->update([
                'last_migrated_beach_id' => $beachId,
                'updated_at' => now(),
            ]);
    }


    /**
     * Parse SQL file once and cache all data, then migrate beaches
     */
    private function parseAndMigrateBeaches(bool $testMode, bool $resume): void
    {
        $this->updateMigrationStatus('in_progress');

        // Phase 1: Parse SQL file once and cache all data
        $this->info('📖 Parsing SQL file and caching data...');
        $this->parseSqlFile();

        $totalBeaches = count($this->allBeaches);
        $this->info("✅ Parsed {$totalBeaches} beaches from SQL file");
        $this->info("✅ Cached pivot table data");
        $this->info("✅ Cached gallery data");
        $this->newLine();

        // Phase 2: Migrate beaches
        $startFrom = $resume ? $this->migrationStatus->last_migrated_beach_id : 0;
        $limit = $this->option('limit');
        $beachesToMigrate = $testMode ? 1 : ($limit ? (int)$limit : $totalBeaches);

        $progressBar = $this->output->createProgressBar($beachesToMigrate);
        $progressBar->start();

        $count = 0;
        foreach ($this->allBeaches as $beachData) {
            // Skip if resuming and haven't reached the last migrated beach yet
            if ($resume && $beachData['id'] <= $startFrom) {
                $this->skippedCount++;
                continue;
            }

            // Stop if we've reached the limit
            if ($count >= $beachesToMigrate) {
                break;
            }

            // Migrate this beach
            try {
                $this->migrateBeach($beachData);
                $this->successCount++;
                $this->updateLastMigratedBeachId($beachData['id']);
                $progressBar->advance();
                $count++;
            } catch (\Exception $e) {
                $this->failedCount++;
                $this->error("\n❌ Failed to migrate beach ID {$beachData['id']}: {$e->getMessage()}");
                $progressBar->advance();
                $count++;
            }
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Parse SQL file once and cache all data in memory
     */
    private function parseSqlFile(): void
    {
        $file = fopen($this->sqlFilePath, 'r');
        if (!$file) {
            throw new \Exception("Could not open SQL file");
        }

        $currentTable = null;
        $buffer = '';
        $insideInsert = false;

        // Tables we need to parse
        $tablesToParse = [
            'beach',
            'accommodation_around_beach',
            'culinary_offer_beach',
            'location_infrastructure_beach',
            'natural_location_condition_beach',
            'recommended_transport_type_beach',
            'sporting_activities_beach',
            'underwater_characteristic_beach',
            'user_tip_beach', // Old DB name (maps to user_type_beach in new DB)
            'gallery_base',
            'gallery',
        ];

        while (($line = fgets($file)) !== false) {
            // Detect INSERT statements for tables we care about
            if (!$insideInsert) {
                foreach ($tablesToParse as $table) {
                    if (strpos($line, "INSERT INTO `{$table}`") !== false) {
                        $currentTable = $table;
                        $insideInsert = true;
                        $buffer = $line;
                        break;
                    }
                }
            } else {
                // We're inside an INSERT statement, keep adding lines to buffer
                $buffer .= $line;

                // Check if this line ends the INSERT statement
                if (preg_match('/\);[\s]*$/', $line)) {
                    // Parse this INSERT statement
                    $this->parseInsertStatement($currentTable, $buffer);
                    $insideInsert = false;
                    $currentTable = null;
                    $buffer = '';
                }
            }
        }

        fclose($file);
    }

    /**
     * Parse INSERT statement and cache data based on table
     */
    private function parseInsertStatement(string $table, string $sql): void
    {
        switch ($table) {
            case 'beach':
                $beaches = $this->parseBeachInsertStatement($sql);
                $this->allBeaches = array_merge($this->allBeaches, $beaches);
                break;

            case 'gallery_base':
                $this->parseGalleryBaseInsertStatement($sql);
                break;

            case 'gallery':
                $this->parseGalleryInsertStatement($sql);
                break;

            default:
                // Pivot tables
                $this->parsePivotInsertStatement($table, $sql);
                break;
        }
    }


    /**
     * Parse beach INSERT statement and extract beach data
     */
    private function parseBeachInsertStatement(string $sql): array
    {
        $beaches = [];

        // Extract column names
        preg_match('/INSERT INTO `beach` \((.*?)\) VALUES/', $sql, $columnMatches);
        if (!isset($columnMatches[1])) {
            return $beaches;
        }

        $columns = array_map(function ($col) {
            return trim(str_replace('`', '', $col));
        }, explode(',', $columnMatches[1]));

        // Extract the VALUES portion
        if (!preg_match('/VALUES\s*(.+)$/s', $sql, $valuesMatch)) {
            return $beaches;
        }

        $valuesSection = $valuesMatch[1];

        // Parse value sets manually by tracking parentheses and quotes
        $valueSets = $this->extractValueSets($valuesSection);

        foreach ($valueSets as $valueSet) {
            $values = $this->parseValueSet($valueSet);

            if (count($values) === count($columns)) {
                $beachData = array_combine($columns, $values);
                $beaches[] = $beachData;
            }
        }

        return $beaches;
    }

    /**
     * Extract individual value sets from VALUES section
     * Handles nested parentheses in quoted strings
     */
    private function extractValueSets(string $valuesSection): array
    {
        $valueSets = [];
        $current = '';
        $depth = 0;
        $inQuotes = false;
        $quoteChar = null;
        $escaped = false;
        $length = strlen($valuesSection);

        for ($i = 0; $i < $length; $i++) {
            $char = $valuesSection[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $current .= $char;
                continue;
            }

            if (($char === "'" || $char === '"') && !$inQuotes) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
                continue;
            }

            if ($char === $quoteChar && $inQuotes) {
                $inQuotes = false;
                $quoteChar = null;
                $current .= $char;
                continue;
            }

            if ($char === '(' && !$inQuotes) {
                $depth++;
                if ($depth > 1) {
                    $current .= $char;
                }
                continue;
            }

            if ($char === ')' && !$inQuotes) {
                $depth--;
                if ($depth === 0) {
                    // End of a value set
                    $valueSets[] = trim($current);
                    $current = '';
                } else {
                    $current .= $char;
                }
                continue;
            }

            if ($depth > 0) {
                $current .= $char;
            }
        }

        return $valueSets;
    }

    /**
     * Parse a single value set from SQL INSERT
     * Handles NULL, quoted strings, numbers, etc.
     */
    private function parseValueSet(string $valueSet): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $escaped = false;

        for ($i = 0; $i < strlen($valueSet); $i++) {
            $char = $valueSet[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $current .= $char;
                continue;
            }

            if (($char === "'" || $char === '"') && !$inQuotes) {
                $inQuotes = true;
                $quoteChar = $char;
                continue;
            }

            if ($char === $quoteChar && $inQuotes) {
                $inQuotes = false;
                $quoteChar = null;
                continue;
            }

            if ($char === ',' && !$inQuotes) {
                $values[] = $this->cleanValue($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Add the last value
        if ($current !== '') {
            $values[] = $this->cleanValue($current);
        }

        return $values;
    }

    /**
     * Clean and convert SQL value to PHP value
     */
    private function cleanValue(string $value): mixed
    {
        $value = trim($value);

        // Handle NULL
        if (strtoupper($value) === 'NULL') {
            return null;
        }

        // Handle empty strings
        if ($value === '') {
            return null;
        }

        // Handle 'brez' (Slovenian for "without" - used as placeholder in old DB)
        if ($value === 'brez') {
            return null;
        }

        // Return the value (numbers will be strings, we'll cast them later)
        return $value;
    }


    /**
     * Migrate a single beach with all related data
     */
    private function migrateBeach(array $oldData): void
    {
        DB::beginTransaction();

        try {
            // Check if beach already exists
            $existingBeach = Beach::find($oldData['id']);
            if ($existingBeach) {
                // Skip if already migrated
                DB::rollBack();
                return;
            }

            // Migrate the beach record
            $beach = $this->createBeachRecord($oldData);

            // Migrate pivot table relationships
            $this->migratePivotRelationships($beach->id);

            // Migrate media/images
            $this->migrateBeachMedia($beach->id, $oldData['folder_name']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create the beach record
     */
    private function createBeachRecord(array $oldData): Beach
    {

        // Handle island/river/lake/region/city
        $islandId = null;
        $riverId = null;
        $lakeId = null;
        $regionId = null;
        $cityId = null;

        if (!empty($oldData['island_name'])) {
            $islandId = $this->getOrCreateIsland($oldData['island_name'], $oldData['island_name_nice_url']);
        }

        if (!empty($oldData['river_name'])) {
            $riverId = $this->getOrCreateRiver($oldData['river_name'], $oldData['river_name_nice_url']);
        }

        if (!empty($oldData['lake_name'])) {
            $lakeId = $this->getOrCreateLake($oldData['lake_name'], $oldData['lake_name_nice_url']);
        }

        if (!empty($oldData['region'])) {
            $regionId = $this->getOrCreateRegion(
                $oldData['region'],
                $oldData['region_nice_url'] ?? null,
                $oldData['region_full_name_nice_url'] ?? null,
                $oldData['country_code']
            );
        }

        if (!empty($oldData['city'])) {
            $cityId = $this->getOrCreateCity(
                $oldData['city'],
                $oldData['city_nice_url'] ?? null,
                $oldData['country_code'],
                $regionId
            );
        }

        // Map old swimming_depth_of_one_m (direct value) to swimming_depth_of_one_m_id (foreign key)
        $swimmingDepthId = !empty($oldData['swimming_depth_of_one_m']) ? (int)$oldData['swimming_depth_of_one_m'] : null;

        // Create beach record
        $beach = new Beach();
        $beach->id = (int)$oldData['id'];
        $beach->user_id = null; // Set to NULL since we don't have user data from old DB
        $beach->status_id = (int)$oldData['status_id'];
        $beach->continent_code = $oldData['continent_code'];
        $beach->country_code = $oldData['country_code'];
        $beach->waterbody_id = !empty($oldData['waterbody_id']) ? (int)$oldData['waterbody_id'] : null;
        $beach->beach_type_id = !empty($oldData['beach_type_id']) ? (int)$oldData['beach_type_id'] : null;
        $beach->area_description_id = !empty($oldData['area_description_id']) ? (int)$oldData['area_description_id'] : null;
        $beach->accessibility_id = !empty($oldData['accessibility_id']) ? (int)$oldData['accessibility_id'] : null;
        $beach->road_to_parking_type_id = !empty($oldData['road_to_parking_type_id']) ? (int)$oldData['road_to_parking_type_id'] : null;
        $beach->parking = !empty($oldData['parking']) ? (int)$oldData['parking'] : null;
        $beach->parking_capacity_id = !empty($oldData['parking_capacity_id']) ? (int)$oldData['parking_capacity_id'] : null;
        $beach->parking_beach_distance_id = !empty($oldData['parking_beach_distance_id']) ? (int)$oldData['parking_beach_distance_id'] : null;
        $beach->parking_beach_height_id = !empty($oldData['parking_beach_height_id']) ? (int)$oldData['parking_beach_height_id'] : null;
        $beach->location_length_id = !empty($oldData['location_length_id']) ? (int)$oldData['location_length_id'] : null;
        $beach->bay_length_id = !empty($oldData['bay_length_id']) ? (int)$oldData['bay_length_id'] : null;
        $beach->beach_inclination_id = !empty($oldData['beach_inclination_id']) ? (int)$oldData['beach_inclination_id'] : null;
        $beach->swimming_depth_of_one_m_id = $swimmingDepthId;
        $beach->occupancy_rate_id = !empty($oldData['occupancy_rate_id']) ? (int)$oldData['occupancy_rate_id'] : null;
        $beach->latitude = !empty($oldData['latitude']) ? $oldData['latitude'] : null;
        $beach->longitude = !empty($oldData['longitude']) ? $oldData['longitude'] : null;
        $beach->island_id = $islandId;
        $beach->river_id = $riverId;
        $beach->lake_id = $lakeId;
        $beach->region_id = $regionId;
        $beach->city_id = $cityId;
        $beach->folder_name = $oldData['folder_name'];
        $beach->mobile_upload = !empty($oldData['mobile_upload']) ? (bool)$oldData['mobile_upload'] : false;
        $beach->video_url = $oldData['video_url'];
        $beach->webcam = $oldData['webcam'];
        $beach->path_to_beach_map_url = $oldData['path_to_beach_map_url'];
        $beach->beach_weather = $oldData['beach_weather'];
        $beach->water_temperature = $oldData['water_temperature'];
        $beach->boat_only = !empty($oldData['boat_only']) ? (bool)$oldData['boat_only'] : false;
        $beach->beach_views = !empty($oldData['beach_views']) ? (int)$oldData['beach_views'] : 0;
        $beach->created_at = $oldData['datetime'];
        $beach->updated_at = $oldData['datetime_ut'];

        $beach->save();

        // Create beach translation (English only for now, using the name from old DB)
        if (!empty($oldData['name'])) {
            $translation = new BeachTranslation();
            $translation->beach_id = $beach->id;
            $translation->locale = 'en';
            $translation->name = $oldData['name'];
            $translation->description = $oldData['description'];
            $translation->slug = Str::slug($oldData['name']);
            $translation->save();
        }

        return $beach;
    }


    /**
     * Get or create island by name
     */
    private function getOrCreateIsland(string $name, ?string $slug): int
    {
        $cacheKey = strtolower($name);

        if (isset($this->islandCache[$cacheKey])) {
            return $this->islandCache[$cacheKey];
        }

        // Clean the name - remove "Island " prefix if present
        $cleanName = preg_replace('/^Island\s+/i', '', $name);

        // Try to find existing island by English translation (exact match)
        $island = Island::whereHas('translations', function ($query) use ($name) {
            $query->where('locale', 'en')->where('name', $name);
        })->first();

        // If not found, try with cleaned name
        if (!$island) {
            $island = Island::whereHas('translations', function ($query) use ($cleanName) {
                $query->where('locale', 'en')->where('name', $cleanName);
            })->first();
        }

        // If still not found, try by slug/code
        if (!$island && $slug) {
            $island = Island::where('code', $slug)->first();
        }

        if (!$island) {
            // Create new island
            $code = $slug ?? Str::slug($cleanName);

            $island = new Island();
            $island->code = $code;
            $island->status_id = 1; // Active
            $island->save();

            // Create English translation
            $translation = new IslandTranslation();
            $translation->island_id = $island->id;
            $translation->locale = 'en';
            $translation->name = $cleanName;
            $translation->slug = $code;
            $translation->save();
        }

        $this->islandCache[$cacheKey] = $island->id;
        return $island->id;
    }

    /**
     * Get or create river by name
     */
    private function getOrCreateRiver(string $name, ?string $slug): int
    {
        $cacheKey = strtolower($name);

        if (isset($this->riverCache[$cacheKey])) {
            return $this->riverCache[$cacheKey];
        }

        // Try to find existing river by English translation
        $river = River::whereHas('translations', function ($query) use ($name) {
            $query->where('locale', 'en')->where('name', $name);
        })->first();

        if (!$river) {
            // Create new river
            $code = $slug ?? Str::slug($name);

            $river = new River();
            $river->code = $code;
            $river->status_id = 1; // Active
            $river->save();

            // Create English translation
            $translation = new RiverTranslation();
            $translation->river_id = $river->id;
            $translation->locale = 'en';
            $translation->name = $name;
            $translation->slug = $code;
            $translation->save();
        }

        $this->riverCache[$cacheKey] = $river->id;
        return $river->id;
    }

    /**
     * Get or create lake by name
     */
    private function getOrCreateLake(string $name, ?string $slug): int
    {
        $cacheKey = strtolower($name);

        if (isset($this->lakeCache[$cacheKey])) {
            return $this->lakeCache[$cacheKey];
        }

        // Try to find existing lake by English translation
        $lake = Lake::whereHas('translations', function ($query) use ($name) {
            $query->where('locale', 'en')->where('name', $name);
        })->first();

        if (!$lake) {
            // Create new lake
            $code = $slug ?? Str::slug($name);

            $lake = new Lake();
            $lake->code = $code;
            $lake->status_id = 1; // Active
            $lake->save();

            // Create English translation
            $translation = new LakeTranslation();
            $translation->lake_id = $lake->id;
            $translation->locale = 'en';
            $translation->name = $name;
            $translation->slug = $code;
            $translation->save();
        }

        $this->lakeCache[$cacheKey] = $lake->id;
        return $lake->id;
    }

    /**
     * Get or create region by name
     *
     * @param string $name Region name (e.g., "Dalmatia - Dubrovnik")
     * @param string|null $shortSlug Short slug (e.g., "dalmatia")
     * @param string|null $fullSlug Full slug (e.g., "dalmatia-dubrovnik")
     * @param string $countryCode Country code (e.g., "HR")
     * @return int Region ID
     */
    private function getOrCreateRegion(string $name, ?string $shortSlug, ?string $fullSlug, string $countryCode): int
    {
        // Use full slug as cache key, fallback to name
        $cacheKey = strtolower($fullSlug ?? $name);

        if (isset($this->regionCache[$cacheKey])) {
            return $this->regionCache[$cacheKey];
        }

        // Try to find existing region by code (full slug) or by English translation
        $code = $fullSlug ?? Str::slug($name);

        $region = Region::where('code', $code)->first();

        if (!$region) {
            // Try to find by English translation
            $region = Region::whereHas('translations', function ($query) use ($name) {
                $query->where('locale', 'en')->where('name', $name);
            })->first();
        }

        if (!$region) {
            // Create new region using firstOrCreate to avoid duplicate key errors
            $region = Region::firstOrCreate(
                ['code' => $code],
                [
                    'country_code' => strtoupper($countryCode),
                    'status_id' => 1, // Active
                ]
            );

            // Create English translation if it doesn't exist
            RegionTranslation::firstOrCreate(
                ['region_id' => $region->id, 'locale' => 'en'],
                [
                    'name' => $name,
                    'slug' => $code,
                ]
            );
        }

        $this->regionCache[$cacheKey] = $region->id;
        return $region->id;
    }

    /**
     * Get or create city by name
     *
     * @param string $name City name (e.g., "Rijeka")
     * @param string|null $slug City slug (e.g., "rijeka")
     * @param string $countryCode Country code (e.g., "HR")
     * @param int|null $regionId Region ID
     * @return int City ID
     */
    private function getOrCreateCity(string $name, ?string $slug, string $countryCode, ?int $regionId): int
    {
        // Use slug as cache key, fallback to name
        $cacheKey = strtolower($slug ?? $name);

        if (isset($this->cityCache[$cacheKey])) {
            return $this->cityCache[$cacheKey];
        }

        // Try to find existing city by code (slug) or by English translation
        $code = $slug ?? Str::slug($name);

        $city = City::where('code', $code)->first();

        if (!$city) {
            // Try to find by English translation
            $city = City::whereHas('translations', function ($query) use ($name) {
                $query->where('locale', 'en')->where('name', $name);
            })->first();
        }

        if (!$city) {
            // Create new city using firstOrCreate to avoid duplicate key errors
            $city = City::firstOrCreate(
                ['code' => $code],
                [
                    'country_code' => strtoupper($countryCode),
                    'region_id' => $regionId,
                    'status_id' => 1, // Active
                ]
            );

            // Create English translation if it doesn't exist
            CityTranslation::firstOrCreate(
                ['city_id' => $city->id, 'locale' => 'en'],
                [
                    'name' => $name,
                    'slug' => $code,
                ]
            );
        }

        $this->cityCache[$cacheKey] = $city->id;
        return $city->id;
    }

    /**
     * Parse pivot table INSERT statement and cache data
     */
    private function parsePivotInsertStatement(string $tableName, string $sql): void
    {
        // Extract column names
        preg_match("/INSERT INTO `{$tableName}` \((.*?)\) VALUES/", $sql, $columnMatches);
        if (!isset($columnMatches[1])) {
            return;
        }

        $columns = array_map(function ($col) {
            return trim(str_replace('`', '', $col));
        }, explode(',', $columnMatches[1]));

        // Extract all value sets
        preg_match_all('/\(([^)]+)\)(?=,|\s*;)/', $sql, $valueMatches);

        if (!isset($this->pivotData[$tableName])) {
            $this->pivotData[$tableName] = [];
        }

        foreach ($valueMatches[1] as $valueSet) {
            $values = $this->parseValueSet($valueSet);

            if (count($values) === count($columns)) {
                $data = array_combine($columns, $values);
                $beachId = (int)$data['beach_id'];

                if (!isset($this->pivotData[$tableName][$beachId])) {
                    $this->pivotData[$tableName][$beachId] = [];
                }

                $this->pivotData[$tableName][$beachId][] = $data;
            }
        }
    }

    /**
     * Migrate pivot table relationships for a beach from cached data
     */
    private function migratePivotRelationships(int $beachId): void
    {
        // Map of old table names to new table names and their relation column names
        // Old DB has user_tip_beach, new DB has user_type_beach
        $pivotTables = [
            'accommodation_around_beach' => ['table' => 'accommodation_around_beach', 'column' => 'accommodation_around_id'],
            'culinary_offer_beach' => ['table' => 'culinary_offer_beach', 'column' => 'culinary_offer_id'],
            'location_infrastructure_beach' => ['table' => 'location_infrastructure_beach', 'column' => 'location_infrastructure_id'],
            'natural_location_condition_beach' => ['table' => 'natural_location_condition_beach', 'column' => 'natural_location_condition_id'],
            'recommended_transport_type_beach' => ['table' => 'recommended_transport_type_beach', 'column' => 'recommended_transport_type_id'],
            'sporting_activities_beach' => ['table' => 'sporting_activities_beach', 'column' => 'sporting_activities_id'],
            'underwater_characteristic_beach' => ['table' => 'underwater_characteristic_beach', 'column' => 'underwater_characteristic_id'],
            'user_tip_beach' => ['table' => 'user_type_beach', 'column' => 'user_type_id'], // Renamed in new DB
        ];

        foreach ($pivotTables as $oldTableName => $config) {
            $newTableName = $config['table'];
            $oldColumnName = str_replace('_type_', '_tip_', $config['column']); // user_type_id → user_tip_id for old DB
            $newColumnName = $config['column'];

            if (isset($this->pivotData[$oldTableName][$beachId])) {
                foreach ($this->pivotData[$oldTableName][$beachId] as $pivotRecord) {
                    // Use old column name if it exists in the record, otherwise use new column name
                    $relationValue = isset($pivotRecord[$oldColumnName]) ? $pivotRecord[$oldColumnName] : $pivotRecord[$newColumnName];

                    DB::table($newTableName)->insert([
                        'beach_id' => $beachId,
                        $newColumnName => (int)$relationValue,
                        'status_id' => isset($pivotRecord['status_id']) ? (int)$pivotRecord['status_id'] : 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Parse gallery_base INSERT statement and cache data
     */
    private function parseGalleryBaseInsertStatement(string $sql): void
    {
        preg_match('/INSERT INTO `gallery_base` \((.*?)\) VALUES/', $sql, $columnMatches);
        if (!isset($columnMatches[1])) {
            return;
        }

        $columns = array_map(function ($col) {
            return trim(str_replace('`', '', $col));
        }, explode(',', $columnMatches[1]));

        preg_match_all('/\(([^)]+)\)(?=,|\s*;)/', $sql, $valueMatches);

        foreach ($valueMatches[1] as $valueSet) {
            $values = $this->parseValueSet($valueSet);

            if (count($values) === count($columns)) {
                $data = array_combine($columns, $values);
                $beachId = (int)$data['beach_id'];
                $galleryBaseId = (int)$data['id'];

                // Map gallery_base_id to beach_id
                $this->galleryBaseData[$galleryBaseId] = $beachId;
            }
        }
    }

    /**
     * Parse gallery INSERT statement and cache data
     */
    private function parseGalleryInsertStatement(string $sql): void
    {
        preg_match('/INSERT INTO `gallery` \((.*?)\) VALUES/', $sql, $columnMatches);
        if (!isset($columnMatches[1])) {
            return;
        }

        $columns = array_map(function ($col) {
            return trim(str_replace('`', '', $col));
        }, explode(',', $columnMatches[1]));

        preg_match_all('/\(([^)]+)\)(?=,|\s*;)/', $sql, $valueMatches);

        foreach ($valueMatches[1] as $valueSet) {
            $values = $this->parseValueSet($valueSet);

            if (count($values) === count($columns)) {
                $data = array_combine($columns, $values);
                $galleryBaseId = (int)$data['gallery_base_id'];

                if (!isset($this->galleryData[$galleryBaseId])) {
                    $this->galleryData[$galleryBaseId] = [];
                }

                $this->galleryData[$galleryBaseId][] = $data;
            }
        }
    }

    /**
     * Migrate media/images for a beach from cached data
     */
    private function migrateBeachMedia(int $beachId, ?string $folderName): void
    {
        if (empty($folderName)) {
            return;
        }

        // Find gallery_base_id for this beach
        $galleryBaseId = array_search($beachId, $this->galleryBaseData);
        if ($galleryBaseId === false) {
            return;
        }

        // Get all gallery images for this gallery_base_id
        if (!isset($this->galleryData[$galleryBaseId])) {
            return;
        }

        // Get media type IDs (cache these)
        static $imageTypeId = null;
        static $sphereTypeId = null;
        static $beachGalleryCollectionId = null;
        static $parkingImagesCollectionId = null;
        static $sphere360CollectionId = null;
        static $spotImagesCollectionId = null;
        static $infrastructureImagesCollectionId = null;
        static $infoImagesCollectionId = null;

        if ($imageTypeId === null) {
            $imageTypeId = DB::table('media_types')->where('code', 'image')->value('id');
            $sphereTypeId = DB::table('media_types')->where('code', 'sphere_360')->value('id');
            $beachGalleryCollectionId = DB::table('media_collections')->where('code', 'beach_gallery')->value('id');
            $parkingImagesCollectionId = DB::table('media_collections')->where('code', 'parking_images')->value('id');
            $sphere360CollectionId = DB::table('media_collections')->where('code', 'beach_360_sphere')->value('id');
            $spotImagesCollectionId = DB::table('media_collections')->where('code', 'beach_spot_images')->value('id');
            $infrastructureImagesCollectionId = DB::table('media_collections')->where('code', 'infrastructure_images')->value('id');
            $infoImagesCollectionId = DB::table('media_collections')->where('code', 'info_images')->value('id');
        }

        $order = 0;

        foreach ($this->galleryData[$galleryBaseId] as $galleryImage) {
            $fileName = $galleryImage['name'];
            $galleryTypeId = isset($galleryImage['gallery_type_id']) ? (int)$galleryImage['gallery_type_id'] : null;
            $isMain = isset($galleryImage['main']) && $galleryImage['main'] == 1;

            // Map old gallery_type_id to new media_type_id and media_collection_id
            // Old DB: 1=parking, 2=spot, 3=beach, 4=infrastructure, 5=sphere, 6=info
            // New DB media_type: 1=image, 3=sphere_360
            // New DB media_collection: 1=beach_gallery, 3=beach_360_sphere, 4=parking_images, 5=beach_spot_images, 6=infrastructure_images, 7=info_images
            switch ($galleryTypeId) {
                case 5:
                    // Sphere/360° panorama
                    $mediaTypeId = $sphereTypeId;
                    $mediaCollectionId = $sphere360CollectionId;
                    break;
                case 1:
                    // Parking images
                    $mediaTypeId = $imageTypeId;
                    $mediaCollectionId = $parkingImagesCollectionId;
                    break;
                case 2:
                    // Spot images
                    $mediaTypeId = $imageTypeId;
                    $mediaCollectionId = $spotImagesCollectionId;
                    break;
                case 4:
                    // Infrastructure images
                    $mediaTypeId = $imageTypeId;
                    $mediaCollectionId = $infrastructureImagesCollectionId;
                    break;
                case 6:
                    // Info images
                    $mediaTypeId = $imageTypeId;
                    $mediaCollectionId = $infoImagesCollectionId;
                    break;
                default:
                    // Type 3 (beach) and any other types go to beach gallery
                    $mediaTypeId = $imageTypeId;
                    $mediaCollectionId = $beachGalleryCollectionId;
                    break;
            }

            // Build file path: img/beaches/{folder_name}/{filename}
            $filePath = "img/beaches/{$folderName}/{$fileName}";

            // Insert media record
            DB::table('media')->insert([
                'mediable_type' => 'App\\Models\\Beach',
                'mediable_id' => $beachId,
                'media_type_id' => $mediaTypeId,
                'media_collection_id' => $mediaCollectionId,
                'user_id' => null,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'disk' => 'spaces', // DigitalOcean Spaces
                'mime_type' => 'image/jpeg',
                'size' => 0, // Unknown
                'width' => null,
                'height' => null,
                'duration' => null,
                'is_primary' => $isMain,
                'order' => $order++,
                'metadata' => json_encode([]),
                'status_id' => isset($galleryImage['status_id']) ? (int)$galleryImage['status_id'] : 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

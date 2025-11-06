<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserMigrationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MigrateUsersFromOldDb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:migrate-from-old-db
                            {--test : Test mode - migrate only first 10 users}
                            {--limit= : Limit number of users to migrate}
                            {--resume : Resume from last migration}
                            {--reset : Reset migration status and start fresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate users from old database SQL dump';

    /**
     * SQL file path
     */
    private string $sqlFilePath;

    /**
     * Statistics
     */
    private int $successCount = 0;
    private int $failedCount = 0;
    private int $skippedCount = 0;

    /**
     * All users data from SQL file
     */
    private array $allUsers = [];

    public function __construct()
    {
        parent::__construct();
        $this->sqlFilePath = database_path('sql/user.sql');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('👥 User Migration from Old Database');
        $this->info('===================================');
        $this->newLine();

        // Check if SQL file exists
        if (!file_exists($this->sqlFilePath)) {
            $this->error("SQL file not found: {$this->sqlFilePath}");
            return 1;
        }

        // Handle reset option
        if ($this->option('reset')) {
            $this->warn('🔄 Resetting migration status...');
            UserMigrationStatus::truncate();
            $this->info('✅ Migration status reset complete');
            $this->newLine();
        }

        // Parse and migrate users
        $testMode = $this->option('test');
        $limit = $this->option('limit');
        $resume = $this->option('resume');

        if ($testMode) {
            $this->warn('🧪 TEST MODE: Migrating only first 10 users');
        } elseif ($limit) {
            $this->warn("📊 LIMIT MODE: Migrating only {$limit} users");
        }

        if ($resume) {
            $this->warn('▶️  RESUME MODE: Continuing from last migration');
        }

        $this->newLine();
        $this->info('📖 Parsing SQL file...');

        try {
            $this->parseAndMigrateUsers($testMode, $resume);
        } catch (\Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return 1;
        }

        // Display summary
        $this->newLine();
        $this->info('✅ Migration completed!');
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Successfully migrated', $this->successCount],
                ['Failed', $this->failedCount],
                ['Skipped (already exist)', $this->skippedCount],
                ['Total processed', $this->successCount + $this->failedCount + $this->skippedCount],
            ]
        );

        return 0;
    }

    /**
     * Parse SQL file and migrate users
     */
    private function parseAndMigrateUsers(bool $testMode, bool $resume): void
    {
        // Phase 1: Parse SQL file
        $this->info('📖 Parsing SQL file and caching data...');
        $this->parseSqlFile();

        $totalUsers = count($this->allUsers);
        $this->info("✅ Parsed {$totalUsers} users from SQL file");
        $this->newLine();

        // Phase 2: Determine which users to migrate
        $limit = $this->option('limit');
        $usersToProcess = [];

        if ($resume) {
            // Get already processed user IDs
            $processedIds = UserMigrationStatus::whereIn('status', ['success', 'skipped'])
                ->pluck('old_user_id')
                ->toArray();

            $this->info("📊 Found " . count($processedIds) . " already processed users");

            // Filter out already processed users
            foreach ($this->allUsers as $userData) {
                if (!in_array($userData['id'], $processedIds)) {
                    $usersToProcess[] = $userData;
                }
            }

            $this->info("📋 Remaining users to process: " . count($usersToProcess));
        } else {
            $usersToProcess = $this->allUsers;
        }

        $usersToMigrate = $testMode ? 10 : ($limit ? (int)$limit : count($usersToProcess));
        $usersToMigrate = min($usersToMigrate, count($usersToProcess));

        if ($usersToMigrate === 0) {
            $this->info('✅ All users already migrated!');
            return;
        }

        $this->newLine();
        $progressBar = $this->output->createProgressBar($usersToMigrate);
        $progressBar->start();

        $count = 0;
        foreach ($usersToProcess as $userData) {
            // Stop if we've reached the limit
            if ($count >= $usersToMigrate) {
                break;
            }

            // Migrate this user
            try {
                $this->migrateUser($userData);
                $this->successCount++;
                $progressBar->advance();
                $count++;
            } catch (\Exception $e) {
                $this->failedCount++;
                $this->error("\n❌ Failed to migrate user: {$e->getMessage()}");
                $progressBar->advance();
                $count++;
            }
        }

        $progressBar->finish();
        $this->newLine();
    }


    /**
     * Parse SQL file and cache all data in memory
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

        while (($line = fgets($file)) !== false) {
            // Detect INSERT statements for user table
            if (!$insideInsert) {
                if (strpos($line, "INSERT INTO `user`") !== false) {
                    $currentTable = 'user';
                    $insideInsert = true;
                    $buffer = $line;
                }
            } else {
                // We're inside an INSERT statement, keep adding lines to buffer
                $buffer .= $line;

                // Check if this line ends the INSERT statement
                if (preg_match('/\);[\s]*$/', $line)) {
                    // Parse this INSERT statement
                    $this->parseInsertStatement($buffer);
                    $insideInsert = false;
                    $currentTable = null;
                    $buffer = '';
                }
            }
        }

        fclose($file);
    }

    /**
     * Parse INSERT statement and extract user data
     */
    private function parseInsertStatement(string $sql): void
    {
        // Extract column names
        preg_match('/INSERT INTO `user` \((.*?)\) VALUES/', $sql, $columnMatches);
        if (!isset($columnMatches[1])) {
            return;
        }

        $columns = array_map(function ($col) {
            return trim(str_replace('`', '', $col));
        }, explode(',', $columnMatches[1]));

        // Extract the VALUES portion
        if (!preg_match('/VALUES\s*(.+)$/s', $sql, $valuesMatch)) {
            return;
        }

        $valuesSection = $valuesMatch[1];

        // Parse value sets manually by tracking parentheses and quotes
        $valueSets = $this->extractValueSets($valuesSection);

        // Generate sequential IDs starting from the current count + 1
        $startId = count($this->allUsers) + 1;

        foreach ($valueSets as $index => $valueSet) {
            $values = $this->parseValueSet($valueSet);

            if (count($values) === count($columns)) {
                $userData = array_combine($columns, $values);
                // Add auto-increment ID
                $userData['id'] = $startId + $index;
                $this->allUsers[] = $userData;
            }
        }
    }

    /**
     * Extract individual value sets from VALUES section
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
                $current .= $char;
                $escaped = true;
                continue;
            }

            if (($char === '"' || $char === "'") && !$inQuotes) {
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
                if ($depth === 1) {
                    $current = '';
                    continue;
                }
            }

            if ($char === ')' && !$inQuotes) {
                $depth--;
                if ($depth === 0) {
                    $valueSets[] = $current;
                    $current = '';
                    continue;
                }
            }

            if ($depth > 0) {
                $current .= $char;
            }
        }

        return $valueSets;
    }

    /**
     * Parse a single value set into an array of values
     */
    private function parseValueSet(string $valueSet): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $escaped = false;
        $length = strlen($valueSet);

        for ($i = 0; $i < $length; $i++) {
            $char = $valueSet[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }

            if (($char === '"' || $char === "'") && !$inQuotes) {
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
                $values[] = $this->normalizeValue($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Add the last value
        if ($current !== '') {
            $values[] = $this->normalizeValue($current);
        }

        return $values;
    }

    /**
     * Normalize a value (handle NULL, trim, etc.)
     */
    private function normalizeValue(string $value): ?string
    {
        $value = trim($value);

        if ($value === 'NULL' || $value === '') {
            return null;
        }

        // Remove quotes if present
        if ((str_starts_with($value, "'") && str_ends_with($value, "'")) ||
            (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            $value = substr($value, 1, -1);
        }

        // Unescape
        $value = str_replace("\\'", "'", $value);
        $value = str_replace('\\"', '"', $value);
        $value = str_replace('\\\\', '\\', $value);

        return $value;
    }

    /**
     * Migrate a single user
     */
    private function migrateUser(array $oldData): void
    {
        $oldUserId = $oldData['id'];

        // Check if already processed
        $status = UserMigrationStatus::where('old_user_id', $oldUserId)->first();
        if ($status && in_array($status->status, ['success', 'skipped'])) {
            $this->skippedCount++;
            return;
        }

        DB::beginTransaction();

        try {
            // Check if user already exists by email
            $existingUser = User::where('email', $oldData['email'])->first();
            if ($existingUser) {
                // Skip if already migrated
                $this->skippedCount++;

                // Update or create status
                UserMigrationStatus::updateOrCreate(
                    ['old_user_id' => $oldUserId],
                    [
                        'new_user_id' => $existingUser->id,
                        'status' => 'skipped',
                        'error_message' => 'User already exists',
                    ]
                );

                DB::commit();
                return;
            }

            // Create user record
            $newUser = $this->createUserRecord($oldData);

            // Record successful migration
            UserMigrationStatus::updateOrCreate(
                ['old_user_id' => $oldUserId],
                [
                    'new_user_id' => $newUser->id,
                    'status' => 'success',
                    'error_message' => null,
                ]
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            // Record failed migration
            UserMigrationStatus::updateOrCreate(
                ['old_user_id' => $oldUserId],
                [
                    'new_user_id' => null,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Create the user record
     */
    private function createUserRecord(array $oldData): User
    {
        // Combine firstname and lastname for the name field
        $name = trim(($oldData['firstname'] ?? '') . ' ' . ($oldData['lastname'] ?? ''));
        if (empty($name)) {
            $name = $oldData['username'] ?? 'Unknown User';
        }

        // Validate country code
        $countryCode = !empty($oldData['country_id']) ? strtoupper($oldData['country_id']) : null;
        if ($countryCode && strlen($countryCode) !== 2) {
            $countryCode = null;
        }

        // Create user
        $user = new User();
        $user->name = $name;
        $user->email = $oldData['email'] ?? null;
        $user->password = $oldData['password'] ?? Hash::make('password'); // Keep old hash or set default
        $user->username = $oldData['username'] ?? null;
        $user->firstname = $oldData['firstname'] ?? null;
        $user->lastname = $oldData['lastname'] ?? null;
        $user->date_of_birth = !empty($oldData['date_of_birth']) ? $oldData['date_of_birth'] : null;
        $user->gender_id = !empty($oldData['gender_id']) ? (int)$oldData['gender_id'] : null;
        $user->mobile = $oldData['mobile'] ?? null;
        $user->country_code = $countryCode;
        $user->status_id = !empty($oldData['status_id']) ? (int)$oldData['status_id'] : 1;
        $user->register_type = $oldData['register_type'] ?? null;
        $user->is_admin = false;

        // Set timestamps from old data
        if (!empty($oldData['datetime'])) {
            $user->created_at = $oldData['datetime'];
        }
        if (!empty($oldData['datetime_ut'])) {
            $user->updated_at = $oldData['datetime_ut'];
        }

        $user->save();

        return $user;
    }
}

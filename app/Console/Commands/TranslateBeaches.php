<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Models\BeachTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class TranslateBeaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beaches:translate
                            {--beach-id= : Translate only a specific beach ID}
                            {--limit= : Limit number of beaches to process}
                            {--dry-run : Show what would be translated without actually translating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translate beach names and descriptions to all 19 languages using AI';

    /**
     * All supported locales
     */
    protected array $locales = ['en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq'];

    /**
     * Language names for better AI translation
     */
    protected array $languageNames = [
        'en' => 'English',
        'sl' => 'Slovenian',
        'de' => 'German',
        'hr' => 'Croatian',
        'fr' => 'French',
        'es' => 'Spanish',
        'it' => 'Italian',
        'ua' => 'Ukrainian',
        'ru' => 'Russian',
        'pl' => 'Polish',
        'cs' => 'Czech',
        'hu' => 'Hungarian',
        'bg' => 'Bulgarian',
        'sk' => 'Slovak',
        'da' => 'Danish',
        'pt' => 'Portuguese',
        'nl' => 'Dutch',
        'el' => 'Greek',
        'sq' => 'Albanian',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🌊 Beach Translation Script Started');
        $this->newLine();

        // Build query
        $query = Beach::query()->where('status_id', 1);

        if ($beachId = $this->option('beach-id')) {
            $query->where('id', $beachId);
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $beaches = $query->get();

        if ($beaches->isEmpty()) {
            $this->warn('No beaches found to translate.');
            return 0;
        }

        $this->info("Found {$beaches->count()} beach(es) to process");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($beaches->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

        $totalTranslated = 0;
        $totalSkipped = 0;

        foreach ($beaches as $beach) {
            $progressBar->setMessage("Processing: {$beach->id}");

            $result = $this->translateBeach($beach);

            $totalTranslated += $result['translated'];
            $totalSkipped += $result['skipped'];

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✅ Translation completed!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Beaches processed', $beaches->count()],
                ['Translations created', $totalTranslated],
                ['Translations skipped (already exist)', $totalSkipped],
            ]
        );

        return 0;
    }

    /**
     * Translate a single beach to all missing languages
     */
    protected function translateBeach(Beach $beach): array
    {
        $translated = 0;
        $skipped = 0;

        // Get existing translations
        $existingTranslations = $beach->translations()->pluck('locale')->toArray();

        // Find missing locales
        $missingLocales = array_diff($this->locales, $existingTranslations);

        if (empty($missingLocales)) {
            return ['translated' => 0, 'skipped' => count($this->locales)];
        }

        // Get source translation (prefer English, fallback to Croatian)
        $sourceTranslation = $beach->translations()
            ->whereIn('locale', ['en', 'hr'])
            ->orderByRaw("CASE WHEN locale = 'en' THEN 1 WHEN locale = 'hr' THEN 2 ELSE 3 END")
            ->first();

        if (!$sourceTranslation) {
            $this->warn("Beach ID {$beach->id} has no source translation (en or hr). Skipping.");
            return ['translated' => 0, 'skipped' => count($missingLocales)];
        }

        $sourceName = $sourceTranslation->name;
        $sourceDescription = $sourceTranslation->description;
        $sourceLocale = $sourceTranslation->locale;
        $sourceLanguage = $this->languageNames[$sourceLocale];

        if ($this->option('dry-run')) {
            $this->line("  [DRY RUN] Would translate beach #{$beach->id} '{$sourceName}' from {$sourceLanguage} to " . count($missingLocales) . " languages");
            return ['translated' => 0, 'skipped' => count($missingLocales)];
        }

        // Translate to each missing locale
        foreach ($missingLocales as $targetLocale) {
            try {
                $targetLanguage = $this->languageNames[$targetLocale];

                $translation = $this->translateWithAI(
                    $sourceName,
                    $sourceDescription,
                    $sourceLanguage,
                    $targetLanguage
                );

                // Create translation record
                BeachTranslation::create([
                    'beach_id' => $beach->id,
                    'locale' => $targetLocale,
                    'name' => $translation['name'],
                    'description' => $translation['description'],
                    'slug' => Str::slug($translation['name']),
                ]);

                $translated++;

                // Small delay to avoid rate limiting
                usleep(100000); // 0.1 second

            } catch (\Exception $e) {
                $this->error("  Failed to translate beach #{$beach->id} to {$targetLanguage}: " . $e->getMessage());
            }
        }

        return ['translated' => $translated, 'skipped' => $skipped];
    }

    /**
     * Translate text using OpenAI
     */
    protected function translateWithAI(
        string $name,
        ?string $description,
        string $sourceLanguage,
        string $targetLanguage
    ): array {
        $prompt = "You are a professional translator specializing in beach and tourism content.\n\n";
        $prompt .= "Translate the following beach information from {$sourceLanguage} to {$targetLanguage}.\n\n";
        $prompt .= "IMPORTANT RULES:\n";
        $prompt .= "1. Keep beach names natural - translate descriptive words but keep proper names\n";
        $prompt .= "2. For descriptions, provide natural, fluent translations\n";
        $prompt .= "3. Maintain the same tone and style\n";
        $prompt .= "4. If description is empty, return empty string\n";
        $prompt .= "5. Return ONLY valid JSON with 'name' and 'description' keys\n\n";
        $prompt .= "Beach Name: {$name}\n";
        $prompt .= "Beach Description: " . ($description ?: '(empty)') . "\n\n";
        $prompt .= "Return format: {\"name\": \"translated name\", \"description\": \"translated description\"}";

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a professional translator. Always respond with valid JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1000,
        ]);

        $content = $response->choices[0]->message->content;

        // Parse JSON response
        $translated = json_decode($content, true);

        if (!$translated || !isset($translated['name'])) {
            throw new \Exception("Invalid AI response: {$content}");
        }

        return [
            'name' => $translated['name'],
            'description' => $translated['description'] ?? '',
        ];
    }
}


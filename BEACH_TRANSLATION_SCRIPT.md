# Beach Translation Script

Automatically translate beach names and descriptions to all 14 supported languages using OpenAI.

## Supported Languages

The script translates to all 14 languages:
- `en` - English
- `sl` - Slovenian  
- `de` - German
- `hr` - Croatian
- `fr` - French
- `es` - Spanish
- `it` - Italian
- `ua` - Ukrainian
- `ru` - Russian
- `pl` - Polish
- `cs` - Czech
- `hu` - Hungarian
- `bg` - Bulgarian
- `sk` - Slovak

## Setup

### 1. Add OpenAI API Key

Add your OpenAI API key to `.env`:

```bash
OPENAI_API_KEY=sk-your-api-key-here
```

You can get an API key from: https://platform.openai.com/api-keys

### 2. Verify Configuration

Check that the config file exists at `config/openai.php`. It should have been published automatically.

## Usage

### Translate All Beaches

Translate all active beaches (status_id = 1) that have missing translations:

```bash
./vendor/bin/sail artisan beaches:translate
```

### Translate Specific Beach

Translate only a specific beach by ID:

```bash
./vendor/bin/sail artisan beaches:translate --beach-id=123
```

### Limit Number of Beaches

Process only a limited number of beaches (useful for testing):

```bash
./vendor/bin/sail artisan beaches:translate --limit=10
```

### Dry Run (Preview)

See what would be translated without actually making API calls:

```bash
./vendor/bin/sail artisan beaches:translate --dry-run
```

### Combine Options

```bash
./vendor/bin/sail artisan beaches:translate --limit=5 --dry-run
```

## How It Works

1. **Finds beaches** with missing translations (only active beaches with status_id = 1)
2. **Uses source translation** (prefers English, falls back to Croatian)
3. **Translates to missing languages** using OpenAI GPT-4o-mini
4. **Creates translation records** with:
   - Translated name
   - Translated description
   - Auto-generated slug from translated name
5. **Skips existing translations** - won't overwrite existing data

## Translation Logic

### Beach Names
- Keeps proper names (e.g., "Livade", "Split")
- Translates descriptive words naturally
- Example: "Beach Livade" → "Plaža Livade" (Croatian), "Strand Livade" (German)

### Descriptions
- Provides natural, fluent translations
- Maintains the same tone and style
- Handles empty descriptions gracefully

### Slugs
- Auto-generated from translated name using Laravel's `Str::slug()`
- URL-friendly format

## Output Example

```
🌊 Beach Translation Script Started

Found 150 beach(es) to process

 45/150 [=========>------------------]  30% - Processing: 623

✅ Translation completed!
+------------------------------------------+-------+
| Metric                                   | Count |
+------------------------------------------+-------+
| Beaches processed                        | 150   |
| Translations created                     | 1,950 |
| Translations skipped (already exist)     | 150   |
+------------------------------------------+-------+
```

## Cost Estimation

Using GPT-4o-mini (very affordable):
- ~$0.15 per 1M input tokens
- ~$0.60 per 1M output tokens

Estimated cost per beach (13 missing translations):
- Input: ~200 tokens × 13 = 2,600 tokens
- Output: ~150 tokens × 13 = 1,950 tokens
- **Cost: ~$0.002 per beach** (less than 1 cent!)

For 1,000 beaches: ~$2.00 total

## Rate Limiting

The script includes a 0.1 second delay between API calls to avoid rate limiting. You can adjust this in the code if needed.

## Error Handling

- Skips beaches without source translations (en or hr)
- Logs errors for failed translations
- Continues processing even if some translations fail
- Shows detailed error messages

## Troubleshooting

### "No beaches found to translate"
- Check that you have beaches with status_id = 1
- Verify beaches have at least one translation (en or hr)

### "Invalid AI response"
- Check your OpenAI API key is valid
- Verify you have API credits
- Check your internet connection

### Rate limit errors
- Increase the delay in the code (change `usleep(100000)` to higher value)
- Process in smaller batches using `--limit`

## Advanced Usage

### Process in Batches

To avoid long-running processes, you can process beaches in batches:

```bash
# First 100 beaches
./vendor/bin/sail artisan beaches:translate --limit=100

# Run again for next batch (it will skip already translated ones)
./vendor/bin/sail artisan beaches:translate --limit=100
```

### Schedule Automatic Translation

Add to `app/Console/Kernel.php` to run daily:

```php
protected function schedule(Schedule $schedule)
{
    // Translate 50 beaches per day automatically
    $schedule->command('beaches:translate --limit=50')
             ->daily()
             ->at('02:00');
}
```

## Database Schema

The script creates records in the `beach_translation` table:

```sql
CREATE TABLE beach_translation (
    id BIGINT UNSIGNED PRIMARY KEY,
    beach_id BIGINT UNSIGNED,
    locale VARCHAR(5),
    name VARCHAR(255),
    description TEXT NULL,
    slug VARCHAR(255) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY (beach_id, locale)
);
```

## Notes

- **Idempotent**: Safe to run multiple times - won't duplicate translations
- **Incremental**: Only translates missing languages
- **Smart source selection**: Uses English if available, falls back to Croatian
- **Production ready**: Includes error handling, progress tracking, and dry-run mode


# Meilisearch Configuration and Setup

## Overview
This project uses **Meilisearch** for global search across beaches, cities, islands, regions, and countries. The search supports:
- Multi-language search (Slovenian, English, German, Croatian, etc.)
- Diacritics/special characters (č, š, ž, ć, đ, ü, ö, ä, etc.)
- Typo tolerance
- Plural handling

## Configuration

### Index Settings
The Meilisearch index settings are configured in `config/scout.php`:
- **searchableAttributes**: Fields that are searchable
- **filterableAttributes**: Fields that can be filtered
- **sortableAttributes**: Fields that can be sorted
- **nonSeparatorTokens**: Special characters that should be treated as part of words (diacritics)
- **ignorePlurals**: Languages for plural handling

### Multi-language Support
All searchable models include a `translated_names` field that contains all translations, enabling search in any language.

## Commands

### 1. Update Index Settings
After modifying `config/scout.php`, update the Meilisearch index settings:

```bash
# Using Docker
docker compose exec app php artisan search:update-settings

# Without Docker (requires PHP 8.3+)
php artisan search:update-settings
```

**When to use**: After changing index configuration in `config/scout.php`

### 2. Re-index Data
After updating settings, re-index all data:

```bash
# Re-index all models (with flush)
docker compose exec app php artisan search:index --flush

# Re-index specific model
docker compose exec app php artisan search:index Beach --flush
docker compose exec app php artisan search:index Country --flush
```

**When to use**: 
- After running `search:update-settings`
- After database changes to searchable models
- After adding/removing translations

### 3. Import Without Flush
Import data without clearing existing index:

```bash
docker compose exec app php artisan search:index
```

## Common Issues and Solutions

### Issue: Search doesn't find words with diacritics (e.g., "nemčija")
**Solution**: 
1. Run `search:update-settings` to apply `nonSeparatorTokens` configuration
2. Re-index data with `search:index --flush`

### Issue: Search doesn't find translations in specific language
**Solution**:
1. Check that the `Accept-Language` header is set correctly in the request
2. Verify that translations exist in the database
3. Re-index data to ensure `translated_names` is populated

### Issue: Old search results still appear after updating data
**Solution**: Re-index the specific model or all models

## Search Endpoint

```bash
# Search with locale (Slovenian)
curl 'https://dev-api.beachrex.com/api/v1/search?q=italija&limit=10' \
  -H 'accept-language: si'

# Search with locale (English)
curl 'https://dev-api.beachrex.com/api/v1/search?q=italy&limit=10' \
  -H 'accept-language: en'

# Search with diacritics
curl 'https://dev-api.beachrex.com/api/v1/search?q=nemčija&limit=10' \
  -H 'accept-language: si'
```

## Development Workflow

1. **After changing translations**:
   ```bash
   docker compose exec app php artisan search:index Beach
   ```

2. **After changing search configuration**:
   ```bash
   docker compose exec app php artisan search:update-settings
   docker compose exec app php artisan search:index --flush
   ```

3. **After adding new searchable fields**:
   - Update `toSearchableArray()` in the model
   - Update `searchableAttributes` in `config/scout.php`
   - Run both commands above

## Supported Models
- Beach
- City
- Island
- Region
- Country

## Technical Details

### How Multi-language Works
1. Each model's `toSearchableArray()` method collects all translations
2. Translations are joined into a single `translated_names` string
3. Meilisearch indexes this string along with the default (English) fields
4. Search queries match against all languages simultaneously
5. Results are localized based on `Accept-Language` header

### Diacritics Support
The `nonSeparatorTokens` configuration tells Meilisearch to treat characters like `č`, `š`, `ž` as normal letters, not separators. This allows:
- "nemčija" to be searchable
- "Italija" to match regardless of case
- Proper tokenization of Slovenian, Croatian, and German words


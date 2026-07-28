# Fix Duplicate Beach Slugs

## Problem

Multiple beaches can have the same name (e.g., "Beach Solaris" in both Vabriga and Šibenik), which creates duplicate slugs in the database. This causes:

1. **URL conflicts** - Both beaches try to use the same slug
2. **Wrong redirects** - Frontend redirects to wrong beach location
3. **API confusion** - API returns wrong beach for a given slug

## Solution

This solution automatically fixes duplicate slugs by appending city names, making each slug unique while maintaining SEO-friendly URLs.

### Example

**Before:**
- Beach Solaris (Vabriga, Istra) → `beach-solaris`
- Beach Solaris (Šibenik, Dalmatia) → `beach-solaris` ❌ DUPLICATE

**After:**
- Beach Solaris (Vabriga, Istra) → `beach-solaris-vabriga` ✅
- Beach Solaris (Šibenik, Dalmatia) → `beach-solaris-sibenik` ✅

---

## 🚀 Quick Start

### Option 1: One-Line Command (Recommended)

```bash
# Preview changes first (dry run)
./fix-duplicate-slugs.sh --dry-run

# Apply changes
./fix-duplicate-slugs.sh
```

This single command:
- ✅ Runs migration
- ✅ Fixes duplicates in **all 19 languages**
- ✅ Creates redirect records
- ✅ Clears cache

### Option 2: Admin Panel (GUI)

1. Go to **Admin Panel** → **System** → **Fix Duplicate Slugs**
2. Click **"Preview Changes (Dry Run)"** to see what will change
3. Click **"Fix All Duplicates"** to apply changes

### Option 3: Manual Artisan Command

```bash
# Run migration first
php artisan migrate

# Preview changes
php artisan beaches:fix-duplicate-slugs --dry-run

# Fix all languages
php artisan beaches:fix-duplicate-slugs

# Or fix specific language
php artisan beaches:fix-duplicate-slugs --locale=en

# Clear cache
php artisan cache:clear
```

---

## 📋 What Gets Created

### 1. Laravel Command
**File:** `app/Console/Commands/FixDuplicateBeachSlugs.php`

- Finds duplicate slugs across all locales
- Shows preview with `--dry-run`
- Fixes duplicates by appending city name
- Creates redirect records
- Processes all 19 languages by default

### 2. Database Migration
**File:** `database/migrations/2024_12_22_000000_create_beach_slug_redirects_table.php`

Creates `beach_slug_redirects` table:
```sql
- old_slug (e.g., "beach-solaris")
- new_slug (e.g., "beach-solaris-vabriga")
- locale (e.g., "en")
- beach_id
```

### 3. Updated API Controller
**File:** `app/Http/Controllers/Api/V1/BeachDetailsController.php`

- Checks redirect table if beach not found by slug
- Automatically serves correct beach for old URLs
- No breaking changes for frontend

### 4. Admin Panel Page
**Files:**
- `app/Filament/BrAdmin/Pages/FixDuplicateSlugs.php`
- `resources/views/filament/br-admin/pages/fix-duplicate-slugs.blade.php`

GUI interface with:
- Real-time duplicate count
- Preview button (dry run)
- Fix button
- Command output display

### 5. Deployment Script
**File:** `fix-duplicate-slugs.sh`

One-command deployment for production/development

---

## 🔍 How It Works

### 1. Detection
Scans `beach_translations` table for duplicate slugs in each locale:

```sql
SELECT slug, COUNT(*) as count 
FROM beach_translations 
WHERE locale = 'en' 
GROUP BY slug 
HAVING COUNT(*) > 1;
```

### 2. Resolution
For each duplicate:
1. Get beach's city slug from translations
2. Append city slug to beach slug
3. Ensure uniqueness (add number if needed)
4. Update `beach_translations` table
5. Create redirect record in `beach_slug_redirects`

### 3. Redirect Handling
When API receives old slug:
1. Try to find beach by slug
2. If not found, check `beach_slug_redirects` table
3. If redirect exists, use new slug
4. Return beach data

---

## 📊 Supported Languages

Processes all 19 languages automatically:
- `en` - English
- `si` - Slovenian
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

---

## ✅ Testing

### Test New URLs
```bash
# Test new unique slugs
curl -H "Accept-Language: en" \
  "https://dev-api.beachrex.com/api/v1/beach-details/beach-solaris-vabriga"

curl -H "Accept-Language: en" \
  "https://dev-api.beachrex.com/api/v1/beach-details/beach-solaris-sibenik"
```

### Test Old URLs (Redirects)
```bash
# Old slug should still work via redirect table
curl -H "Accept-Language: en" \
  "https://dev-api.beachrex.com/api/v1/beach-details/beach-solaris"
# Returns one of the beaches (probably lower ID)
```

### Test Frontend
Frontend automatically works because it builds URLs from API response data:
```javascript
// API returns: { basic_info: { slug: "beach-solaris-vabriga" }, ... }
// Frontend builds: /istra/vabriga/beach-solaris-vabriga ✅
```

---

## 🔧 Deployment

### Development Server
```bash
./fix-duplicate-slugs.sh --dry-run  # Preview
./fix-duplicate-slugs.sh            # Apply
```

### Production Server
```bash
# SSH to production
ssh user@production-server

# Navigate to project
cd /path/to/beachrex-api

# Run script
./fix-duplicate-slugs.sh --dry-run  # Preview
./fix-duplicate-slugs.sh            # Apply
```

### Via Admin Panel
1. Login to admin panel
2. Navigate to **System** → **Fix Duplicate Slugs**
3. Click **Preview Changes**
4. Review output
5. Click **Fix All Duplicates**

---

## 🛡️ Safety Features

1. **Dry Run Mode** - Preview changes before applying
2. **Redirect Table** - Old URLs continue to work
3. **No Data Loss** - Only slug field is modified
4. **Atomic Updates** - Each slug update is a separate transaction
5. **Locale Isolation** - Each language processed independently

---

## 📝 Example Output

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔍 Fixing duplicate beach slugs
Languages: en, si, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Processing locale: en
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Found 1 duplicate slug(s) for en

  Duplicate slug: beach-solaris (2 beaches)

┌──────────┬───────────────┬────────────────────┬──────────┬─────────────────┐
│ Beach ID │ Name          │ Region             │ City     │ Current Slug    │
├──────────┼───────────────┼────────────────────┼──────────┼─────────────────┤
│ 323      │ Beach Solaris │ Istra              │ Vabriga  │ beach-solaris   │
│ 139      │ Beach Solaris │ Dalmatia - Šibenik │ Šibenik  │ beach-solaris   │
└──────────┴───────────────┴────────────────────┴──────────┴─────────────────┘

  ✓ Updated Beach 323: beach-solaris → beach-solaris-vabriga
  ✓ Updated Beach 139: beach-solaris → beach-solaris-sibenik
  ✓ Fixed 2 slug(s) for en

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ COMPLETE - Fixed 2 duplicate slugs across all languages!
⚠️  Remember to clear API cache: php artisan cache:clear
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## 🔄 Rollback (If Needed)

If you need to rollback:

```bash
# Rollback migration (removes redirect table)
php artisan migrate:rollback

# Manually restore old slugs from backup
# (Make sure to backup beach_translations before running!)
```

---

## 🧪 Testing

### Running Tests

All existing tests should pass without modification. The redirect functionality is backward compatible:

```bash
# Run all tests
php artisan test

# Run beach-related tests
php artisan test --filter=BeachDetailsApiTest
php artisan test --filter=BeachSlugRedirectTest
```

### Test Coverage

**Existing Tests (BeachDetailsApiTest):**
- ✅ Still pass without migration (redirect table check is optional)
- ✅ Test beach details by slug
- ✅ Test beach details by ID
- ✅ Test 404 for nonexistent beaches
- ✅ Test locale support

**New Tests (BeachSlugRedirectTest):**
- ✅ Test new unique slugs work
- ✅ Test old slugs redirect to new slugs
- ✅ Test redirects respect locale
- ✅ Test 404 when no redirect exists

### Backward Compatibility

The controller checks if `beach_slug_redirects` table exists before querying it:

```php
if (Schema::hasTable('beach_slug_redirects')) {
    // Check for redirects
}
```

This means:
- ✅ Tests pass before migration is run
- ✅ Production works before migration is run
- ✅ No breaking changes to existing functionality

---

## 📞 Support

If you encounter issues:
1. Check command output for errors
2. Verify database connection
3. Ensure all migrations are run
4. Check that beaches have cities assigned
5. Review logs in `storage/logs/laravel.log`
6. Run tests to verify functionality: `php artisan test`


# Beach Media Path Fix

## Problem

Beach images were being saved with incorrect paths in the database. The system has two types of beach uploads:

1. **Regular uploads** (via web/admin): Should use `img/beaches/{folder_name}/{filename}`
2. **Mobile app uploads**: Should use `img/beaches_mobile/{folder_name}/{filename}`

The beach importer was hardcoding all paths to `img/beaches/` regardless of the `mobile_upload` flag, causing mobile-uploaded images to have incorrect paths.

### Example

**Incorrect path for mobile upload:**
```
img/beaches/bourcefranc-le-chapus-plage-de-marennes_5/bourcefranc-le-chapus-plage-de-marennes_531-sl1.jpg
```

**Correct path for mobile upload:**
```
img/beaches_mobile/bourcefranc-le-chapus-plage-de-marennes_5/bourcefranc-le-chapus-plage-de-marennes_531-sl1.jpg
```

## Solution

### 1. Fixed the Beach Importer

**File:** `app/Console/Commands/MigrateBeachesFromOldDb.php`

**Changes:**
- Modified `migrateBeach()` method to pass the `mobile_upload` flag to `migrateBeachMedia()`
- Updated `migrateBeachMedia()` signature to accept `$isMobileUpload` parameter
- Changed path building logic to use correct folder based on `mobile_upload` flag:
  ```php
  $baseFolder = $isMobileUpload ? 'beaches_mobile' : 'beaches';
  $filePath = "img/{$baseFolder}/{$folderName}/{$fileName}";
  ```

### 2. Created Fix Command for Existing Data

**File:** `app/Console/Commands/FixBeachMediaPaths.php`

A new Artisan command to fix existing media records with incorrect paths.

## How to Use

### For New Imports

The beach importer (`beaches:migrate-from-old-db`) will now automatically use the correct path based on the `mobile_upload` flag. No additional action needed.

### For Existing Data

#### Step 1: Preview Changes (Dry Run)

See what would be updated without making changes:

```bash
./vendor/bin/sail artisan beaches:fix-media-paths --dry-run
```

#### Step 2: Fix All Beaches

Update all beaches with `mobile_upload = true`:

```bash
./vendor/bin/sail artisan beaches:fix-media-paths
```

#### Step 3: Fix Specific Beach

Update media for a specific beach only:

```bash
./vendor/bin/sail artisan beaches:fix-media-paths --beach-id=123
```

## Command Options

| Option | Description |
|--------|-------------|
| `--dry-run` | Show what would be updated without making changes |
| `--beach-id=ID` | Fix media for a specific beach ID only |

## What the Fix Command Does

1. Finds all beaches with `mobile_upload = true`
2. For each beach, finds all associated media records
3. Checks if the media path starts with `img/beaches/` (incorrect)
4. Updates the path to `img/beaches_mobile/` (correct)
5. Skips media that already has the correct path
6. Provides detailed output and summary

## Example Output

```
🔍 Scanning for beaches with incorrect media paths...

Found 15 beach(es) with mobile_upload = true

Processing Beach ID: 531 - Folder: bourcefranc-le-chapus-plage-de-marennes_5
  ✅ Updated: img/beaches/bourcefranc-le-chapus-plage-de-marennes_5/file1.jpg → img/beaches_mobile/bourcefranc-le-chapus-plage-de-marennes_5/file1.jpg
  ✅ Updated: img/beaches/bourcefranc-le-chapus-plage-de-marennes_5/file2.jpg → img/beaches_mobile/bourcefranc-le-chapus-plage-de-marennes_5/file2.jpg
  Updated 2 media record(s)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Summary:
   Beaches processed: 15
   Media records updated: 45
   Media records skipped: 0

✅ Media paths have been updated successfully!
```

## Database Schema

The `beach` table has a `mobile_upload` boolean column:

```sql
mobile_upload TINYINT(1) DEFAULT 1 COMMENT 'Flag for mobile uploads'
```

- `true` (1): Images uploaded via mobile app → use `img/beaches_mobile/`
- `false` (0): Images uploaded via web/admin → use `img/beaches/`

## Files Modified

1. ✅ `app/Console/Commands/MigrateBeachesFromOldDb.php` - Fixed importer logic
2. ✅ `app/Console/Commands/FixBeachMediaPaths.php` - New command to fix existing data

## Files NOT Modified (Working as Expected)

- `app/Filament/BrAdmin/Resources/BeachResource/RelationManagers/MediaRelationManager.php` - Admin uploads always use `img/beaches/` which is correct
- `app/Models/Media.php` - Uses `file_path` from database directly, no changes needed
- `app/Http/Resources/BeachResource.php` - Returns `file_path` from database, no changes needed

## Testing

After running the fix command, verify:

1. Check a mobile-uploaded beach in the database:
   ```sql
   SELECT b.id, b.folder_name, b.mobile_upload, m.file_path 
   FROM beach b 
   JOIN media m ON m.mediable_id = b.id AND m.mediable_type = 'App\\Models\\Beach'
   WHERE b.mobile_upload = 1 
   LIMIT 5;
   ```

2. Verify the paths now start with `img/beaches_mobile/`

3. Test image URLs in the API response to ensure they resolve correctly

## Rollback

If you need to rollback the changes (not recommended):

```sql
UPDATE media 
SET file_path = REPLACE(file_path, 'img/beaches_mobile/', 'img/beaches/')
WHERE file_path LIKE 'img/beaches_mobile/%';
```

## Future Considerations

- The Filament admin panel will continue to upload new images to `img/beaches/` for all beaches (admin uploads)
- Mobile app uploads should set `mobile_upload = true` and use `img/beaches_mobile/`
- The importer now handles this correctly for future migrations


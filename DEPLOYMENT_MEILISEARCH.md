# Meilisearch Deployment Guide

This guide covers deploying the Meilisearch global search feature to your dev server.

## 📋 Pre-Deployment Checklist

Before pushing to Git, ensure these files are updated:

- ✅ `docker-compose.yml` - Meilisearch service added
- ✅ `.env.example` - Meilisearch configuration variables added
- ✅ Models updated with Searchable trait (Beach, City, Island, Region, Country)
- ✅ `app/Http/Controllers/Api/V1/SearchController.php` - Search API controller
- ✅ `app/Console/Commands/IndexSearchableModels.php` - Indexing command
- ✅ `routes/api.php` - Search route added
- ✅ `config/scout.php` - Scout configuration
- ✅ `phpunit.xml` - Test environment configured

## 🚀 Deployment Steps on Dev Server

### 1. Pull Latest Code

```bash
cd /path/to/your/api
git pull origin main  # or your branch name
```

### 2. Update Environment Variables

Add these variables to your `.env` file on the server:

```bash
# Scout Search Configuration
SCOUT_DRIVER=meilisearch
SCOUT_PREFIX=
SCOUT_QUEUE=false

# Meilisearch Configuration
# ⚠️ IMPORTANT: Use localhost/127.0.0.1 on production server, NOT "meilisearch"
# Local dev (Docker Compose): http://meilisearch:7700
# Production server: http://localhost:7700 or http://127.0.0.1:7700
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=YOUR_SECURE_MASTER_KEY_HERE
MEILISEARCH_NO_ANALYTICS=true
MEILISEARCH_ENV=production
FORWARD_MEILISEARCH_PORT=7700
```

**⚠️ IMPORTANT Configuration Notes:**

1. **MEILISEARCH_HOST** differs between environments:
   - **Local Development (Docker Compose):** `http://meilisearch:7700` (uses Docker service name)
   - **Production Server:** `http://localhost:7700` or `http://127.0.0.1:7700` (uses actual hostname)
   - If Meilisearch is on a separate server: `http://your-meilisearch-server.com:7700`

2. **MEILISEARCH_KEY** must be a secure random string in production:
   ```bash
   # Generate a secure key:
   openssl rand -base64 32
   ```

### 3. Install Composer Dependencies

```bash
./vendor/bin/sail composer install --no-dev --optimize-autoloader
# OR if not using Sail:
composer install --no-dev --optimize-autoloader
```

### 4. Start Meilisearch Container

```bash
./vendor/bin/sail up -d meilisearch
# OR if not using Sail:
docker-compose up -d meilisearch
```

Verify Meilisearch is running:

```bash
./vendor/bin/sail exec meilisearch curl http://localhost:7700/health
# Should return: {"status":"available"}
```

### 5. Clear Application Cache

```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan route:clear
```

### 6. Index All Searchable Data

This is the **most important step** - it populates Meilisearch with your data:

```bash
# Index all models at once
./vendor/bin/sail artisan search:index

# OR index specific models:
./vendor/bin/sail artisan search:index Beach
./vendor/bin/sail artisan search:index City
./vendor/bin/sail artisan search:index Island
./vendor/bin/sail artisan search:index Region
./vendor/bin/sail artisan search:index Country
```

### 7. Verify Search is Working

Test the search endpoint:

```bash
# Test basic search
curl "http://your-dev-server.com/api/v1/search?q=miami"

# Test with filters
curl "http://your-dev-server.com/api/v1/search?q=beach&country_code=US&limit=10"
```

### 8. Restart Application (if needed)

```bash
./vendor/bin/sail restart
```

## 🔄 Ongoing Maintenance

### Re-indexing Data

When you update beach/city/island/region/country data, you need to re-index:

```bash
# Re-index all models (clears existing index first)
./vendor/bin/sail artisan search:index --flush

# Re-index specific model
./vendor/bin/sail artisan search:index Beach --flush
```

### Automatic Indexing

The models are configured to automatically update the search index when:
- A new record is created
- An existing record is updated
- A record is deleted

**However**, only records that meet the `shouldBeSearchable()` criteria are indexed:
- Beaches: `status_id = 1`
- Cities/Islands/Regions: `status_id = 1` AND has active beaches
- Countries: Has active beaches

## 🐛 Troubleshooting

### Search returns empty results

```bash
# 1. Check if Meilisearch is running
./vendor/bin/sail exec meilisearch curl http://localhost:7700/health

# 2. Re-index all data
./vendor/bin/sail artisan search:index --flush
```

### "Connection refused" or "Could not resolve host" errors

**Error:** `cURL error 6: Could not resolve host: meilisearch`

**Solution:** Update `MEILISEARCH_HOST` in `.env`:

```bash
# ❌ WRONG on production server (Docker service name only works in Docker Compose):
MEILISEARCH_HOST=http://meilisearch:7700

# ✅ CORRECT on production server:
MEILISEARCH_HOST=http://localhost:7700
# OR
MEILISEARCH_HOST=http://127.0.0.1:7700
```

**Note:** The hostname `meilisearch` only works in local Docker Compose environments where containers communicate via service names. On production servers, use `localhost` or the actual server IP/hostname.

## 📝 Summary of Commands for Deployment

```bash
# One-time setup after deployment
git pull origin main
./vendor/bin/sail composer install --no-dev --optimize-autoloader
./vendor/bin/sail up -d meilisearch
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan search:index

# Test
curl "http://your-dev-server.com/api/v1/search?q=test"
```

## ✅ Deployment Complete!

Your Meilisearch global search is now live on the dev server.

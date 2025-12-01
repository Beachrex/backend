# Using Remote Meilisearch for Local Development

This guide explains how to configure your local development environment to use the remote Meilisearch server on Hetzner instead of running a local instance.

## Why Use Remote Meilisearch Locally?

✅ **Consistency** - Same search behavior in development and production  
✅ **Shared Data** - All developers use the same indexed data  
✅ **Less Resources** - No need to run Meilisearch locally  
✅ **Faster Setup** - New developers don't need to index data locally  
✅ **Real Testing** - Test against production-like environment  

## Configuration Changes

### 1. Remove Meilisearch from docker-compose.yml

The local Meilisearch service has been removed from `docker-compose.yml`:

**Before:**
```yaml
depends_on:
    - pgsql
    - redis
    - meilisearch  # ← Removed

meilisearch:  # ← Entire service removed
    image: 'getmeili/meilisearch:v1.11'
    ports:
        - '${FORWARD_MEILISEARCH_PORT:-7700}:7700'
    # ... rest of config
```

**After:**
```yaml
depends_on:
    - pgsql
    - redis
    # meilisearch removed
```

### 2. Update .env Configuration

Update your local `.env` file to point to the remote Meilisearch server:

```env
# Meilisearch Configuration (Remote Hetzner Server)
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY_HERE
SCOUT_QUEUE=false
```

**Replace `YOUR_MASTER_KEY_HERE`** with the actual master key from the Hetzner server.

**Note:** `SCOUT_QUEUE=false` for local development ensures immediate indexing. Production should use `SCOUT_QUEUE=true`.

### 3. Restart Docker Containers

```bash
# Stop containers
./vendor/bin/sail down

# Remove old Meilisearch volume (optional, frees up space)
docker volume rm br-api2_sail-meilisearch

# Start containers
./vendor/bin/sail up -d
```

### 4. Clear Laravel Cache

```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan cache:clear
```

### 5. Test Connection

```bash
# Test from Laravel
./vendor/bin/sail artisan tinker

# In tinker:
>>> use MeiliSearch\Client;
>>> $client = new Client(env('MEILISEARCH_HOST'), env('MEILISEARCH_KEY'));
>>> $client->health();
# Should return: ["status" => "available"]
```

## Testing Search Locally

### Test Search API

```bash
# Test search endpoint
curl -H "Accept-Language: en" \
  "http://localhost/api/v1/search?q=miami&limit=5"
```

### Test Indexing (Optional)

If you need to re-index data:

```bash
./vendor/bin/sail artisan search:index
```

**Note:** This will update the shared remote index, affecting all developers and production. Only do this if necessary.

## Environment-Specific Configuration

### Local Development (.env)

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY
SCOUT_QUEUE=false  # Immediate indexing for development
```

### Production (Laravel Cloud)

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY
SCOUT_QUEUE=true  # Queue indexing for better performance
```

## Troubleshooting

### Can't Connect to Remote Meilisearch

**Check network connectivity:**
```bash
curl https://search.beachrex.com/health
# Should return: {"status":"available"}
```

**Check Laravel config:**
```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan tinker
>>> env('MEILISEARCH_HOST')
# Should show: "https://search.beachrex.com"
```

### Search Returns No Results

**Check if indexes exist:**
```bash
curl -H "Authorization: Bearer YOUR_MASTER_KEY" \
  https://search.beachrex.com/indexes
```

**Re-index if needed:**
```bash
./vendor/bin/sail artisan search:index --flush
```

### Slow Search Performance

This is normal when using a remote server. The Hetzner server is in Germany, so there will be some latency.

**Expected response times:**
- Local Meilisearch: 10-50ms
- Remote Meilisearch (Europe): 100-200ms
- Remote Meilisearch (US): 200-400ms

For production, this is acceptable as the API caches search results.

## Reverting to Local Meilisearch (If Needed)

If you need to run Meilisearch locally again:

### 1. Restore docker-compose.yml

Add back the Meilisearch service:

```yaml
meilisearch:
    image: 'getmeili/meilisearch:v1.11'
    ports:
        - '${FORWARD_MEILISEARCH_PORT:-7700}:7700'
    environment:
        MEILI_MASTER_KEY: '${MEILISEARCH_KEY:-masterKey}'
        MEILI_NO_ANALYTICS: '${MEILISEARCH_NO_ANALYTICS:-true}'
        MEILI_ENV: '${MEILISEARCH_ENV:-development}'
    volumes:
        - 'sail-meilisearch:/meili_data'
    networks:
        - sail
    healthcheck:
        test:
            - CMD
            - wget
            - '--no-verbose'
            - '--spider'
            - 'http://localhost:7700/health'
        retries: 3
        timeout: 5s
```

Add to volumes:
```yaml
volumes:
    sail-meilisearch:
        driver: local
```

Add to depends_on:
```yaml
depends_on:
    - pgsql
    - redis
    - meilisearch
```

### 2. Update .env

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey
SCOUT_QUEUE=false
```

### 3. Restart and Index

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan search:index
```

## Best Practices

### 1. Don't Index Frequently

Since you're using a shared remote index:
- ✅ Only index when you add new searchable fields
- ✅ Only index when testing search functionality
- ❌ Don't index on every code change
- ❌ Don't run `search:index` in automated tests

### 2. Use Staging Environment

For testing major search changes:
- Create a separate staging Meilisearch instance
- Test changes there first
- Then apply to production

### 3. Coordinate with Team

If multiple developers are using the same remote Meilisearch:
- Communicate before re-indexing
- Consider using separate indexes per developer (advanced)
- Document any index schema changes

## Security Notes

⚠️ **Master Key Security:**
- Never commit the master key to git
- Store it in `.env` (which is gitignored)
- Share it securely with team members (password manager, encrypted chat)
- Rotate the key if it's ever exposed

⚠️ **Network Security:**
- The remote Meilisearch is protected by SSL (HTTPS)
- Master key is required for all operations
- Firewall only allows ports 22, 80, 443

## Performance Considerations

### Caching

The API caches search results, so remote latency is only felt on:
- First search for a query
- Cache misses
- Cache invalidation

### Indexing

Indexing to remote Meilisearch is slower than local:
- Local: ~1-2 seconds for 4,000 documents
- Remote: ~5-10 seconds for 4,000 documents

This is acceptable since indexing happens infrequently.

## FAQ

**Q: Will this slow down my local development?**  
A: Slightly. Search requests will take 100-200ms instead of 10-50ms. But the API caches results, so you'll only notice it on the first search.

**Q: What if the remote server is down?**  
A: Search functionality will fail. The rest of the API will continue working. Consider adding error handling for search failures.

**Q: Can I use a different Meilisearch instance for development?**  
A: Yes! Just update `MEILISEARCH_HOST` in your `.env` to point to a different server.

**Q: Do I need to index data locally?**  
A: No! The remote server already has all the data indexed. You only need to re-index if you're testing search changes.

**Q: Will my local changes affect production search?**  
A: Only if you run `php artisan search:index`. Normal development (adding/editing beaches, cities, etc.) won't affect search until you explicitly index.

---

**Last Updated:** 2025-12-01  
**Version:** 1.0


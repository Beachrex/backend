# Runbook: performance, caching and bot traffic

Why this exists: incident 2026-10-30 (~14:40 UTC). A scraper loaded beachrex.com pages from ~130 addresses of one
/24; each page's browser code called ~10 API endpoints; the API cached model graphs that cost 0.2–1.4 s of PHP even
on a cache hit, Redis filled up (one `cities` key ~50 MB per language), the PHP-FPM queue saturated and health took
25 s. ADR 2026-10-30 "API performance: layered caching, bounded keys, protected health".

## 1. What is cached where

| Layer | What | Key | Life | Cleared by |
|---|---|---|---|---|
| Cloudflare | website static files (`/_next/static`, images) | URL | Cloudflare default | deploy (new file names) |
| Cloudflare | **not** the API (see §6) | – | – | – |
| Browser | public API GETs | URL + `Accept-Language` | 60 s (`max-age`), `ETag` → 304 | – |
| nginx in `web_<env>` (micro-cache) | public API GETs that send `X-Accel-Expires` (`public.cache:60` routes) | method + host + URI + normalized language | 60 s, stale while refreshing, stale on PHP errors | TTL only (≤ 60 s) |
| Laravel `ApiResponseCache` (Redis) | finished JSON of list endpoints (gzip) | `api:<generation>:<endpoint>:<locale>:<md5 params>` | 6 h lists / 1 h parameterized | any content model saved/deleted; `cache:clear-api`; deploy `cache:clear` |
| Laravel `BeachDetailsCache` (Redis) | `GET /beach-details/{id}` | `beach_details:g<generation>:<locale>:<id>` | 30 days | beach/translation/media saves; `forgetAll` on create/delete/move |
| Laravel other `cacheRemember` | within-radius (1 h), legacy redirects (1 day), weather, show endpoints | per endpoint | per endpoint | per endpoint / deploy |
| Next.js (website) | ISR pages (5 min), fetch data cache | page / fetch | `revalidate` | deploy, `revalidateTag` |

Routes with `public.cache:60`: site-settings, beaches (list, filters, map-bounds, most-visited, footer),
legacy-redirect, continents, countries, regions, islands, cities (lists and single), first-page-content, static-pages,
spatial within-radius. Not cached publicly: anything with `Authorization`, POST, errors, `beach-details` (counts views),
weather, search, AI.

**Admin edits** are visible within about a minute (micro-cache 60 s; the Laravel cache is cleared at once).
After a raw-SQL bulk change (a command that does not use Eloquent), run `php artisan cache:clear-api --type=all`.

## 2. Reading the logs during an incident

Host nginx (`/var/log/nginx/access.log`, format `beachrex_timed`): real visitor IP, host, request, status, bytes,
`rt=` total time, `urt=` upstream time, `us=` upstream status, referer, UA, `ray=` Cloudflare ray, `cc=` country.

Read-only commands (owner, on the VPS):

```bash
# Top client IPs and /24s in the last 20k lines
sudo tail -n 20000 /var/log/nginx/access.log | awk '{print $1}' | sort | uniq -c | sort -rn | head -20
sudo tail -n 20000 /var/log/nginx/access.log | awk '{split($1,a,"."); print a[1]"."a[2]"."a[3]".0/24"}' | sort | uniq -c | sort -rn | head
# Top user agents
sudo tail -n 20000 /var/log/nginx/access.log | awk -F'"' '{print $(NF-1)}' | sort | uniq -c | sort -rn | head
# Slowest requests (rt=) and 429s
sudo tail -n 20000 /var/log/nginx/access.log | grep -o 'rt=[0-9.]* .*' | sort -t= -k2 -rn | head
sudo tail -n 20000 /var/log/nginx/access.log | awk '$7==429' | wc -l
# API side (inside web_prod): micro-cache status per request (cache=HIT|MISS|STALE|UPDATING|BYPASS), times, language
docker logs --since 5m web_prod 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i ~ /^cache=/) print $i}' | sort | uniq -c
docker logs --since 5m web_prod 2>/dev/null | awk '{print $7}' | cut -d'?' -f1 | sort | uniq -c | sort -rn | head -20
# Cache and pool numbers
docker exec $(docker ps -qf name=api_prod | head -1) php artisan ops:perf-report
docker stats --no-stream
```

## 3. Emergency switches

- **Block a network** (fastest, no deploy): Cloudflare → Security → WAF → Custom rules → edit the block rule,
  add the range: `(ip.src in {202.46.62.0/24 <new range>} and not cf.client.bot)` → Block.
- **Challenge everyone except verified bots** for a while: Cloudflare → Security → Settings → "Under Attack Mode"
  (or a custom rule `(not cf.client.bot)` → Managed Challenge on `beachrex.com` only — never on the API hosts, the
  website's server and the mobile app call the API without a browser).
- **Rate limits** (host nginx, `deploy/nginx/beachrex.conf`): API 20 r/s per IP (burst 100) and 60 r/s per /24
  (burst 300); pages 8 r/s per IP (burst 80) and 25 r/s per /24 (burst 250). Our server (2.28.76.142) and private
  networks are exempt. Changing them = PR + deploy (the deploy reloads host nginx).
- **Cache everything for longer** is not a switch: raise `public.cache:<s>` in `routes/api.php` (PR).

## 4. PHP-FPM sizing

Pools (Dockerfile.prod): `www` (port 9000, `PHP_FPM_MAX_CHILDREN`, prod 16, uat/dev 8 via compose) and `health`
(port 9001, 2 workers, only `/api/v1/health`). One worker ≈ 40–80 MB RSS (up to `memory_limit` 512 MB on a bad
request). Rule of thumb on the 4-vCPU / 8 GB VPS: CPU-bound PHP gains nothing beyond ~2× the cores; more workers
only queue work in PostgreSQL. Raise only if `ops:perf-report` shows `max children reached` growing while CPU is
idle. Change per environment with `PHP_FPM_MAX_CHILDREN` in `deploy/docker-compose.vps.yml` (PR) or the env file.

## 5. Load test (never against production)

`scripts/loadtest/api-mix.mjs` replays the incident's endpoint mix and prints p50/p95/p99 per endpoint and the
micro-cache hit rate. Local setup used for the ADR numbers: PHP-FPM (pool like Dockerfile.prod), nginx with
`deploy/nginx/api.prod.conf` (upstreams pointed at 127.0.0.1), PostGIS, Redis, a copy-like database
(3 800 beaches, 41 822 media, 19 languages). Export the fixture from the database you test:

```bash
psql -Atc "select json_build_object('beaches',(select json_agg(json_build_array(id, latitude::float, longitude::float, country_code)) from beach),'countries',(select json_agg(distinct country_code) from beach),'regions',(select json_agg(code) from region),'cities',(select json_agg(code) from city))" > fixture.json
node scripts/loadtest/api-mix.mjs --base http://127.0.0.1:8090 --fixture fixture.json --duration 60 --concurrency 32
node scripts/loadtest/api-mix.mjs --base http://127.0.0.1:8090 --fixture fixture.json --duration 30 --rate 300 --mix reference
```

## 6. Cloudflare (Free plan) — why the API is not cached there

Cloudflare's cache key cannot include `Accept-Language` on the Free plan and Cloudflare ignores `Vary` for JSON, so a
cached API answer could reach a visitor in another language. The origin micro-cache gives the same protection
without that risk. Do **not** add a Cache Rule for `api.beachrex.com`.

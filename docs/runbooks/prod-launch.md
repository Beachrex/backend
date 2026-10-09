# Runbook — Production launch (beachrex.com on the VPS)

**Status:** Planned. Owner-run, with the agent preparing PRs and checks. Reviewed against the code on 2026-10-08 (TASK-004): env list, footer pages, www → apex, SEO/visibility, old URLs and consent checks added.
**Decision:** the prod DB is created from **UAT**. There is no Laravel Cloud anymore
(ADR "Production Database Is Created from UAT", `docs/ai/implementation/DECISIONS.md`).

Today:
- `beachrex.com` serves the old site from another host.
- `api.beachrex.com` and `beachrex.com` have HTTP-only placeholder blocks in `deploy/nginx/beachrex.conf`.
- `dock.beachrex.com` already has SSL.
- `beachrex_prod` exists but is empty.
- Prod containers: `api_prod`, `web_prod`, `front_prod`, `scheduler_prod` (deployed from `main`).

Steps are in order. Each has a **check** to pass before you continue.

---

## Phase A — Preparation (no user impact, can be done days before)

| # | Who | Step | Check |
|---|---|---|---|
| A1 | Owner | At the DNS provider, lower the TTL of `beachrex.com`, `www`, `api` to 300 s (24 h before the switch) | `dig +noall +answer beachrex.com` shows TTL ≤ 300 |
| A2 | Owner | Create/complete `/opt/beachrex/envs/.env.prod` and `.env.front.prod` (list below) | Every variable name in the list is present |
| A3 | Owner | Google Cloud Console → OAuth client: add `https://api.beachrex.com/api/v1/auth/google/callback` to the redirect URIs and `https://beachrex.com` to the JavaScript origins | Saved |
| A4 | Owner | **Decision:** Stripe live or test keys at launch? Live needs the live webhook endpoint `https://api.beachrex.com/api/v1/payments/stripe/webhook` and its signing secret | Decision written in HANDOFF |
| A5 | Agent | Open the promotion PR `uat → main` (only on the owner's request) | CI green |

**How to build `.env.prod` (reviewed 2026-10-09):** start from a copy of the working `/opt/beachrex/envs/.env.uat`
(same server, same PostgreSQL, Redis and Meilisearch), then change the values listed below. Compose sets only
`DB_DATABASE=beachrex_prod`, `REDIS_DB=0` and `APP_ENV=production` for prod; everything else comes from the file.
The code defaults are for local development (`DB_CONNECTION` defaults to `sqlite`, `DB_HOST`/`REDIS_HOST` to
`127.0.0.1`, `SCOUT_DRIVER` to `collection`, `CACHE_STORE` to `database`), so a file written from scratch without
them would not reach the database and search would silently do nothing (`search:index` runs with `|| true`).

Must be present (same values as UAT unless stated):
- `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_PORT=5432`, `DB_USERNAME`, `DB_PASSWORD`.
- `REDIS_CLIENT`, `REDIS_HOST=redis`, `REDIS_PORT`, `REDIS_PASSWORD` (if set on UAT).
- `CACHE_STORE=redis`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, `SCOUT_DRIVER=meilisearch`.
- `APP_NAME`, `AI_PROVIDER`, `L5_SWAGGER_CONST_HOST=https://api.beachrex.com`.

Change for prod:
- `APP_KEY`: generate a new key for prod, don't copy UAT's.
- `APP_URL=https://api.beachrex.com`, `FILAMENT_DOMAIN=dock.beachrex.com`.
- `SESSION_COOKIE=beachrex_prod_session` (each environment its own cookie name; dev/uat/prod admin share `.beachrex.com`).
- `SCOUT_PREFIX=prod_`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY`.
- `OPENAI_API_KEY`, `OPENWEATHER_API_KEY`.
- `STRIPE_ENABLED`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, and `STRIPE_PRODUCT_ITINERARY_{3DAY,7DAY,14DAY}_PRICE_ID`. Live mode needs new live price IDs.
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `FRONTEND_URL=https://beachrex.com`.
- `DO_SPACES_*`, plus the `BACKUP_*` variables (`docs/runbooks/restore-backup.md`) with `BACKUP_ENABLED=true`.

Also in `.env.prod` (checked against `.env.example` on 2026-10-08, TASK-004):
- `APP_ENV=production`, `APP_DEBUG=false` (never `true` on prod: it shows stack traces), `LOG_LEVEL=warning` or `error`.
- `SESSION_DOMAIN=.beachrex.com`, `SESSION_SECURE_COOKIE=true` (the code reads this name; `SESSION_SECURE` does nothing), `SANCTUM_STATEFUL_DOMAINS=beachrex.com,www.beachrex.com,dock.beachrex.com`.
- `OPENAI_MODEL=gpt-4o-mini`; feature flags `AI_ENABLED`, `AI_PAYMENTS_ENABLED` (Stripe live at launch, owner 2026-10-08), `AI_FREE_TIER_ENABLED`, `AI_FREE_TIER_DAILY_LIMIT`. (`GOOGLE_AUTH_ENABLED` is not read by the code; Google sign-in works when `GOOGLE_CLIENT_ID`/`SECRET` are set.)
- `OPEN_METEO_API_KEY`: the commercial Open-Meteo plan (the free endpoint is non-commercial only).
- `MAIL_*` (mailer, host, port, username, password, `MAIL_FROM_ADDRESS=info@beachrex.com`, `MAIL_FROM_NAME=Beachrex`) if the site sends e-mail.

**After any later edit of `.env.prod`** (e.g. `BACKUP_ENABLED` in D5): the deploy caches the config, so the change only applies after a prod redeploy (or `php artisan config:cache` in `api_prod` and `scheduler_prod`).

`.env.front.prod`:
- `NEXT_PUBLIC_API_ENDPOINT=https://api.beachrex.com`
- `NEXT_PUBLIC_BASE_URL=https://beachrex.com`. This exact host makes the site indexable (`isProductionSite()`).
- `NEXT_PUBLIC_CDN_TEASER`, `NEXT_PUBLIC_CDN_LARGE`, `NEXT_PUBLIC_CDN_ASSETS`: same values as UAT.

## Phase B — Load the prod database from UAT (no user impact)

> Tell admin editors to **stop editing on UAT** from here until launch. Later UAT edits won't reach prod.

```bash
cd /opt/beachrex/deploy
DC="docker compose -f docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.prod"

# B1. Dump UAT (custom format) inside the pgsql container
$DC exec -T pgsql sh -c 'pg_dump -U "$POSTGRES_USER" -Fc --no-owner --no-privileges beachrex_uat > /tmp/uat.dump && ls -lh /tmp/uat.dump'

# B2. Confirm beachrex_prod is empty. Expect: "relation beach does not exist" or 0
$DC exec -T pgsql sh -c 'psql -U "$POSTGRES_USER" -d beachrex_prod -c "select count(*) from beach"'

# B3. Restore into beachrex_prod (PostGIS first)
$DC exec -T pgsql sh -c 'psql -U "$POSTGRES_USER" -d beachrex_prod -c "CREATE EXTENSION IF NOT EXISTS postgis" \
  && pg_restore -U "$POSTGRES_USER" -d beachrex_prod --no-owner --no-privileges /tmp/uat.dump'

# B4. Verify: counts must match UAT
for db in beachrex_uat beachrex_prod; do
  $DC exec -T pgsql sh -c "psql -U \"\$POSTGRES_USER\" -d $db -tAc \"select '$db', (select count(*) from beach), (select count(*) from media), (select count(*) from users)\""
done
```

**B5. Owner decision: clean UAT test data in prod.** Review first, then run only what you approve:
```sql
-- review (run in beachrex_prod)
select id, email, is_admin, created_at from users order by id;
select count(*) from ai_sessions;   select count(*) from purchases;   select count(*) from newsletter_subscribers;
-- typical cleanup (only after review):
-- delete from personal_access_tokens;                 -- all API tokens issued on UAT
-- truncate ai_messages, ai_usage, ai_sessions cascade;
-- truncate purchases, user_entitlements cascade;      -- UAT used Stripe test mode
-- delete from users where is_admin = false;           -- keep admins only
```
Then `$DC exec -T pgsql rm /tmp/uat.dump`.

**B6. Footer pages and caches** (after C1, once the prod code is deployed). The UAT copy may already hold them; the command is idempotent and shows what it would change:
```bash
$DC exec -T api_prod php artisan static-pages:sync --dry-run
$DC exec -T api_prod php artisan static-pages:sync
$DC exec -T api_prod php artisan cache:clear
```

## Phase C — Deploy prod code (no user impact, DNS still points elsewhere)

| # | Who | Step | Check |
|---|---|---|---|
| C1 | Owner/Agent | Merge the `uat → main` PR. The deploy builds `api_prod`, `web_prod`, `scheduler_prod`, runs migrations (no-op after the restore) and `search:index` (`prod_` indexes) | Deploy run for the merge SHA green |
| C2 | Owner | Check the API on the server before DNS: `curl -s -H "Host: api.beachrex.com" http://127.0.0.1/api/v1/health` | `"status":"ok"`, `revision` = merge SHA |
| C3 | Owner | Frontend: the `main` push of `web-client` (via a `uat → main` PR) builds `front_prod` with `.env.front.prod` | `curl -s -H "Host: beachrex.com" http://127.0.0.1/en \| grep -o "<title>[^<]*"` |
| C4 | Owner | Search on prod: `curl -s -H "Host: api.beachrex.com" "http://127.0.0.1/api/v1/search?q=zlatni"` | Results contain beaches (Meilisearch reachable, `prod_` indexes filled; the deploy's `search:index` hides failures) |

> **Before Phase D:** the D3 nginx PR must be ready and approved (the agent opens it before the switch day).
> Between D1 and the D3 deploy, `api.beachrex.com` and `beachrex.com` have no HTTPS block, so the site is down for
> that window. Do D1 → D2 → merge D3 → C3 again (rebuild `front_prod` once the API answers on HTTPS) back to back.

## Phase D — DNS switch and SSL (short user-visible switch)

| # | Who | Step | Check |
|---|---|---|---|
| D1 | Owner | DNS: A records `beachrex.com`, `www.beachrex.com`, `api.beachrex.com` → `2.28.76.142` | `dig +short beachrex.com` = `2.28.76.142` (wait for TTL) |
| D2 | Owner | Issue certificates **without letting certbot edit nginx** (the repo owns the config): `sudo certbot certonly --nginx -d beachrex.com -d www.beachrex.com -d api.beachrex.com` | `sudo ls /etc/letsencrypt/live/beachrex.com/` shows `fullchain.pem` |
| D3 | Agent | PR: replace the HTTP-only prod blocks in `deploy/nginx/beachrex.conf` with 80→301 + 443 blocks using `/etc/letsencrypt/live/beachrex.com/` (template below). `www.beachrex.com` gets its own 443 block that 301s to `https://beachrex.com$request_uri` (one canonical host; old links to `www` keep working). Merge **only after D2**, otherwise `nginx reload` fails for all environments | Deploy green; `curl -sI https://www.beachrex.com/en` → `301` to `https://beachrex.com/en` |
| D3b | Owner/Agent | Re-run the web-client prod deploy (Actions → Deploy → `main`, "Run workflow") so the frontend build and its static sitemaps (`/sitemap/countries.xml` is cached for 24 h) are made against the live HTTPS API | Deploy green; `curl -s https://beachrex.com/sitemap/countries.xml \| grep -c "<url>"` > 0 |
| D4 | Owner | `https://beachrex.com/en`, `https://api.beachrex.com/api/v1/health`, `https://dock.beachrex.com` | Pages load; health `ok`; `curl -sI https://beachrex.com/en \| grep -i x-robots-tag` is **empty** (prod is indexable) |
| D5 | Owner | Stripe live webhook (if live), UptimeRobot monitor for `https://api.beachrex.com/api/v1/health`, enable backups on prod (`BACKUP_ENABLED=true` in `.env.prod`), then **turn them off on UAT** (`BACKUP_ENABLED=false` in `.env.uat` + redeploy uat) | First prod backup appears next night; `uat-api…/api/v1/health` shows `backup.enabled: false` |
| D5b | Agent + Owner | **MCP becomes production-only** (owner decision 2026-10-07). The agent adds an Nginx route `mcp.beachrex.com` → `127.0.0.1:3102` (prod process `beachrex-mcp-prod`, API `https://api.beachrex.com`, links to `https://beachrex.com`). The owner adds DNS and the certificate (`certbot certonly`) and switches the Claude connector to `https://mcp.beachrex.com/mcp`. Then the dev MCP route (`mcp-dev.beachrex.com`) is removed or kept for testing only (owner's choice) | In Claude, beach links point to `beachrex.com` |
| D6 | Owner | Google Search Console: add the `beachrex.com` property and submit `https://beachrex.com/sitemap.xml` | Sitemap accepted |
| D7 | Owner | **Visibility and SEO checks** (TASK-004). Run each `curl` and compare: `curl -s https://beachrex.com/robots.txt` · `curl -s https://beachrex.com/sitemap.xml \| head -3` · `curl -s https://beachrex.com/ads.txt` · `curl -s -o /dev/null -w "%{http_code}" https://beachrex.com/llms.txt` · `curl -sI https://api.beachrex.com/api/v1/health \| grep -i x-robots-tag` · `curl -s https://dev.beachrex.com/robots.txt` · `curl -s https://uat.beachrex.com/robots.txt` | prod robots: `Allow: /`, AI training bots `Disallow: /`, `Sitemap:` line; sitemap starts with `<sitemapindex`; ads.txt has `pub-5133469552990533`; llms.txt `200`; the API has `X-Robots-Tag: noindex…`; dev and uat robots: `Disallow: /` only |
| D8 | Owner | **Old URLs redirect** (Google Maps links). Open 5–10 old beach URLs (e.g. `https://www.beachrex.com/hr/hrvatska/dalmacija/tisno-plaze/plaza-tisno`) | Each ends on the same beach (308/301 chain), not on the home page or a 404 |
| D9 | Owner | **Consent and analytics.** CookieYes dashboard: domain `beachrex.com`, "Google Consent Mode" on. Open `https://beachrex.com/en` in a private window | The banner shows; before consent no `_ga` cookie; after "Accept" GA Realtime shows the visit; the cookie policy page shows the CookieYes table |
| D10 | Owner | Google Search Console: request indexing of the home page; AdSense: add the site `beachrex.com` and wait for the review | Search Console sees the sitemap; AdSense "Ready" |

Template for D3 (443 block; the 80 block becomes `return 301 https://$host$request_uri;`):
```nginx
server {
    listen 443 ssl http2;
    server_name www.beachrex.com;
    ssl_certificate /etc/letsencrypt/live/beachrex.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/beachrex.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    return 301 https://beachrex.com$request_uri;
}
server {
    listen 443 ssl http2;
    server_name beachrex.com;
    ssl_certificate /etc/letsencrypt/live/beachrex.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/beachrex.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    location / { proxy_pass http://beachrex_front_prod; ... same headers as uat ... }
}
# same for api.beachrex.com → beachrex_api_prod (client_max_body_size 25M)
```

## Rollback

- **Before D1:** nothing is public. Fix and repeat.
- **After D1:** point the DNS A records back to the old host. With TTL 300 the old site is back within about 5 minutes.
- **Data:** `beachrex_prod` can be dropped and reloaded from UAT (Phase B) at any time before real users write to it.

## Go / no-go checklist (owner, launch day)

Go only when every line is true:
- [ ] `.env.prod` built from `.env.uat` with the changes above (new `APP_KEY`, `SESSION_SECURE_COOKIE=true`, `SESSION_COOKIE`, URLs, `SCOUT_PREFIX=prod_`, Stripe live keys and price IDs, `OPEN_METEO_API_KEY`, `BACKUP_*`); `.env.front.prod` with `NEXT_PUBLIC_BASE_URL=https://beachrex.com`.
- [ ] B4 counts match UAT; B5 cleanup done (UAT tokens deleted, admin passwords rotated).
- [ ] C2 health `ok` with the merge SHA; C4 search finds a beach.
- [ ] D3 nginx PR approved and ready before D1.
- [ ] Old URLs tested on dev (HANDOFF owner actions) — no surprises expected at D8.
- [ ] CookieYes: domain, Google Consent Mode and IAB TCF v2.2 (needed for AdSense in the EEA) enabled.
- [ ] Legal texts reviewed (privacy, cookies, terms).
- [ ] Rollback understood (DNS back to the old host, TTL 300).


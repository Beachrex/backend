# Runbook — Production launch (beachrex.com on the VPS)

**Status:** Planned. Owner-run, with the agent preparing PRs and checks.
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

`.env.prod` variable names (values only on the VPS, never in git or chat):
- `APP_KEY`: generate a new key for prod, don't copy UAT's.
- `APP_URL=https://api.beachrex.com`, `FILAMENT_DOMAIN=dock.beachrex.com`.
- `DB_USERNAME`, `DB_PASSWORD`. The database is `beachrex_prod` and `REDIS_DB=0`; both are set by compose.
- `SCOUT_PREFIX=prod_`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY`.
- `OPENAI_API_KEY`, `OPENWEATHER_API_KEY`.
- `STRIPE_ENABLED`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, and `STRIPE_PRODUCT_ITINERARY_{3DAY,7DAY,14DAY}_PRICE_ID`. Live mode needs new live price IDs.
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `FRONTEND_URL=https://beachrex.com`.
- `DO_SPACES_*`, plus the `BACKUP_*` variables (`docs/runbooks/restore-backup.md`) with `BACKUP_ENABLED=true`.

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

## Phase C — Deploy prod code (no user impact, DNS still points elsewhere)

| # | Who | Step | Check |
|---|---|---|---|
| C1 | Owner/Agent | Merge the `uat → main` PR. The deploy builds `api_prod`, `web_prod`, `scheduler_prod`, runs migrations (no-op after the restore) and `search:index` (`prod_` indexes) | Deploy run for the merge SHA green |
| C2 | Owner | Check the API on the server before DNS: `curl -s -H "Host: api.beachrex.com" http://127.0.0.1/api/v1/health` | `"status":"ok"`, `revision` = merge SHA |
| C3 | Owner | Frontend: the `main` push of `web-client` (via a `uat → main` PR) builds `front_prod` with `.env.front.prod` | `curl -s -H "Host: beachrex.com" http://127.0.0.1/en \| grep -o "<title>[^<]*"` |

## Phase D — DNS switch and SSL (short user-visible switch)

| # | Who | Step | Check |
|---|---|---|---|
| D1 | Owner | DNS: A records `beachrex.com`, `www.beachrex.com`, `api.beachrex.com` → `2.28.76.142` | `dig +short beachrex.com` = `2.28.76.142` (wait for TTL) |
| D2 | Owner | Issue certificates **without letting certbot edit nginx** (the repo owns the config): `sudo certbot certonly --nginx -d beachrex.com -d www.beachrex.com -d api.beachrex.com` | `sudo ls /etc/letsencrypt/live/beachrex.com/` shows `fullchain.pem` |
| D3 | Agent | PR: replace the HTTP-only prod blocks in `deploy/nginx/beachrex.conf` with 80→301 + 443 blocks using `/etc/letsencrypt/live/beachrex.com/` (template below). Merge **only after D2**, otherwise `nginx reload` fails for all environments | Deploy green |
| D4 | Owner | `https://beachrex.com/en`, `https://api.beachrex.com/api/v1/health`, `https://dock.beachrex.com` | Pages load; health `ok`; `curl -sI https://beachrex.com/en \| grep -i x-robots-tag` is **empty** (prod is indexable) |
| D5 | Owner | Stripe live webhook (if live), UptimeRobot monitor for `https://api.beachrex.com/api/v1/health`, enable backups on prod (`BACKUP_ENABLED=true` in `.env.prod`), then **turn them off on UAT** (`BACKUP_ENABLED=false` in `.env.uat` + redeploy uat) | First prod backup appears next night; `uat-api…/api/v1/health` shows `backup.enabled: false` |
| D6 | Owner | Google Search Console: add the `beachrex.com` property and submit `https://beachrex.com/sitemap.xml` | Sitemap accepted |

Template for D3 (443 block; the 80 block becomes `return 301 https://$host$request_uri;`):
```nginx
server {
    listen 443 ssl;
    server_name beachrex.com www.beachrex.com;
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

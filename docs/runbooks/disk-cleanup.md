# Runbook: disk cleanup after deploys (old Docker images, build cache)

Status: **Built** (ADR 2026-10-30 "Deploys prune old Docker images"). Owner 2026-10-30: the VPS disk (75 GB,
Hetzner) was 71 % full; "da deploy samodejno čisti stare Docker slike — ja".

Every deploy builds a new image (`beachrex/api:<env>`, `beachrex/front:<env>`). The old one lost its tag and stayed
on disk as a dangling `<none>` image, and BuildKit cache grew with every build.

## What runs

`deploy/scripts/docker-prune.sh <env> <api|front>` runs as the step **"Clean up old Docker images and build cache"**
at the end of every successful deploy, in both workflows (backend: after "Reload Nginx"; web-client: after
"Reload Nginx", only if the script is on the server). It covers the images of **all** environments with one rule.
The step has `continue-on-error`, the script always exits 0: the cleanup never fails a deploy.

### Retention rule

Per compose service that builds an image (`api_<env>`, `front_<env>`):

| Image | What happens |
|---|---|
| current (`beachrex/api:<env>`, `beachrex/front:<env>`) | kept |
| previous: the image that was live before the build — the build step tags it `<tag>-previous` (e.g. `beachrex/front:prod-previous`) just before `docker compose build`; without that tag, the newest other image of the service | kept |
| any image a container uses (running or stopped: old API replica, scheduler/worker before their recreate, stopped `front_<env>_green`) | kept ("a container uses it" in the log) |
| everything older | `docker image rm <id>` (no `-f`: Docker refuses anything in use or multi-tagged; that is logged, never forced) |

"Our" images are found by the label `com.docker.compose.project=deploy` that `docker compose build` puts on every
image it builds (the compose file is in `/opt/beachrex/deploy`), or by the name `beachrex/*`. Base images (php, node,
postgis, redis, nginx), other projects' images, **volumes and networks are never touched**; there is no
`docker system prune`. Dangling images *without* our label (built by an older compose that did not label images, or
by someone else) are only counted in the notice.

BuildKit cache: `docker builder prune -f --filter until=72h`, then capped at 8 GB
(`--max-used-space`, or `--keep-storage` on an older Docker; least recently used entries go first).

### Not at the same time as a build

The API and the website deploy are in different repos, so they can overlap. Both build steps
(`api-rollout.sh`, `front-deploy.sh`) hold `/opt/beachrex/.docker-build.lock` **shared** while they build; the
cleanup takes it **exclusively** (waits up to 10 min, otherwise it skips and the next deploy cleans up). A build
waits at most 10 min for the lock and then builds anyway.

## Reading the result

The step prints notices (readable through the check-run annotations API; GitHub keeps 4096 characters per
annotation, so long ones come in parts): **"Disk cleanup (<api|front> <env>) (part n)"** with `df -h /` and
`docker system df` before and after, "Freed on /: …" and what was kept per service; then
**"Disk report (<api|front> <env>) (part n)"** with other disk users (nothing in the report is deleted; small
items are left out):

- container logs per container with its json-file limit (`10m x 5` for every app service incl. the green ones;
  `pgsql`/`redis` have no compose logging section on purpose (it would recreate the shared database), the VPS
  Docker daemon default `10m x 3` applies to them — seen in the first run; `NO-LIMIT` would mean no limit at all);
- Laravel `storage/logs` per environment (if it is large: `LOG_STACK=daily` keeps 14 days, `LOG_DAILY_DAYS`);
- `*.dump` files in `/root` and `/tmp` from manual backups, and `/tmp/*.dump` inside the pgsql container
  (the prod-launch runbook leaves `/tmp/uat.dump` there unless removed) — **the owner decides and deletes by hand**;
- container writable layers (Next.js `.next/cache` lives there; it is gone when the container is recreated);
- volume sizes, `/opt/beachrex/api_*`, `front_*`, MCP `node_modules`, `~/.npm`, journald.

## First run (dev, 2026-10-30, run 41036884533)

Before: `/` 56 GB of 75 GB used (78 %); Build Cache 20.75 GB (176 entries), images 6.7 GB, volumes 5.3 GB (pgsql 5.0 GB).
No old images of our services were left (all six services had only their current image): the VPS Docker uses the
containerd image store, where an image that loses its tag and is not in use is gone at once — so images never piled
up, and a "previous" image only survives because the build step now tags it before building (follow-up PR).
Build cache: 2.1 GB
unused > 72 h + 6.6 GB above the 8 GB cap removed (~8.7 GB). The "After" part was cut by the 4096-character limit
(fixed in the next PR).

Found, not deleted:
- **`front_prod` writable layer 14.3 GB** (the Next.js cache in the container: ISR pages / fetch cache; the
  site went live 2026-10-29). Root cause and fix: section "Website cache" below.
- Five manual DB dumps in `/root` (`*_dev-before-ascii-20261008-0825.dump`, `*_uat-before-promote-20261008-0906.dump`,
  `*_uat-before-promote-20261009.dump`, `*_prod-before-copy-20261029-1113.dump`, `*_uat-before-copy-20261029-1113.dump`),
  ~337–339 MB each, ~1.7 GB together: the owner decides.
- Container logs all small (largest `web_prod` 7 MB, `api_prod` 6 MB; every container has a size limit, pgsql/redis
  `10m x 3` from the daemon default). Laravel `laravel.log` 1–3 MB per env.

## Website cache: `front_prod` 16 GB in a day (root cause and fix, 2026-10-30)

ADR 2026-10-30 "Bounded Next.js cache". Measured on prod by the owner (13:56, container up since the 2026-10-29 21:33
deploy; the writable layer survived the 09:22 reboot because a reboot restarts, not recreates, the container):
writable layer **16.3 GB** (dev/uat 32 MB); `.next/server/app` 13.9 GB in 178 057 files; `.next/cache` 1.2 GB
(`fetch-cache` 139 955 files). Newest files: scanner paths stored as pages, e.g. `api/proc/self/environ.{html,rsc,meta}`,
`api/public/.env.*`, `api/..;/.env.*`, `api/graphql.*`, `api/auth/session.*`.

**Why.** Next.js 14 (`output: standalone`) keeps its ISR/data cache inside the container by default and never deletes:
- every ISR render is written as `.html` + `.rsc` + `.meta` (beach page ~260 KB per language, 404 page ~125 KB),
  **including 404 and redirect renders**; every cached `fetch` is one file in `.next/cache/fetch-cache`;
- the middleware matcher skipped all paths starting with `api`, so `/api/<anything>` rendered as locale `api` through
  `[locale]/[...rest]` (ISR, 1 day), which also called the legacy-redirect API with the path → one page and one fetch
  file per invented URL. `/.env`, `/wp-login.php` etc. were redirected to `/en/...` and cached the same way;
- 178 057 files / 3 ≈ 59 000 pages × ~235 KB ≈ 13.9 GB: crawlers and scanners in the first day.

Nothing secret was served: those URLs rendered the normal 404 page (checked locally: `/api/.env`,
`/api/proc/self/environ`, `/api/..;/.env` → 404 with the site's 404 HTML, no environment values).

**Why the earlier fixes did not catch it.** The 2026-10-26 slowdown fix bounded *memory* (slug lists); the image and
build-cache cleanup covers images and BuildKit, not container layers. Dev/uat get little traffic and are recreated by
frequent deploys (32 MB), so it showed only on prod after launch, sitemap submission and the first scanners.

**Fix (web-client).**
- `cache-handler.cjs`: pages, route handlers and fetch responses in one gzip-compressed in-memory LRU with a byte
  budget (`NEXT_CACHE_MAX_MB`: prod 256, uat/dev 64, set in `docker-compose.vps.yml`); nothing is written to disk;
  404/redirect renders are not stored.
- Middleware: scanner URLs (`src/lib/utils/junk-paths.ts`) get a 9-byte 404 before anything renders; the matcher
  skips only `api/health`. Unknown locales → 404 in `[locale]/layout`; `[locale]/[...rest]` is `force-dynamic`.
- Safety net: `/app/.next/cache` is a 256 MB tmpfs in every `front_*` service.

Local proof (production build + standalone server + mock API; growth of `.next/cache` + `.next/server/app`):

| Requests | Before | After |
|---|---|---|
| 50 beaches × 19 languages | +247 MB: 950 pages, 2 019 fetch files | 0 |
| same again (served from cache) | 0 | 0 |
| 200 query-string variants of one page | 0 | 0 |
| 200 unknown beach slugs (404) | +24 MB, 200 pages | 0 |
| 200 unknown `[...rest]` paths (404) | +24 MB, 200 pages, 200 fetch files | 0 |
| 100 wrong-parent beach URLs (redirect) | +12 MB, 100 pages | 0 |
| 480 scanner probes (`/api/.env`, `/api/proc/self/environ`, ...) | ~125 KB each (as the 404s above) | 0, 9-byte 404 in 2 ms |

TTFB (avg of 950 requests): first render 64 → 67 ms, from cache 15.3 → 15.9 ms. Memory: cache 63.5 MB for ~1 000 pages
plus their API responses (budget 128 MB); RSS ~1.1–1.5 GB under the crawl with a 1 MB or a 128 MB budget alike
(Next's render heap, not the cache).

**What to watch.** `https://beachrex.com/api/health` → `cache.mb` stays ≤ `cache.maxMb`; `evictions` rising fast with
low `hits` means the budget is small for the traffic (raise `NEXT_CACHE_MAX_MB` in the compose file; mind the 8 GB
VPS). The deploy "Disk report" lists each website container's writable layer, `.next/cache`, `.next/server/app` and
these numbers, with a WARNING when a layer reaches GBs. The current 16 GB on prod go away with the next prod website
deploy (the container is recreated); until then they stay.

**Cloudflare (owner, optional; stops scanner traffic before it reaches the VPS).** WAF → Custom rules → Block (or
Managed Challenge) on host `beachrex.com` / `www.beachrex.com` (never `api.beachrex.com`) when the lower-cased URI path
- starts with `/api/` and is not `/api/health`;
- contains `/.env`, `/.git`, `/.aws`, `/.ssh`, `..;`, `/proc/self`, `/etc/passwd`, `wp-admin`, `wp-login`,
  `wp-content`, `wp-includes`, `xmlrpc.php`, `phpmyadmin`, `cgi-bin`, `/actuator`;
- ends with `.php`, `.asp`, `.aspx`, `.jsp`, `.cgi`, `.env`, `.bak`, `.sql`, `.yml`, `.ini`, `.log`.

Also worth considering: Security → Bots → "Block AI Scrapers and Crawlers" (if AI crawlers are not wanted) and the free
Cloudflare managed ruleset. HTML is not edge-cached today (Cloudflare does not cache HTML by default; pages send
`s-maxage=300, stale-while-revalidate`); a "Cache Everything" rule for public pages (excluding `/api/*`, `/*/ai*`,
`/*/favorites*`, `/*/auth/*`, `/*/payment/*`) would take most page traffic off the server — a separate decision.

## Quick rollback to the previous image (owner, on the VPS)

Normal rollback is still revert + deploy (zero-downtime, runbook `zero-downtime-deploys.md`). If a deploy broke the
website and a rebuild would take too long, the previous image is on disk:

```bash
DC="docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.prod --env-file /opt/beachrex/envs/.env.front.prod"
docker tag beachrex/front:prod-previous beachrex/front:prod
$DC up -d --no-deps front_prod_green && curl -s http://127.0.0.1:3011/api/health   # wait for 200
echo 'set $beachrex_front_pool beachrex_front_prod_green;' > /opt/beachrex/nginx-state/front_prod.conf
sudo nginx -t && sudo systemctl reload nginx
$DC up -d --no-deps --force-recreate front_prod                                       # blue on the old image
rm /opt/beachrex/nginx-state/front_prod.conf && sudo systemctl reload nginx && $DC stop front_prod_green
```

API: `docker tag beachrex/api:prod-previous beachrex/api:prod`, then the same steps as `api-rollout.sh` by hand is
risky (migrations already ran) — prefer revert + deploy. The next deploy builds a new image again either way.

## Run it by hand / dry run

```bash
BEACHREX_PRUNE_DRY_RUN=1 bash /opt/beachrex/deploy/scripts/docker-prune.sh dev api   # shows what it would do
```

Knobs (environment): `BEACHREX_BUILD_CACHE_UNTIL` (72h), `BEACHREX_BUILD_CACHE_MAX` (8GB),
`BEACHREX_PRUNE_LOCK_WAIT_S` (600).

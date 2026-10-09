# Handoff — state as of 2026-10-09 (end of session 8)

> **Two agent roles** ([`AGENT-ROLES.md`](AGENT-ROLES.md)): the **web agent** (default) starts here; the **mobile agent** starts at [`mobile/MOBILE-PLAN.md`](mobile/MOBILE-PLAN.md).
> **Next session, start here:** read this file, `docs/DEVELOPMENT-RULES.md`, and the newest entries in `DEV_HANDOFF.md` of both repos (2026-10-09).
> Session 6 (all **live on dev**, table below): footer pages (MCP connector, media kit) synced on dev, map quick filters, adventurer/party icons, footer redesign, site icons + share images, share menu on beach pages, copyright (terms §6, IPTC in uploads, ImageObject JSON-LD, © line), author page `/{locale}/gregor-balazic`.
> Session 7 (all **live on dev**, table below): region/island/city pages server-rendered (SEO), audit fixes B9, D5–D7, D12, D13, A4, F1–F4, Organization logo from the brand package.
> Session 8 (all **live on dev**, table below): mobile app plan, side-menu beach groups, mobile header, prod runbook fixes, speed (ISR page cache, images, fonts), backend hardening (PHP-FPM, view counter, nginx rate limit/gzip, indexes, Redis cap, weather timeouts), localized error page, old user import with random passwords.
> Open (code): runbook PR (prod launch review, go/no-go list); alt texts (A6); old user migration fixed (option a), password-reset flow missing (see Open items).
> Owner answers pending: real old URLs for tests; CookieYes "Google Consent Mode"; when to run `media:embed-copyright`; larger author photos.

Covers both repos: `Beachrex/backend` (this repo) and `Beachrex/web-client`.
Rules: [`docs/DEVELOPMENT-RULES.md`](DEVELOPMENT-RULES.md). Change log: `DEV_HANDOFF.md` in each repo.

## Working mode

**Mode B (autonomous)** — the agent plans, implements, tests, opens PRs to `dev` and merges them **when CI is green**,
then verifies the dev deploy.

Standing permissions (owner's words):
- 2026-10-07: "si odpr PR in mergal za vse posodobitve? prvo v dev, potem pa merge dev na uat, ne direkt na uat?"
  → PRs to `dev` may be merged by the agent; `dev → uat` only via a PR from `dev`, and only when the owner asks.
- 2026-10-07: "začni po vrsti in uredi zadeve" → work through the agreed list (below) in order.

Ask the owner for: secrets, accounts (Hetzner, DigitalOcean, OpenAI, Stripe, Google, UptimeRobot), DNS and
anything run on the VPS, product decisions, promotion to uat/prod, irreversible steps.

## Git and CI specifics

- Default branch for work: `dev`. Branch from `origin/dev`; PR into `dev`.
- Backend CI (`.github/workflows/deploy.yml`): `test` job (PHP 8.4 + PostGIS service) then `deploy`.
  The deploy runs only on pushes to `dev`/`uat`/`main` (and manual dispatch).
- Frontend CI (`web-client/.github/workflows/deploy.yml`): deploy only.
- Deploys are serialised per repo (`concurrency: deploy-backend` / `deploy-front`). A backend deploy takes ~5 min, a frontend deploy ~2 min.
- Frontend husky pre-commit runs `yarn lint --fix && git add --all` — commit only with a clean working tree.
- The agent sandbox cannot reach `*.beachrex.com` (connections fail) and has no Docker.
  Verify deploys via the GitHub Actions run status; live checks are an owner action.

## Bootstrap a fresh session (agent sandbox)

```bash
# Backend: PHP 8.3 + composer + local PostgreSQL 16 are available in the sandbox
cd backend && composer install
apt-get install -y postgresql-16-postgis-3        # if PostGIS is missing
service postgresql start
su postgres -c "psql -c \"CREATE USER sail WITH SUPERUSER PASSWORD 'password';\" -c 'CREATE DATABASE beachrex_test OWNER sail;'"
PGPASSWORD=password psql -h 127.0.0.1 -U sail -d beachrex_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"
cp .env.example .env && php artisan key:generate
php artisan test          # expect only the 4 known env-dependent failures

# Frontend
cd web-client && yarn install --frozen-lockfile
npx tsc --noEmit -p . && yarn lint
BASE_URL=http://localhost:9 npx playwright test e2e/*.unit.spec.ts --project=chromium
```

## Environments

| Env | Branch | Frontend | API | Admin |
|---|---|---|---|---|
| dev | `dev` | https://dev.beachrex.com | https://dev-api.beachrex.com | https://dock-dev.beachrex.com |
| uat | `uat` | https://uat.beachrex.com | https://uat-api.beachrex.com | https://dock-uat.beachrex.com |
| prod | `main` | beachrex.com — **not live** (old site, DNS points elsewhere) | api.beachrex.com — not live | dock.beachrex.com |

MCP server: only on dev (`https://mcp-dev.beachrex.com/mcp`).
**Cloudflare proxy is on** (orange cloud) for the dev/uat sites and APIs (checked 2026-10-09); owner keeps it on (global visitors, DDoS protection). Nginx takes the visitor IP from `CF-Connecting-IP` for Cloudflare's ranges only (`deploy/nginx/beachrex.conf`); if Cloudflare publishes new ranges, update that list. Sessions: `SESSION_DRIVER=database` in all three env files (owner, 2026-10-09).
There is **no** Laravel Cloud anymore. The prod DB will be created from the UAT DB (see the prod launch runbook).

## Done

- 2026-10-07: the owner set `OPENAI_MODEL=gpt-4o-mini` in `.env.dev` and `.env.uat`.
- 2026-10-07 (session 2, work order items 1–5):
  - Rules adopted: Beachrex/backend#5, Beachrex/web-client#3.
  - Ops safety net (backups, scheduler, `/api/v1/health`, log rotation, PR CI): Beachrex/backend#6, deploy run 86.
  - Prod launch runbook: Beachrex/backend#7.
  - Frontend PR CI plus a fix for broken beach/beach-map links in da/pt/nl/el/sq: Beachrex/web-client#4.
  - MCP server rewrite and the `country_code` alias: Beachrex/backend#8, deploy run 90.
  - Itineraries rebuilt (TASK-001): Beachrex/backend#9, deploy run 92.
  - Backups prod-only policy (docs): Beachrex/backend#11.
  - **Promoted dev → uat** (owner request): Beachrex/backend#12 (uat deploy run 99) and Beachrex/web-client#5 (uat deploy run 44).
  - MCP deploy fix: the legacy root-owned `beachrex-mcp` held :3100, so the new process crash-looped while the deploy reported success. The deploy now removes the legacy process and **verifies `/health` reports the env**. Also: Imagor bypass for image paths with šumniki. Beachrex/backend#13, dev deploy run 103. **Verified live on dev**: filters, name search ("zlatni rat"), beach type/lifeguard/family data, and image URLs all work.
  - TASK-002 spec written: Beachrex/backend#14.
  - TASK-002 step 1 (`AsciiPath` + read-only `storage:ascii-paths --inventory`): Beachrex/backend#17, dev deploy run 109 green.
    Not yet run against real data (owner action below).
- 2026-10-07 (session 2, later): **Live on dev**. All Deploy runs are green.

  | PR | Change | Deploy run |
  |---|---|---|
  | Beachrex/backend#19 | TASK-002 prevention | 113 |
  | Beachrex/backend#20 | TASK-002 `--copy` / `--update-db` / `--delete-old` (not run) | 115 |
  | Beachrex/backend#21 | distances via `geography`; migration `add_geography_index_to_beach_location` ran on dev | 117 |
  | Beachrex/backend#22 | MCP name match | 119 |
  | Beachrex/backend#23 | API fixes: view count, filter aliases, `/beaches` docs, mobile tour path | 121 |
  | Beachrex/backend#25 | real sea temperature (Open-Meteo Marine) | 125 |
  | Beachrex/backend#26 | TASK-003: AI chat understands any language, real DB candidates | 127 |

  **Verified by the owner on dev (2026-10-07):** "widget je ok, chat je ok" (sea temperature widget, AI chat with the 5 test questions).
  - **Code not yet on uat:** Beachrex/backend#13, #14, #17 and #19–#34, and Beachrex/web-client#6–#8. Promote when the owner asks. The uat **DB** already holds the migrated dev data (2026-10-08).

- 2026-10-09 (session 8): **production readiness, speed and scalability. Live on dev** (each finding verified in code first, all deploy runs green):

  | PR | Change | Deploy run |
  |---|---|---|
  | Beachrex/web-client#43 | Side menu: pre-filtered beach groups that open the map | 84 |
  | Beachrex/web-client#44 | Mobile header without accommodation/shop, cleaner side menu | 85 |
  | Beachrex/backend#59 | Prod launch runbook: blockers fixed, go/no-go list | 197 |
  | Beachrex/web-client#45 | Pages cached with ISR (home 5 min) instead of rendered per request | 86 |
  | Beachrex/web-client#46 | Home images 11.2 MB → 0.8 MB, hero preloaded | 87 |
  | Beachrex/web-client#47 | Self-hosted fonts (next/font), no double API requests | 88 |
  | Beachrex/backend#60 | PHP-FPM pool 16 workers (env `PHP_FPM_*`) | 199 |
  | Beachrex/backend#61 + Beachrex/web-client#48 | Beach views: one UPDATE; `POST /beaches/{id}/view` from the browser (ISR pages) | 201 / 89 |
  | Beachrex/backend#62 | Nginx (all envs): API rate limit 20 r/s per real IP (burst 100, VPS/office IP exempt), gzip, upstream keepalive | 203 |
  | Beachrex/backend#63 | Indexes on translation slugs and beach country/continent | 205 |
  | Beachrex/backend#64 | Redis: 1 GB cap, LRU eviction, fewer snapshots | 207 |
  | Beachrex/backend#65 | Weather timeouts 3 s, failures cached 5 min; `_nocache` only locally | 209 |
  | Beachrex/web-client#49 | Localized error page (19 languages); AI chat images fixed | 90 |
  | Beachrex/backend#66 | Old user import: random passwords (owner option a), admin filter "Imported from old site" | 211 |

  Decisions: `cache:clear` stays in the deploy (stale cache shapes after code changes are riskier; ISR shields the website). HTTP/2 enabled on all HTTPS sites (Beachrex/backend#68). "Less JavaScript" recorded only (owner).
  `SESSION_DRIVER` was `redis` in all env files; the owner switched dev/uat/prod to `database` (2026-10-09), so Redis eviction cannot sign out admins.

- 2026-10-08 (session 7): **TASK-004 SEO follow-up and small audit fixes. Live on dev** (web-client; each finding verified in code first, all deploy runs green):

  | PR | Change | Deploy run |
  |---|---|---|
  | Beachrex/web-client#34 | Region, island and city pages render on the server: name in `<h1>`, beach cards, localized title/description, canonical, Place + BreadcrumbList JSON-LD | 75 |
  | Beachrex/web-client#35 | D7: "All beaches (n)" jumps to the beach list on the page, total count | 76 |
  | Beachrex/web-client#36 | Organization JSON-LD logo = brand package PNG (`/logo.png` did not exist) | 77 |
  | Beachrex/web-client#37 | D5, F1: no `console.log` for visitors; guard test | 78 |
  | Beachrex/web-client#38 | B9: language switch for all 19 locales and the beach map word | 79 |
  | Beachrex/web-client#39 | D12, D13: payment result pages (no `/internal/...` 404s, 19 languages), sign-in error link without `/en` | 80 |
  | Beachrex/web-client#40 | A4: remaining hard-coded English UI text in 19 languages | 81 |
  | Beachrex/web-client#41 | F2, F3: 19 unused files removed, API URL only from `env` (F4 was already done) | 82 |

  Verified: D6 (`'Malinska'` fallback) went with #34; the beach page's distance dropdown already worked (D5 was only the city page). New translations are machine-made by the agent, not native-checked.
  Local check for #34: `next build` + `next start` against a mock API (server HTML has title, description, canonical, h1, cards, JSON-LD; no hydration errors). Live check on dev: owner (view source of a region page).

- 2026-10-08 (session 6): **owner requests after the TASK-004 review. Live on dev** (all deploy runs green):

  | PR | Change | Deploy run |
  |---|---|---|
  | Beachrex/web-client#22 | SEO part 2: one h1, hreflang, sitemap index, map metadata | 63 |
  | Beachrex/backend#50 + Beachrex/web-client#23 | Adventurer / party (youth) icons on cards and the beach page | 179 / 64 |
  | Beachrex/backend#51 | Footer page "Beachrex in your AI assistant" (MCP connector, 19 languages) | 181 |
  | Beachrex/web-client#24 + Beachrex/backend#52 | Map quick filters (advanced off); H13 = filters not shown yet | 65 / 183 |
  | Beachrex/web-client#25 + Beachrex/backend#53 | Media kit: brand package (CGP v1.0) in `web-client/public/brand/`, downloads under CMS page `media-kit` | 66 / 185 |
  | Beachrex/web-client#26 | Footer layout for laptops | 67 |
  | Beachrex/web-client#27 | `favicon.ico`, phone icons, manifest, default share image | 68 |
  | Beachrex/web-client#28 | Beach banner: share menu (X, Facebook, LinkedIn, WhatsApp, Telegram, e-mail, native); ratings + save hidden (H14) | 69 |
  | Beachrex/web-client#29 | Footer: "© {year} MOJA PLAŽA, d.o.o. · BeachRex. All rights reserved." (19 languages) | 70 |
  | Beachrex/backend#54 | Terms of use §6: photos are works of MOJA PLAŽA, d.o.o. (19 languages) | 187 |
  | Beachrex/backend#55 | IPTC copyright in uploaded JPEGs; `media:embed-copyright` backfill (not run) | 189 |
  | Beachrex/web-client#30 | Beach photos as `ImageObject` (creator, licence) in JSON-LD | 71 |
  | Beachrex/web-client#31 | Beach share image = primary photo as 1200×630 JPEG via Imagor | 72 |
  | Beachrex/web-client#32, #33 | Author page `/{locale}/gregor-balazic` (platform design, 19 languages, footer link); role "Founder of Beachrex" | 73 / 74 |

  The owner ran `static-pages:sync` on dev for the MCP page, the media kit and terms §6.
  Owner decisions 2026-10-08: no watermark; trademark to be registered at EUIPO (owner); the author page text stays in `messages/*.json` (no CMS for now); the mobile side padding (global `.container { padding: 0 5px }` in `web-client/src/styles/globals.css`) stays as it is for now.
  X shows no link card for dev URLs because dev's robots.txt is `Disallow: /` (by design); production will.

- 2026-10-08 (session 5): **TASK-004 pre-launch review. Live on dev** (each finding verified in code first):

  | PR | Change | Deploy run |
  |---|---|---|
  | Beachrex/backend#43, #44, #45 | TASK-004 spec, audit, owner decisions, hidden-for-launch list, step 5b | docs |
  | Beachrex/web-client#16 | Slovenian `Auth`/`AI` strings; locale key parity test | 55 |
  | Beachrex/web-client#17 | Placeholder accommodation/GetYourGuide hidden (`FEATURES`), no Lorem Ipsum | 56 |
  | Beachrex/web-client#18 | Menu/footer from real data, dead buttons hidden, `/about` → CMS, localized 404 | 57 |
  | Beachrex/backend#46 + Beachrex/web-client#19 | Old beachrex.com URLs → 308 to the same beach/city/island/region/country | 171 / 58 |
  | Beachrex/backend#47 | Footer CMS pages in 19 languages + `static-pages:sync` | 173 |
  | Beachrex/web-client#20 | `/beaches` on real data, shared cards (QA #48) | 59 (manual dispatch; the push event was not delivered) |
  | Beachrex/web-client#21 | Production `robots.txt` (a static file blocked all crawlers), AI crawler policy, consent + GA + AdSense, `ads.txt`, `llms.txt` | 59 |
  | Beachrex/backend#48 | Backend never indexed (`X-Robots-Tag`, `robots.txt`); visibility rule | 175 |

  Owner decisions 2026-10-08: ADR "TASK-004: What Visitors See at Launch"; dev/uat invisible to search engines and AI, only production visible (`DEVELOPMENT-RULES.md` §9).
- 2026-10-08 (session 4): **TASK-002 finished and P2 rows started. Live on dev**:
  - Step 6 is done: 10 685 old keys were deleted after the owner's explicit "da", and the control inventory is clean.
  - Each QA root cause below was verified in the code (and for webcam/waterbody in the dev DB) first.

  | PR | Change | Deploy run |
  |---|---|---|
  | Beachrex/backend#35 | TASK-002 §5: MCP images only via Imagor; step 6 docs | 148 |
  | Beachrex/web-client#10 | TASK-002 §5: `buildCdnUrl` without the Spaces bypass | 49 |
  | Beachrex/web-client#9 | QA #45, #46: beach page badges from real data | 48 |
  | Beachrex/web-client#11 | QA #36, #65, #68: one webcam rule; webcam on the beach page | 50 |
  | Beachrex/web-client#12 | QA #67: cards use uploaded videos like the beach page | 51 |

  | Beachrex/backend#38 | QA #70: `pool_type` filter (indoor/outdoor) | 154 |
  | Beachrex/web-client#13 | QA #70: home pool tiles send `pool_type` | 52 |
  | Beachrex/backend#40 | QA #45 cards: `features` (beach type, family, parking) in list/map results | 158 |
  | Beachrex/web-client#14 | QA #45 cards: icons follow `features` | 53 |
  | Beachrex/web-client#15 | QA #45: real beach type / family / parking icons from Spaces `categories_icons` (owner confirmed on dev: "odlično") | 54 |

  QA #69 is data: every beach is waterbody `sea`. The owner keeps the tiles and will add the data (2026-10-08).
  Owner 2026-10-08: QA #43 was already fixed (the spreadsheet was not updated); QA #72 waits until the tester explains it; QA #40 is skipped (probably fixed). The tester does not always update statuses, so verify before fixing.
- 2026-10-07 (session 3): **dev review tracker P1 rows fixed, Live on dev** and set to Retest. Each root cause was verified in the code first:

  | PR | QA rows | Root cause | Deploy run |
  |---|---|---|---|
  | Beachrex/web-client#6 | 19, 53, 55, 56, 60, 77 | card lists threw on beaches without `main_image`; `buildDestinationUrl` threw for cities/islands without a region | 45 |
  | Beachrex/web-client#7 | 52, 53, 54 | search pushed `/<locale>/beaches/...`; the middleware turned next-intl's redirect into an empty 200 | 46 |
  | Beachrex/web-client#8 | 24, 25, 74 | locale-less `next/link` hrefs (logo, footer map, listing map buttons) | 47 |

  The sandbox cannot reach dev, so the live check is the tester's retest.
- 2026-10-07: security/locale/filter/cache fixes and a docs catch-up —
  Beachrex/backend#3 and Beachrex/web-client#1 merged to dev; Beachrex/backend#4 and Beachrex/web-client#2 promoted to uat.
  Deploys green: backend dev run 82, uat run 83; frontend dev run 40, uat run 41.

## Owner's open actions

- **Old beachrex.com URLs (live on dev since 2026-10-08, Beachrex/backend#46 + Beachrex/web-client#19):** test them on dev now. Take 5–10 real old links (Google Maps, other sites), replace `https://www.beachrex.com` with `https://dev.beachrex.com` and open them: each must end on the same beach/place (308), not the home page or a 404. Send the agent any that land wrong; they become test cases. On beachrex.com they start working at the DNS switch (prod launch runbook D2/D3, check D8). After launch: Google Search Console → submit `https://beachrex.com/sitemap.xml` and watch the "Not found (404)" report for a few weeks.
- **Mobile app (TASK-005, `docs/mobile/MOBILE-PLAN.md` §14):** Apple Developer (D-U-N-S) and Google Play accounts, Expo account (builds run on the owner's computer), Sentry yes/no. Done/decided 2026-10-09: repo `Beachrex/mobile-app`, bundle ID `com.datavallis.beachrex`; no `EXPO_TOKEN` and no Google Maps key needed.

- **Footer pages on uat/prod:** `static-pages:sync` (dry run first) at promotion (prod launch runbook B6). Dev is synced (2026-10-08).
- **Copyright backfill:** `php artisan media:embed-copyright --dry-run`, then without `--dry-run`, when the owner decides (rewrites existing JPEG originals on Spaces; best on prod after launch).
- **Author page photos:** the two photos are 750 px; larger originals (≥1920 px) would sharpen the banner.
- **Icon files on Spaces:** confirm `categories_icons/user_type-adventurer.png` and `user_type-youth.png` exist (adventurer/party icons).
- **Legal translations:** terms §6 and the footer pages were machine-translated; have the main languages checked.
- **Footer pages on dev (TASK-004):** run `static-pages:sync --dry-run`, then without `--dry-run` (commands in DEV_HANDOFF / the chat). Same on uat/prod at promotion (prod launch runbook B6).
- **CookieYes:** enable "Google Consent Mode"; domain `beachrex.com`.
- **Legal texts:** the privacy policy, cookie policy and terms are drafts (they name OpenAI and Stripe, which the code uses); review, ideally with a lawyer.

- **Waterbody data (QA #69):** every beach has waterbody `sea` (3 774 on dev, 2026-10-08), so the lake/river/pool tiles and filters find nothing. Owner decision 2026-10-08: keep the tiles and add the data ("to zaenkrat pusti, samo zabeleži, moramo dodati podatke"). No code change.

- **Open-Meteo licence (sea temperature):** the free Marine API is for non-commercial use only. Before the commercial launch, take an Open-Meteo API plan and set `OPEN_METEO_API_KEY` in `.env.prod` (and `.env.uat` if wanted). Without a key the code uses the free endpoint.

- **TASK-002**: the inventory ran on dev on 2026-10-08, after the scan fixes in Beachrex/backend#29, #30 and #31 (dev run 137). Results:
  - 206 174 keys, of which 10 685 are non-ASCII (10 680 NFD, 5 NFC) in 202 beach folders.
  - **0 collisions**.
  - 399 vtour files reference a migrated key.
  - 2 413 DB values: `media.file_name` 1183, `media.file_path` 982, `beach.folder_name` 202, `city.main_image` 40, `island.main_image` 5, `region.main_image` 1.
  - The agent checked the CSV: every DB value maps to an existing migrated key.

  Per the owner, no uat inventory is needed: uat gets the dev DB by promotion (ADR 2026-10-08).
  **Steps 2–5 done on dev (2026-10-08):**
  - `--copy` copied 10 685 keys and rewrote 399 vtour files. The old keys were kept.
  - `pg_dump` saved as `/root/beachrex_dev-before-ascii-20261008-0825.dump` on the VPS.
  - `--update-db` updated 2 413 values and skipped 0.
  - `cache:clear` and `search:index` ran.
  - The control inventory shows 0 non-ASCII DB values and all old keys `copied`.
  - The acceptance URL works.

  **dev → uat DB promotion done (2026-10-08, owner):**
  - uat backup saved as `/root/beachrex_uat-before-promote-20261008-0906.dump`.
  - Counts match dev: 3 774 beaches, 43 335 media, 4 users. uat has 0 non-ASCII paths.
  - `cache:clear` and `search:index` ran on uat, and uat health is ok.
  - Everything that existed only in the uat DB was replaced by dev data.

  - The owner checked beach pages with šumniki on uat on 2026-10-08: images work.
  - The old beachrex.com site also reads the `beachrex` bucket. The owner accepts that its images with šumniki break after step 6: "to nima veze, zato pa pohitimo z razvojem, da damo novo strani čimprej ven" (2026-10-08).

  **Step 6 done (2026-10-08):** the dry run listed 10 685 keys; after the owner's explicit "da", `--delete-old --confirm-delete` deleted 10 685 old keys. The control inventory shows 0 non-ASCII keys, 0 collisions and 0 non-ASCII DB values.

  Cleanup PRs (TASK-002 §5): the Imagor/Spaces bypass is removed from MCP (Beachrex/backend#35) and web-client (Beachrex/web-client#10). ADR 2026-10-08.

- **DB backups** (`docs/runbooks/restore-backup.md`). Policy (owner, 2026-10-07): prod only; uat temporarily until prod is live.
  - 2026-10-07: the owner created the `beachrex-backups` bucket and key and set the `BACKUP_*` vars in `.env.uat`.
  - Still to do: after the uat deploy, run `db:backup --no-prune` once on uat, then the restore drill.
  - At prod launch (runbook phase D5): `BACKUP_ENABLED=true` in `.env.prod`, then `BACKUP_ENABLED=false` in `.env.uat` and redeploy uat.
- **Enable Hetzner Cloud Backups** for the VPS (Hetzner console → server → Backups).
- **UptimeRobot** (or similar): HTTP monitors on `https://dev-api.beachrex.com/api/v1/health` and `https://uat-api.beachrex.com/api/v1/health`, with an alert e-mail to the owner.
- Optional: Docker daemon log rotation for `pgsql`/`redis` (`/etc/docker/daemon.json` `log-opts`).

- Smoke-check UAT after the 2026-10-07 promotion:
  - `curl -s -o /dev/null -w "%{http_code}" -X POST -H "Accept: application/json" https://uat-api.beachrex.com/api/v1/beaches` → `401`
  - `curl -sI https://uat.beachrex.com/en | grep -i x-robots-tag` → `noindex, nofollow, noarchive, nosnippet`

## Agreed work order (2026-10-07)

1. **Dev rules adopted**: this file, `DEVELOPMENT-RULES.md`, `CLAUDE.md` in both repos. *(done: Beachrex/backend#5, Beachrex/web-client#3)*
2. **Ops safety net**: daily DB backups to a private bucket, scheduler service, Docker log rotation,
   `/api/v1/health`, PR CI. *(Built; backups need the owner step below)*
3. **Prod launch runbook**: [`docs/runbooks/prod-launch.md`](runbooks/prod-launch.md) covers the prod DB from UAT, env checklist, deploy, DNS, SSL and rollback. *(written; launch date is the owner's decision)*
4. **MCP server quality**: fixed and **verified live on dev** (2026-10-07). The owner tested it in Claude and is happy with it, including the AI adding general knowledge (e.g. lifeguard season) on top of Beachrex data.
5. **AI itineraries**: rebuilt (TASK-001; ADR "code plans, LLM narrates"; pgvector deferred). *(Built; live quality check on dev is an owner action, and `OPENAI_MODEL` in the server env should be `gpt-4o-mini`)*
   **Now: TASK-002 ASCII-only storage paths** (owner request 2026-10-07; spec: `docs/tasks/TASK-002-ascii-storage-paths.md`).
   Step 1 built: `AsciiPath` + read-only `storage:ascii-paths --inventory` (runbook `docs/runbooks/ascii-storage-paths.md`).
   Step 2 (prevention) merged: Beachrex/backend#19. New or changed paths are stored ASCII (`HasAsciiPaths`, ASCII upload names).
   Step 3 merged (Beachrex/backend#20): `--copy` / `--update-db` / `--delete-old`, each with `--dry-run`, plus runbook steps 2–6. Not run yet; it waits for the owner's inventory and collision review.
   After step 6: cleanup PRs (TASK-002 §5) remove the Imagor bypass in web-client and MCP.
   **TASK-003 AI chat** (owner: "ja, začni task", 2026-10-07): structured multilingual query understanding + real DB candidates. **Done**: Beachrex/backend#26, dev run 127; the owner confirmed it on dev.
6. **SEO before launch** — TASK-004: robots/AI policy, sitemap index, h1, hreflang, `/beaches` SSR, beach map metadata, SSR of region/island/city pages *(done)*; alt texts (A6) *(open)*.
7. **Dev review tracker** (owner, 2026-10-07): a tester reviews dev and logs findings in the owner's spreadsheet
   `dev.beachrex_pregled_napak_delovanja.xlsx` (77 rows as of 2026-10-07). The spreadsheet stays with the owner (e-mail links, personal data).
   The agent's copy with a first-pass verdict per row is **[`docs/qa/dev-review-tracker.md`](qa/dev-review-tracker.md)**. Owner's instructions:
   - assess every row before fixing (some are already fixed, some are nonsense, some are only missing translations), so we don't break anything;
   - record what was fixed, so the owner can mark the row in the spreadsheet for the tester to retest.

   Process: one fix = one PR referencing `QA #<row>`. After the dev deploy the tracker row gets `Fixed in` + **Retest**. The owner updates the spreadsheet; the tester retests. When the owner sends a newer spreadsheet, re-sync the tracker by row number.
   Next: the P1 crashes (continent/country pages, non-EN search → empty page, locale jumps); they are mostly in web-client.

8. **TASK-004 Pre-launch review** (owner, 2026-10-08: "celotno stran je treba pregledat, kaj še ni narejeno, v nogi ko so prazne strani … bazo napolnit vsebino za nogo v vseh jezikih … podstran /si/plaze … grafično popravit in napolnit z relevantnimi podatki … stran dali v produkcijo v naslednjih dneh"). *(planned; write `docs/tasks/TASK-004-pre-launch-review.md` first)*
   Known inputs:
   - the footer `browse.links` and `BurgerMenu` link to routes that do not exist (see Open items);
   - static pages are CMS-managed (`/api/v1/static-pages`, `StaticPageSeeder`);
   - `/[locale]/beaches` (`web-client/src/app/[locale]/beaches/page.tsx`) uses `mockCountries` and a mock "Plaža Ladinka" ×4;
   - missing translations (tracker P3 rows 1, 26–29, 35, 75, 76);
   - data quality (beach names with an English "Beach" prefix, missing region translations, about 21% of beaches have a description).
   The sandbox cannot reach `*.beachrex.com` or `beachrex.fra1.digitaloceanspaces.com` (proxy 403), so the page-by-page review needs the owner's screenshots, or the owner adds those hosts under the environment's Allowed domains.

9. **Mobile app** (owner, 2026-10-09): TASK-005, built by the **mobile agent** in `Beachrex/mobile-app` (Expo, React Native, NativeWind, EAS). Plan `docs/mobile/MOBILE-PLAN.md`, tasks `docs/mobile/MOBILE-TASKS.md`. The web agent owns the backend/web tasks the app needs (MOBILE-TASKS §B: `app-config`, native sign-in, favorites API, app-link files) and keeps `/api/v1` additive-only.

## AI chat and itinerary plan (owner accepted 2026-10-09)

ADR "AI chat and itinerary: plan to make them sellable" in `docs/ai/implementation/DECISIONS.md`. One PR per step, as always.
- [x] **AI-1** quick chat fixes (Beachrex/backend#72, Beachrex/web-client#51, live on dev): backend (real slugs/photos/page language, session memory, prompt, accessibility free, 503) and web chat page (session, locale, linked beach names, photos, error texts, location).
- [x] **AI-2** tool-calling chat like the MCP + evaluation set (Beachrex/backend#74, #75, Beachrex/web-client#54). Evaluation 2026-10-09: Claude Opus 5.5 37/40 (3 "fails" = no dog/naturist tags in HR data), ~$0.036/question. **On on dev** with `AI_CHAT_ENGINE=agent`, `AI_CHAT_PROVIDER=anthropic`. uat/prod: only when the owner asks; they also need `ANTHROPIC_API_KEY`.
- [x] **AI-3** sellable itinerary, live on dev 2026-10-09:
  - payment safety (Beachrex/backend#76);
  - beach links/photos and `/payments/products` (#77);
  - background generation in `worker_<env>` and saved plans API (#78; worker checked `Up` by the owner);
  - planner form (Beachrex/web-client#57);
  - plan page with progress and "My plans" (#58);
  - trip map and Save as PDF (#59).
  - Owner, before selling: the Stripe webhook must also send `checkout.session.async_payment_succeeded`. Test a real purchase with Stripe test keys on dev.
- [ ] **AI-4** EU selling with the owner (EUR, VAT/invoices, withdrawal waiver, pricing page, terms).
  - **On hold (owner, 2026-10-09):** a new Stripe account is being opened for MOJA PLAŽA d.o.o.; the current account is only for testing. Start AI-4 when the owner has the new account.
  - Open owner decisions:
    1. Prices in EUR: keep 19/39/59 for 3/7/14 days?
    2. VAT: is the company VAT-registered in Slovenia, and should Stripe Tax be on in the new account?
    3. Invoice details: the company name, address and tax number.
    4. The 14-day withdrawal waiver for digital content: who reviews the terms text (owner's lawyer, or the agent drafts it)?
    5. A separate pricing page (e.g. `/cenik`), or prices in the planner only?
  - When the account exists, the owner puts its keys and price ids in `/opt/beachrex/envs/.env.*`: `AI_PAYMENTS_ENABLED=true`, `STRIPE_ENABLED=true`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` and `STRIPE_PRICE_ITINERARY_{3,7,14}DAY`. The webhook must send `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.expired` and `charge.refunded`.

## Mobile app

Status: **In progress** (owner, 2026-10-09: mobile development starts today). M0.1 (Expo app in `Beachrex/mobile-app`) in progress.
Backend for the app (MOBILE-TASKS §B): B-M1 `GET /api/v1/app-config` done (Beachrex/backend#83); B-M3 favorites API done (`/api/v1/me/favorites`, web H6 UI still hidden); B-M2: the app signs in with e-mail + password (owner 2026-10-09); `DELETE /api/v1/me` added, login/register throttled; Google/Apple sign-in later, when the app is in production.

Owner decisions 2026-10-09: bundle ID `com.datavallis.beachrex`; branches `dev` + `main`; map = Leaflet + OpenStreetMap in a WebView (no Google Maps key); builds run on the owner's computer (no `EXPO_TOKEN`); `development` and `preview` builds use the dev API (no uat).
The web agent writes this section from the mobile agent's status relayed by the owner (doc ownership: `docs/AGENT-ROLES.md`).

## Open items (found, not yet scheduled)

- **Less JavaScript on every page** (owner 2026-10-09: "zaenkrat ne, ampak si zabeleži"): header/footer as server components, flowbite Dropdown/Sidebar replaced by small own code, lazy burger menu and search results. Expected saving ~60–100 KB gzipped per page (today ~240 KB). Found in the 2026-10-09 performance audit.

- **Old user migration (`users:migrate-from-old-db`): fixed per owner option (a) on 2026-10-09** — random unusable password, never the MD5 hash or "password"; imported users sign in with Google (linked by e-mail). Run only in production after launch (GDPR): `--test` first. Admin → Users has the filter "Imported from old site". Do **not** remove `database/sql/user.sql` (owner).
  - **Forgot password** (owner 2026-10-09): `POST /api/v1/forgot-password` + `/reset-password`, e-mail via SMTP `mail.datavallis.com:465` as `hello@beachrex.com`; website e-mail sign-in + reset pages. Works only after the owner adds the `MAIL_*` lines to `/opt/beachrex/envs/.env.<env>` and redeploys (prod runbook A2 lists them).
- **Mobile side padding:** the global `.container { padding: 0 5px }` overrides Tailwind padding on every container; owner: leave for now (2026-10-08).
- **MCP follow-ups (owner feedback 2026-10-07, after a live test in Claude):**
  - **MCP must become production-only** once prod is live (prod launch runbook D5b). Links will then point to `beachrex.com`.
  - **Images only from `s3.beachrex.com`** once TASK-002 removes non-ASCII paths (TASK-002 §5 removes the Spaces bypass).

- **Data quality (not code), seen via MCP 2026-10-07:**
  - many `beach_translation.name` values contain the English prefix "Beach …" in every locale (e.g. sl "Beach Zlatni Rat");
  - region translations are missing for most locales (sl shows "Dalmatia - Split");
  - only ~21% of beaches have a description (Zlatni Rat has none).
  
  Candidates for an AI translation/cleanup pass (`beaches:translate` exists) once TASK-002 is done.

- An unknown URL under `/{locale}/{beaches}/{continent}/...` shows the 404 page with HTTP status **200**: `src/app/[locale]/beaches/[continent]/loading.tsx` makes the response stream before `notFound()`. The page is `noindex`, so search engines drop it; a real 404 status needs the loading UI moved below the route that resolves the slug. Found 2026-10-08 (session 7), not fixed.
- `.gitlab-ci.yml` in both repos is unused (GitHub Actions replaced it).
- Local `sail artisan test`: `phpunit.xml` points at `127.0.0.1` / `beachrex_test`, as in CI. Inside Sail this probably fails.
- Cache invalidation on admin edits for other cached models (Beach, Country, …): `DEPLOYMENT_GUIDE` §9.
- MCP on uat/prod: the processes run on 3101/3102, but there is no Nginx route or DNS (`mcp-uat`/`mcp`) yet. Owner decision.
- `MediaService::upload` (API `/media/upload`) stores the client's original name in `media.file_name`, but the file is stored under a slug + time + random name. `BeachDetailsController` (previous/next beach `main_image`) builds URLs from `folder_name` + `file_name`, so such images would 404 there. Found during TASK-002, not fixed.
- API issues found while fixing the MCP (2026-10-07), not fixed yet:

## Latest numbers

- Next TASK number: TASK-006 (TASK-001 itinerary quality, TASK-002 ASCII storage paths, TASK-003 chat understanding, TASK-004 pre-launch review, TASK-005 mobile app) · ADR log: `docs/ai/implementation/DECISIONS.md` (dated entries)

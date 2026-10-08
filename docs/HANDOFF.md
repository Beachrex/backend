# Handoff — state as of 2026-10-08 (end of session 3)

> **Next session, start here:** (1) TASK-002: all steps are done, including step 6 (10 685 old keys deleted, 2026-10-08). Only the §5 cleanup PRs remain (check their status below);
> (2) dev review tracker [`docs/qa/dev-review-tracker.md`](qa/dev-review-tracker.md): P1 rows and P2 rows 36, 45, 46, 65, 67, 68 are fixed (Retest). QA #69 is data (all beaches are waterbody `sea`); the owner will add lake/river/pool data. Remaining P2 rows: 48 (`/beaches` mock data); 72 waits for the tester; 45 (cards) and 70 are in Retest;
> (3) item 6 (SEO) after that. Promotion to uat only when the owner asks.

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
6. **SEO before launch** — SSR for `/beaches` and `/beach-map`, `about` metadata, single H1, alt texts. *(planned)*
7. **Dev review tracker** (owner, 2026-10-07): a tester reviews dev and logs findings in the owner's spreadsheet
   `dev.beachrex_pregled_napak_delovanja.xlsx` (77 rows as of 2026-10-07). The spreadsheet stays with the owner (e-mail links, personal data).
   The agent's copy with a first-pass verdict per row is **[`docs/qa/dev-review-tracker.md`](qa/dev-review-tracker.md)**. Owner's instructions:
   - assess every row before fixing (some are already fixed, some are nonsense, some are only missing translations), so we don't break anything;
   - record what was fixed, so the owner can mark the row in the spreadsheet for the tester to retest.

   Process: one fix = one PR referencing `QA #<row>`. After the dev deploy the tracker row gets `Fixed in` + **Retest**. The owner updates the spreadsheet; the tester retests. When the owner sends a newer spreadsheet, re-sync the tracker by row number.
   Next: the P1 crashes (continent/country pages, non-EN search → empty page, locale jumps); they are mostly in web-client.

## Open items (found, not yet scheduled)

- **MCP follow-ups (owner feedback 2026-10-07, after a live test in Claude):**
  - **MCP must become production-only** once prod is live (prod launch runbook D5b). Links will then point to `beachrex.com`.
  - **Images only from `s3.beachrex.com`** once TASK-002 removes non-ASCII paths (TASK-002 §5 removes the Spaces bypass).

- **Data quality (not code), seen via MCP 2026-10-07:**
  - many `beach_translation.name` values contain the English prefix "Beach …" in every locale (e.g. sl "Beach Zlatni Rat");
  - region translations are missing for most locales (sl shows "Dalmatia - Split");
  - only ~21% of beaches have a description (Zlatni Rat has none).
  
  Candidates for an AI translation/cleanup pass (`beaches:translate` exists) once TASK-002 is done.

- Frontend links found during QA #24/#25/#74, not fixed (allowlisted in `web-client/e2e/locale-links.unit.spec.ts`):
  - `payment/success` and `payment/cancel` pages use locale-less `next/link` hrefs (`/`, `/ai/...`, `/internal/...`);
  - `auth/callback` links to a hard-coded `/en`;
  - `BurgerMenu` links to `/beach-accommodation` and `/mobile-app`, and the footer `browse.links` messages link to `/destinations` and `/accommodations`. None of these routes exist (404);
  - `src/hooks/useLocalizedPath.ts` has its own beaches-word map without da/pt/nl/el/sq, so the language switch from those locales' beach pages is not recognised.
- `.gitlab-ci.yml` in both repos is unused (GitHub Actions replaced it).
- Local `sail artisan test`: `phpunit.xml` points at `127.0.0.1` / `beachrex_test`, as in CI. Inside Sail this probably fails.
- Cache invalidation on admin edits for other cached models (Beach, Country, …): `DEPLOYMENT_GUIDE` §9.
- MCP on uat/prod: the processes run on 3101/3102, but there is no Nginx route or DNS (`mcp-uat`/`mcp`) yet. Owner decision.
- `MediaService::upload` (API `/media/upload`) stores the client's original name in `media.file_name`, but the file is stored under a slug + time + random name. `BeachDetailsController` (previous/next beach `main_image`) builds URLs from `folder_name` + `file_name`, so such images would 404 there. Found during TASK-002, not fixed.
- API issues found while fixing the MCP (2026-10-07), not fixed yet:

## Latest numbers

- Next TASK number: TASK-004 (TASK-001 = itinerary quality, TASK-002 = ASCII storage paths, TASK-003 = chat understanding) · ADR log: `docs/ai/implementation/DECISIONS.md` (dated entries)

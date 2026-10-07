# Handoff — state as of 2026-10-07 (end of session 2)

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
  - **Not yet on uat:** Beachrex/backend#13, #14, #17 and #19–#23. Promote when the owner asks.

- 2026-10-07: security/locale/filter/cache fixes and a docs catch-up —
  Beachrex/backend#3 and Beachrex/web-client#1 merged to dev; Beachrex/backend#4 and Beachrex/web-client#2 promoted to uat.
  Deploys green: backend dev run 82, uat run 83; frontend dev run 40, uat run 41.

## Owner's open actions

- **Open-Meteo licence (sea temperature):** the free Marine API is for non-commercial use only. Before the commercial launch, take an Open-Meteo API plan and set `OPEN_METEO_API_KEY` in `.env.prod` (and `.env.uat` if wanted). Without a key the code uses the free endpoint.

- **TASK-002 inventory (read-only):** deployed to dev (Beachrex/backend#17, run 109), run `storage:ascii-paths --inventory` on dev and uat
  per [`docs/runbooks/ascii-storage-paths.md`](runbooks/ascii-storage-paths.md) and send the summary + CSVs to the agent.
  Nothing on Spaces moves until the owner has reviewed the inventory.

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
   **TASK-003 AI chat** (owner: "ja, začni task", 2026-10-07): structured multilingual query understanding + real DB candidates. Built; live check on dev is an owner action (5 questions in sl/hr/de/it/en, `docs/tasks/TASK-003.feedback.md`).
6. **SEO before launch** — SSR for `/beaches` and `/beach-map`, `about` metadata, single H1, alt texts. *(planned)*

## Open items (found, not yet scheduled)

- **MCP follow-ups (owner feedback 2026-10-07, after a live test in Claude):**
  - **MCP must become production-only** once prod is live (prod launch runbook D5b). Links will then point to `beachrex.com`.
  - **Images only from `s3.beachrex.com`** once TASK-002 removes non-ASCII paths (TASK-002 §5 removes the Spaces bypass).

- **Data quality (not code), seen via MCP 2026-10-07:**
  - many `beach_translation.name` values contain the English prefix "Beach …" in every locale (e.g. sl "Beach Zlatni Rat");
  - region translations are missing for most locales (sl shows "Dalmatia - Split");
  - only ~21% of beaches have a description (Zlatni Rat has none).
  
  Candidates for an AI translation/cleanup pass (`beaches:translate` exists) once TASK-002 is done.

- `.gitlab-ci.yml` in both repos is unused (GitHub Actions replaced it).
- Local `sail artisan test`: `phpunit.xml` points at `127.0.0.1` / `beachrex_test`, as in CI. Inside Sail this probably fails.
- Cache invalidation on admin edits for other cached models (Beach, Country, …): `DEPLOYMENT_GUIDE` §9.
- MCP on uat/prod: the processes run on 3101/3102, but there is no Nginx route or DNS (`mcp-uat`/`mcp`) yet. Owner decision.
- `MediaService::upload` (API `/media/upload`) stores the client's original name in `media.file_name`, but the file is stored under a slug + time + random name. `BeachDetailsController` (previous/next beach `main_image`) builds URLs from `folder_name` + `file_name`, so such images would 404 there. Found during TASK-002, not fixed.
- API issues found while fixing the MCP (2026-10-07), not fixed yet:

## Latest numbers

- Next TASK number: TASK-004 (TASK-001 = itinerary quality, TASK-002 = ASCII storage paths, TASK-003 = chat understanding) · ADR log: `docs/ai/implementation/DECISIONS.md` (dated entries)

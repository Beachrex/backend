# Runbook — ASCII-only storage paths (TASK-002)

Spec: [`docs/tasks/TASK-002-ascii-storage-paths.md`](../tasks/TASK-002-ascii-storage-paths.md).
Command: `php artisan storage:ascii-paths`.

The `beachrex` Spaces bucket is **shared by dev, uat and prod**. Steps run in this order, and nothing is
deleted until every environment DB is updated and verified.

| Step | Flag | Changes anything? | Status |
|---|---|---|---|
| 1. Inventory | `--inventory` | No (read-only) | **Built** (deployed to dev) |
| 2. Copy keys + rewrite vtour references | `--copy` | Adds new keys to Spaces; rewrites tour files | **Built** (not run) |
| 3. Update DB paths (per env) | `--update-db` | DB rows | **Built** (not run) |
| 4. Caches + search reindex | `cache:clear`, `search:index` | Caches | existing commands |
| 5. Verify | `--inventory` again + image checks | No | manual |
| 6. Delete old keys (owner confirms) | `--delete-old --confirm-delete` | Deletes keys | **Built** (not run) |

`--copy`, `--update-db` and `--delete-old` accept `--dry-run`. Always run the dry run first and read its output.
New paths are already guarded: since TASK-002 prevention, models store new or changed paths ASCII-only.

## Step 1 — Inventory (owner, on the VPS)

Read-only. It lists every key under `public/img/` with non-ASCII characters (NFC, NFD or mixed), every non-ASCII
value in a path-like DB column (`beach.folder_name`, `media.file_path`, `media.file_name`, `countries.image_path`,
`*.main_image`, `article.feature_image`, `badges.icon`, and any other text/JSON column whose name contains
path/url/folder/image/icon/file_name/…), and every vtour text file (`tour.xml`, `*.html`, `*.js` …) that references
a non-ASCII name. It writes an `old → new` CSV and flags collisions.

Spaces are the same for every env, so the Spaces part only needs to run once (dev). The DB part is per env.

```bash
COMPOSE="docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml"

# dev (Spaces + dev DB)
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev \
  php artisan storage:ascii-paths --inventory --output=/tmp/inventory-dev.csv
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev cat /tmp/inventory-dev.csv > ~/inventory-dev.csv

# uat (uat DB; the Spaces part repeats and should match dev)
$COMPOSE --env-file /opt/beachrex/envs/.env.uat exec -T api_uat \
  php artisan storage:ascii-paths --inventory --output=/tmp/inventory-uat.csv
$COMPOSE --env-file /opt/beachrex/envs/.env.uat exec -T api_uat cat /tmp/inventory-uat.csv > ~/inventory-uat.csv
```

Send the agent the summary table printed by the command and the two CSV files.

### Long scans: run it detached

The bucket holds hundreds of thousands of keys (360° tour tiles), so listing can take many minutes. Since 2026-10-08 the command prints `listed N keys (Ns, N MB) … last: <key>` every 10 000 keys (`--progress=N`, `0` = off).
If the SSH session or terminal times out, the scan dies with it. Run the scan detached inside the container and follow its log:

```bash
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -d -T api_dev sh -c \
  'php artisan storage:ascii-paths --inventory --output=/tmp/inventory-dev.csv > /tmp/inventory-dev.log 2>&1'

# check progress (repeat until the summary table and "CSV written to" appear)
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev tail -n 25 /tmp/inventory-dev.log

# when done
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev cat /tmp/inventory-dev.csv > ~/inventory-dev.csv
```

- A deploy recreates `api_dev` and kills a running scan. Nobody merges to `dev` while it runs.
- The `NEXT_PUBLIC_* variable is not set` warnings come from Docker Compose reading the frontend services in the same file. They are harmless here.

### CSV columns

| Column | Meaning |
|---|---|
| `source` | `spaces` (a key), `db` (a column value) or `vtour_ref` (a tour text file that references non-ASCII names) |
| `location` | The key, or `table.column#id` (JSON columns add `:$.json.path`) |
| `old` / `new` | Current value and its ASCII form (`App\Support\AsciiPath::fromPath`) |
| `form` | `NFC`, `NFD` or `mixed`. Keys were mostly uploaded in NFD, URLs are built in NFC |
| `issue` | `collision` (two old keys → one new key, or a *different* file already sits at the new key), `copied` (the new key already holds the same file), `rewrite_refs` (vtour) or `skipped_url` (a DB value that is a full URL; fix by hand) |
| `detail` | Collision partners, or the non-ASCII names found in a vtour file |

**Collisions must be resolved before step 2.** Typical case: the same file exists in NFC and NFD form.

## Before step 2

- The inventory shows **0 collisions**. Resolve each one by hand: decide which file wins, then delete or rename the other. Rerun `--inventory`.
- `db:backup --no-prune` on uat (`docs/runbooks/restore-backup.md`).
- Nobody merges to `dev` while the steps run, because a deploy recreates `api_dev` (lesson from 2026-10-07).

`COMPOSE` and the env files are the same as in step 1. Every command runs in the container of the environment named.

## Step 2 — Copy (once, from dev; the bucket is shared)

```bash
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan storage:ascii-paths --copy --dry-run
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan storage:ascii-paths --copy
```

- Copies every non-ASCII key to its ASCII key with `public-read`, server-side. **Old keys stay**, so every site keeps working.
- Aborts without copying anything if there is a collision.
- Then rewrites vtour text files (`tour.xml`, `*.html`, `*.js` …). References are rewritten only where they match a migrated folder or file name, so titles stay unchanged.
  - A tour at a non-ASCII key is rewritten at its ASCII copy; the old file stays as is.
  - A tour at an ASCII key is rewritten in place. The original is saved in the container under `storage/app/private/ascii-paths/vtour-backup/`.
  - URL-encoded references (`%C4%8D`) are not detected. Check one tour per affected beach in step 5.
- Idempotent: a second run copies 0.

## Step 3 — Update the DB (per env: dev, then uat)

```bash
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan storage:ascii-paths --update-db --dry-run
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan storage:ascii-paths --update-db
# uat: same with .env.uat / api_uat (after uat has this code)
```

- Refuses while any non-ASCII key is not copied yet.
- Rewrites every non-ASCII value in path-like columns, including strings inside JSON columns, in one transaction.
- Values that contain `://` (full URLs) are skipped and listed; fix them by hand.
- Idempotent.

## Step 4 — Caches

```bash
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan cache:clear
$COMPOSE --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan search:index
```

Same for uat. The frontend uses the API, so no frontend redeploy is needed.

## Step 5 — Verify

- `--inventory` on each env: `Non-ASCII DB values` = 0, except the skipped URLs.
- Spaces keys still show as `copied` until step 6.
- Open 10 beach pages with former šumniki on dev and uat: gallery, main image, previous/next, 360° tour.
- The acceptance URL works through Imagor on 10 consecutive requests:
  `https://s3.beachrex.com/unsafe/fit-in/800x800/img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg`

## Step 6 — Delete old keys (owner confirms; affects every env)

Only after steps 3–5 are done on **every** env DB. Prod is created from uat, so dev + uat are enough before prod exists.

```bash
$COMPOSE --env-file /opt/beachrex/envs/.env.uat exec -T api_uat php artisan storage:ascii-paths --delete-old --dry-run
$COMPOSE --env-file /opt/beachrex/envs/.env.uat exec -T api_uat php artisan storage:ascii-paths --delete-old --confirm-delete
```

- Refuses if there is a collision, an old key without a copy, or a non-ASCII DB value left in the env it runs in. Run it from uat, so the uat DB is checked; dev was checked in step 5.
- After it: `--inventory` reports 0 non-ASCII keys. Then the cleanup PRs (TASK-002 §5) remove the Imagor bypass in web-client and MCP.

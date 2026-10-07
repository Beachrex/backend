# Runbook — ASCII-only storage paths (TASK-002)

Spec: [`docs/tasks/TASK-002-ascii-storage-paths.md`](../tasks/TASK-002-ascii-storage-paths.md).
Command: `php artisan storage:ascii-paths`.

The `beachrex` Spaces bucket is **shared by dev, uat and prod**. Steps run in this order, and nothing is
deleted until every environment DB is updated and verified.

| Step | Flag | Changes anything? | Status |
|---|---|---|---|
| 1. Inventory | `--inventory` | No (read-only) | **Built** |
| 2. Copy keys + rewrite vtour references | `--copy` | Adds new keys to Spaces | Planned |
| 3. Update DB paths (per env) | `--update-db` | DB rows | Planned |
| 4. Caches + search reindex | — | Caches | Planned |
| 5. Verify | — | No | Planned |
| 6. Delete old keys (owner confirms) | `--delete-old` | Deletes keys | Planned |

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

### CSV columns

| Column | Meaning |
|---|---|
| `source` | `spaces` (a key), `db` (a column value) or `vtour_ref` (a tour text file that references non-ASCII names) |
| `location` | The key, or `table.column#id` (JSON columns add `:$.json.path`) |
| `old` / `new` | Current value and its ASCII form (`App\Support\AsciiPath::fromPath`) |
| `form` | `NFC`, `NFD` or `mixed`. Keys were mostly uploaded in NFD, URLs are built in NFC |
| `issue` | `collision` (two old keys → one new key, or the new key already exists) or `rewrite_refs` |
| `detail` | Collision partners, or the non-ASCII names found in a vtour file |

**Collisions must be resolved before step 2.** Typical case: the same file exists in NFC and NFD form.

# TASK-002 — ASCII-only storage paths (no č, š, ž, ć … in Spaces keys or the DB)

Depends on: —
Requested by the owner, 2026-10-07: "on DigitalOcean Spaces rename all folders and files with šumniki
(čšžć → cszc), fix the DB, make it work, and never store šumniki again, also for newly added beaches."

## Why
Object keys like `img/beaches/hr-114-o-brač/hr-114-o-brač-sl17.jpg` break image delivery:
- Imagor (`s3.beachrex.com`) does not serve UTF-8 keys reliably; the same URL works on one request and fails on the next.
- Keys were uploaded in **NFD** form (`c` + U+030C), while URLs are usually built in NFC (`%C4%8D`), so they don't match.
- Workarounds exist in several places:
  - web-client `buildCdnUrl` (bypasses Imagor with NFD encoding);
  - MCP `cdn()` (the same rule, 2026-10-07);
  - the 360° tour URL builder.
  
  Each new client has to rediscover the problem.

## Goal
Every object key in the `beachrex` Spaces bucket and every stored path in the DB is ASCII. New uploads
cannot introduce non-ASCII keys. After the migration, the Imagor bypass workarounds can be removed.

## Scope

### 1. Inventory (read-only) — `php artisan storage:ascii-paths --inventory`
- List every Spaces key under `public/img/` that contains non-ASCII characters, in NFC or NFD form.
- List every DB value that holds a path or folder:
  - `beach.folder_name`
  - `media.file_path`
  - `countries.image_path`
  - any other `*_path` / `*_url` / `folder_name` column (grep migrations; check region/island/city/article/badge images)
- For 360° tours (`…/vtour/`): list `tour.xml` and other text files that **reference** non-ASCII file names.
- Output a CSV mapping `old_key → new_key`, with collision detection (two old keys mapping to the same new key).
  Collisions must be resolved before anything moves.

### 2. Transliteration rule (one function, unit-tested)
- `App\Support\AsciiPath::fromPath(string): string` normalises to NFC, then transliterates:
  - č→c, ć→c, š→s, ž→z, đ→dj, plus the uppercase forms;
  - other Latin diacritics via `Str::ascii` (ä→a, ö→o, ü→u, é→e, ñ→n, ß→ss …);
  - Cyrillic and Greek transliterated by `Str::ascii`.
- Lowercase is kept as is. Spaces become `-`. Characters other than `[A-Za-z0-9._/-]` become `-`. Repeated `-` collapse to one.
- Path separators are preserved. Must be **idempotent** (`f(f(x)) == f(x)`).
- Owner rule from the request: **čšžć → cszc**.

### 3. Migration (`--copy`, `--update-db`, `--delete-old`, each with `--dry-run`)
The bucket is **shared by dev, uat and prod**, so the order matters:
1. `--copy`: server-side copy of every old key to the new key, with ACL `public-read` like today. Old keys stay, so nothing breaks.
2. Rewrite references inside vtour text files (`tour.xml`, `*.html`, `*.js` in `vtour/`) to the new names, then upload them.
3. `--update-db`: update the path columns in **every** environment DB (dev, uat; prod is created from uat). Run once per env. Idempotent.
4. Clear caches: `cache:clear`, Meilisearch reindex (`search:index`), and the Imagor/CDN cache if applicable.
5. Verify: every DB path resolves to an existing key (`HEAD`), and a sample of beach pages renders images.
6. `--delete-old`: only after every env DB is updated and verified, delete the old non-ASCII keys. Owner-confirmed.

### 4. Prevention (no new non-ASCII paths)
- `Beach::folder_name` gets a mutator that applies `AsciiPath`, plus validation in the Filament `BeachResource` form (`TextInput::make('folder_name')`).
- Filament `MediaRelationManager` uploads use a filename from `AsciiPath`, not the original name. `MediaService` already uses `Str::slug`; add a test.
- Every path written by code goes through `AsciiPath`. Add a DB-level guard test: a scan test that fails if any seeder or factory produces non-ASCII paths.
- Mobile app uploads (`img/beaches_mobile/`): check where they come from. If outside this repo, document the rule for the app.

### 5. Cleanup after the migration (separate PRs)
- web-client: `buildCdnUrl` can drop the non-ASCII bypass and always use Imagor; keep a test that rejects non-ASCII paths.
- MCP: `cdn()` bypass can be removed.
- `docs/features/image-url-encoding-handoff.md`: mark it historical.

## Decisions already made
- Transliteration as above (owner: čšžć → cszc).
- Copy first, delete last. No step may break images that are currently working.

## Tests
- Unit: `AsciiPath`: Croatian/Slovenian letters, NFD input, idempotency, separators, collisions.
- Feature: the inventory finds NFC and NFD keys (fake S3 disk); `--dry-run` changes nothing; `--update-db` rewrites only matching rows and is idempotent; collision → abort.
- Prevention: saving a beach with `folder_name = "hr-114-o-brač"` stores `hr-114-o-brac`; a Filament media upload with `Plaža Čiovo.jpg` stores an ASCII key.

## Owner steps (server / irreversible)
- Before step 3: run a `db:backup` on uat.
- Run `--inventory` and review the CSV.
- Run the migration steps on the VPS via `docker compose exec` (the runbook will be written with the task).
- Confirm `--delete-old`.

## Must not touch
- Image files' content. Only keys and references change.

## Acceptance criteria
- Inventory reports 0 non-ASCII keys and 0 non-ASCII DB paths on dev and uat.
- The Zlatni rat image URL works through Imagor on 10 consecutive requests:
  `https://s3.beachrex.com/unsafe/fit-in/800x800/img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg`
- New beaches and uploads cannot store non-ASCII paths (tests).
- Runbook, `DEV_HANDOFF.md` and HANDOFF are updated.

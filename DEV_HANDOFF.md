# Development Handoff / Change Log


## 📋 Documentation Rules

**IMPORTANT:** This file MUST be updated whenever making changes to the API or codebase. Follow these rules:

### When to Update This File:
1. ✅ **API Changes** - Any modification to API endpoints, request/response structures, or parameters
2. ✅ **New Features** - Any new functionality added to the application
3. ✅ **Bug Fixes** - Significant bug fixes that affect API behavior or data structure
4. ✅ **Database Changes** - Schema changes, new tables, or modified relationships
5. ✅ **Breaking Changes** - Any change that could break existing integrations

### Required Updates for API Changes:
When modifying API responses or adding new fields, you MUST update:

1. **Code Files:**
   - ✅ Resource classes (`app/Http/Resources/*.php`)
   - ✅ Service classes (`app/Services/*.php`)
   - ✅ Controller classes (`app/Http/Controllers/Api/**/*.php`)

2. **Swagger/OpenAPI Documentation:**
   - ✅ Schema files (`app/Swagger/Schemas/*.php`)
   - ✅ Controller annotations (`@OA\Response`, `@OA\Property`, etc.)
   - ✅ Regenerate docs: `./vendor/bin/sail artisan l5-swagger:generate`

3. **This Handoff File:**
   - ✅ Add new dated section at the top
   - ✅ Document what changed and why
   - ✅ Include code examples
   - ✅ List affected endpoints
   - ✅ Add testing instructions

### Documentation Format:
```markdown
## YYYY-MM-DD - Brief Title

### Overview
Brief description of the change

### Changes Made
- File 1: What changed
- File 2: What changed

### Affected Endpoints
- `/api/v1/endpoint` - Description

### Testing
How to test the changes
```

---

## 2026-10-08 - TASK-002: Old Keys Deleted; MCP Images Only via Imagor

### Overview
Runbook step 6 ran on dev (owner, after an explicit confirmation): `--delete-old --dry-run` listed 10 685 keys, `--delete-old --confirm-delete` printed `Deleted 10685 old key(s).` The control inventory afterwards: 206 174 keys scanned, 0 non-ASCII keys, 0 collisions, 0 non-ASCII DB values, 0 vtour files that reference a migrated key. TASK-002 §5 cleanup for the MCP server follows.

### Changes Made
- `mcp-server/src/api-client.ts` `cdn()`: the Spaces bypass for non-ASCII paths is removed. Every image URL goes through Imagor (`s3.beachrex.com`). A non-ASCII path gets an empty URL; empty gallery entries are dropped.
- `cfg().cdnAssets` and `BEACHREX_CDN_ASSETS` (`mcp-server/.env.example`) are removed.
- `mcp-server/test/api-client.test.ts`: the bypass test is replaced by one that fails on the old code.
- Docs: runbook step 6 and TASK-002 status, ADR "Non-ASCII Image Paths Get No URL", HANDOFF.

### Affected Endpoints
None in the Laravel API. MCP tools: `image_url`, `image_thumb` and `media[].url` in search and beach details.

### Testing
`cd mcp-server && npm test` (35 pass); on the old `api-client.ts` the new test fails (1 of 35).

---

## 2026-10-08 - TASK-002: Migration Run on Dev; Copy Check Uses Listing ETags

### Overview
The owner ran TASK-002 steps 2–5 on dev (2026-10-08):
- `--copy` copied 10 685 keys and rewrote 399 vtour files at their ASCII copies. The old keys were kept.
- A `pg_dump` of `beachrex_dev` was taken before `--update-db`.
- `--update-db` updated 2 413 DB values and skipped 0.
- `cache:clear` and `search:index` ran.
- The control inventory shows 0 non-ASCII DB values, all 10 685 old keys `copied`, and 0 collisions.
- The acceptance URL `s3.beachrex.com/unsafe/fit-in/800x800/img/beaches/hr-114-o-brac/hr-114-o-brac-sl17.jpg` works.

`--update-db` and the control inventory each spent 5–15 silent minutes checking every copy with one HEAD request per key.

### Changes
- `app/Console/Commands/AsciiStoragePaths.php`: the listing keeps each key's ETag (S3 `ListObjectsV2` returns it), and `sameFile()` uses it. Disks without ETags in the listing (local) still call `checksum()`.
- `tests/Feature/AsciiStoragePathsS3ReadTest.php`: a new test checks that an identical copy is `copied` and a different file is `collision`, without any HeadObject call. The mocked S3 handler throws on calls it does not expect.

### Affected endpoints
None (artisan command only).

### Testing
`php artisan test --filter=AsciiStoragePaths` → 22 passed. The new test fails on the old code, which makes a HeadObject call the mock rejects.

---

## 2026-10-08 - TASK-002: Inventory Memory Fix (vtour contents not kept)

### Overview
The owner's dev run (after the parallel reads) listed 206 174 keys in ~10 s. It then read 32 000 of 51 350 vtour text files in 72 s and stopped with `Allowed memory size of 536870912 bytes exhausted`. The cause: every vtour file with any non-ASCII text (titles, script comments) was kept in memory twice, as the original and the rewritten content.

### Changes
- `app/Console/Commands/AsciiStoragePaths.php`:
  - `vtourRewrites` keeps only key, target, up to 10 sample tokens, the token count and a `changed` flag. The flag means the file references a migrated key segment.
  - `--copy` re-reads only the changed files when it rewrites them.
  - S3 reads go in chunks of 100.
  - The summary has a new row: "of which reference a migrated key (rewritten by --copy)".
- `tests/Feature/AsciiStoragePathsInventoryTest.php`: `test_does_not_keep_vtour_file_contents_in_memory` uses 150 non-ASCII tour files of ~0.6 MB each. Peak growth must stay under 40 MB. On the old code it grew by 88 MB and the test fails.

### Affected endpoints
None (artisan command only).

### Testing
`php artisan test --filter=AsciiStoragePaths` → 21 passed. The copy/rewrite tests in `AsciiStoragePathsMigrationTest` still pass with the re-read.

---

## 2026-10-08 - TASK-002: Parallel vtour Reads in the Inventory

### Overview
With the progress output (below), the owner's dev run showed that listing the bucket takes 17 s for 206 174 keys. The slow part came next, with no output: the command read every vtour text file (`tour.xml`, `tour.html`, `tour.js`, `plugins/*.js`, `skin/*` of every tour), one GET at a time.

### Changes
- `app/Console/Commands/AsciiStoragePaths.php`: on S3 disks the vtour text files are fetched in parallel through `Aws\CommandPool`. A new `--concurrency=N` option sets the number of parallel GETs (default 32). Keys are read in chunks of 500 so memory stays flat. Progress lines show `Reading N vtour text files …` and `read X/N vtour text files (Ns)`. A failed GET aborts with the key in the message. Other disks (local, tests) still read sequentially. `--copy` uses the same reader.
- `tests/Feature/AsciiStoragePathsS3ReadTest.php` (new): drives the S3 path through a mocked S3 handler. It checks 602 files read once each, no image downloads, the vtour reference found, and the progress lines.

### Affected endpoints
None (artisan command only).

### Testing
`php artisan test --filter=AsciiStoragePaths`: 20 passed. The new test fails on the previous command (`Output does not contain "Reading 602 vtour text files"`).

---

## 2026-10-08 - TASK-002: Progress Output for the inventory scan

### Overview
The owner's first `storage:ascii-paths --inventory` run on dev printed only "Listing keys under 'public/img/' …" and then hit a timeout. Listing the whole bucket (tour tiles included) takes long, and the command printed nothing until the listing was done.

### Changes
- `app/Console/Commands/AsciiStoragePaths.php`: new `--progress=N` option (default 10000, `0` = off). It prints `listed N keys (Ns, N MB) … last: <key>` while listing and `listing done: N keys in Ns` at the end. Nothing else changes; the command is still read-only for `--inventory`.
- `docs/runbooks/ascii-storage-paths.md`: how to run the scan detached (`exec -d … > /tmp/inventory-dev.log`) and follow the log, so an SSH or terminal timeout does not kill it.

### Affected endpoints
None (artisan command only).

### Testing
`php artisan test --filter=AsciiStoragePaths`: 19 passed. The new `test_listing_prints_progress_lines` fails on the old command (`The "--progress" option does not exist`).

---

## 2026-10-07 - AI Chat Understands Any Language and Queries Real Beaches (TASK-003)

### Overview
`POST /api/v1/ai/chat` used English keywords for the intent and for 3 countries. Without GPS it usually had no beaches to recommend, and it never queried places, surface or facilities. It now extracts the query with structured output, resolves the place, and recommends beaches the code selected from the DB. ADR "Chat: Code Queries, LLM Understands and Narrates".

### Changes Made
- `App\Services\AI\Chat\QueryUnderstandingService`:
  - one LLM call, temperature 0, strict `json_schema`;
  - extracts intent, place, country, beach types, facilities, user types, activities, near_me, days, beach names and language;
  - code enums come from the DB (cached 1 h);
  - uses the last 4 messages;
  - returns null on any failure.
- `App\Services\AI\Chat\PlaceResolver`: island, city or region from translated names and slugs in all locales, ignoring diacritics and generic words ("otok"). Handles inflections such as "Braču" and "Pagu". On a name clash the island wins. Cached index.
- `ItineraryContextBuilderService::fetchChatCandidates()`:
  - optional country, place, beach IDs, attributes and GPS radius (`geography`);
  - relaxes user types, activities, facilities, then beach types, only when nothing matches;
  - accessibility is never relaxed;
  - sorted by score, distance or popularity.
- `ContextBuilderService`: a candidate path that keeps slugs and `main_image` and adds place, type, facilities, suitable-for and distance to the prompt. `MAX_BEACHES` is public.
- `ChatController`:
  - plan enforcement uses the understood intent and days;
  - the answer is written in the user's language;
  - `filters_applied` comes from the code;
  - the understanding tokens and cost are added to the session and the `ai_usages` row;
  - the keyword fallback uses word boundaries ("stop" no longer means "top");
  - **bug fix**: the conversation history was out of order (same timestamp), so it is now ordered by `id` too.
- OpenAPI: `filters_applied` uses real param names; new `metadata.understanding`, `language` and `assumptions`. `api-docs.json` is regenerated. The web client does not read `filters_applied`.

### Affected endpoints
`POST /api/v1/ai/chat`: same top-level fields; `filters_applied` keys changed; 3 new metadata fields.

### Testing
```bash
php artisan test tests/Feature/AI/ChatQueryUnderstandingTest.php tests/Feature/AI/PlaceResolverTest.php
```
18 tests. Deliberate breaks (each caught):
- place filter ignored;
- model `filters_applied` kept;
- no inflection matching;
- accessibility relaxable.

Live check after the dev deploy: see `docs/tasks/TASK-003.feedback.md`.

---

## 2026-10-07 - Real Sea Temperature (Open-Meteo Marine)

### Overview
`sea_temperature` in every weather endpoint was the OpenWeatherMap **air** temperature. The owner chose option B on 2026-10-07: a real sea surface temperature.

### Changes Made
- `WeatherService::getSeaTemperature()` calls the Open-Meteo Marine API (`current=sea_surface_temperature`):
  - rounded to 0.1 °C and cached for 3 h;
  - "no marine data" (lakes, inland points) is cached as null;
  - API errors return null and are not cached;
  - timeout 5 s.
- `ProvidesWeather` (beach, country, region, island and city weather) returns that value. The frontend `SeaTempWidget` shows "--°" for null.
- New optional env var `OPEN_METEO_API_KEY` (`config/services.php` `open_meteo.api_key`):
  - empty → free endpoint `marine-api.open-meteo.com`, which Open-Meteo allows for **non-commercial use only**;
  - set → commercial endpoint `customer-marine-api.open-meteo.com`.
- OpenAPI `sea_temperature` description updated; `api-docs.json` regenerated.
- NOT RUN: a live call to Open-Meteo, because the agent sandbox has no outbound access (proxy 403). The response shape follows the Open-Meteo docs and is covered by `Http::fake` tests.

### Affected endpoints
`GET /api/v1/beach-details/{identifier}/weather` and the country, region, island and city `/weather` endpoints: `sea_temperature` is now the sea, not the air.

### Testing
```bash
php artisan test tests/Feature/SeaTemperatureTest.php tests/Feature/BeachWeatherSeaTemperatureTest.php
```
On the old `ProvidesWeather`, the endpoint test fails: `Failed asserting that 28 matches expected 21.4`.

---

## 2026-10-07 - API Fixes: View Counts, Filter Aliases, `/beaches` Docs, 360° Tour Path

### Overview
Four of the API issues found while fixing the MCP (HANDOFF open items). `sea_temperature` is not changed: today it is the air temperature, and setting it to `null` would show "--°" on every beach page. That is a product decision for the owner.

### Changes Made
- **View counts**: `GET /beach-details/{identifier}` no longer increments `beach_views` when the request has an `X-Beachrex-Client` header. The MCP server now sends `X-Beachrex-Client: mcp`, so AI lookups no longer inflate "most visited". The web client sends no such header and is counted as before.
- **Filter aliases**: `GET /beaches/filters` accepts `country_code`, `region_code` and `island_code` as aliases of `country`, `region` and `island`, matching `/beaches` and the MCP.
- **`/beaches` OpenAPI**: the region, island and city params are documented with their real names `region_code`, `island_code` and `city_code` (`applyFilters` never read `region`/`island`/`city`). The `country_code` alias is documented. `api-docs.json` is regenerated. The frontend already uses the `*_code` names; `yarn generate:api` in web-client picks up the corrected names next time.
- **360° tour URL** in search/map results (`PostGISService`) respects `mobile_upload` (`img/beaches_mobile/…`), like `BeachDetailsResource`.

### Affected endpoints
- `GET /api/v1/beach-details/{identifier}`: new optional header `X-Beachrex-Client`.
- `GET /api/v1/beaches/filters`: new aliases.
- `GET /api/v1/beaches` and the nearby/radius endpoints: `spheres_360[].tour_url` is fixed for mobile uploads.

### Testing
```bash
php artisan test tests/Feature/BeachDetailsApiTest.php tests/Feature/FilterAliasApiTest.php tests/Feature/PostGISSphereTourUrlTest.php
cd mcp-server && npm test
```
Against the old backend code, 3 of the new tests fail: view count, alias, mobile tour URL.

---

## 2026-10-07 - MCP: "Zlatni rat" Is an Exact Match for "Beach Zlatni Rat"

### Overview
Owner feedback after a live test: `get_beach_details("Zlatni rat")` said "No beach is named exactly" and then showed "Beach Zlatni Rat". Many legacy names carry the English prefix "Beach" in every locale.

### Changes Made
- `mcp-server/src/api-client.ts` `nameKey()`: besides case and diacritics, it now ignores one leading or trailing generic beach word:
  - beach, plaža/plaz, plazha, plage, playa, praia, spiaggia, strand, paralia;
  - a name that is only that word keeps it, and inner words are kept ("Long Beach Bay").
- Tests in `mcp-server/test/api-client.test.ts`: 34/34 pass. On the old `nameKey`, the 2 new tests fail.

### Testing
```bash
cd mcp-server && npm run build && npm test
```

---

## 2026-10-07 - Fix: Beach Distances Were ~37% Too Long (EPSG:3857 → geography)

### Overview
`PostGISService` and `BeachDetailsController` measured distances after `ST_Transform(…, 3857)`. Web Mercator stretches lengths by 1/cos(latitude), so at Croatian latitudes (~43°) every `distance_km` came out ~37% too long and radius searches missed beaches. Example: Split riva → Zlatni rat was 44.19 km instead of 32.08 km.

### Changes Made
- Every `ST_Distance`/`ST_DWithin` on `beach.location` now uses `::geography`, which measures in metres on the spheroid:
  - `getBeachesWithinRadius`, `getNearestBeaches`, `calculateDistance`, `calculateCentroid` (radius filter);
  - previous/next beach in `BeachDetailsController`.
- Migration `2026_10_07_180000_add_geography_index_to_beach_location`: GIST expression index on `(location::geography)`, so `ST_DWithin` on geography can use an index. Additive and backward compatible.

### Affected endpoints
Every endpoint that returns `distance_km` or filters by radius (nearby beaches, map radius search, MCP `find_beaches_nearby`, previous/next beach). Values are now real distances, about 27% smaller than before at these latitudes. A radius now includes beaches that were wrongly excluded before.

### Testing
```bash
php artisan test tests/Feature/PostGISDistanceTest.php
```
Against the old code, 2 of the 3 tests fail (44.19 km vs ≈32.08 km).

---

## 2026-10-07 - TASK-002 Step 3: `--copy`, `--update-db`, `--delete-old` (Built, Not Run)

### Overview
The migration steps of `storage:ascii-paths`, each with `--dry-run`. Nothing has been run against Spaces or an env DB. The owner runs them per `docs/runbooks/ascii-storage-paths.md` after reviewing the inventory.

### Changes Made
- `app/Console/Commands/AsciiStoragePaths.php`:
  - **Key plan**: one listing with sizes; each non-ASCII key gets a status:
    - to copy;
    - `copied` (the target holds the same file: same size and same checksum/ETag);
    - `collision` (two old keys map to one target, or a *different* file is already at the target).
  - `--copy`: aborts on any collision. Otherwise it copies server-side with `public` visibility and keeps the old keys, then rewrites vtour text files:
    - only references that match migrated folder or file names change, so titles stay;
    - a tour at a non-ASCII key is rewritten at its ASCII copy;
    - a tour at an ASCII key is rewritten in place, with a local backup.

    Idempotent.
  - `--update-db`: refuses while any key is not copied. In one transaction it rewrites non-ASCII values in path-like columns, including strings inside JSON columns. Values with `://` (full URLs) are skipped and listed. Idempotent.
  - `--delete-old`: needs `--confirm-delete`. It refuses on collisions, on keys without a copy, or while the current env DB still has non-ASCII paths.
  - Exactly one step per run.
- The inventory CSV `issue` column can now also be `copied` or `skipped_url`.
- Runbook steps 2–6 written.

### Affected endpoints
None until the owner runs the steps. After that, image paths in API responses become ASCII.

### Testing
```bash
php artisan test tests/Feature/AsciiStoragePathsMigrationTest.php
```
12 tests. Deliberate breaks (each caught):
- collision abort removed;
- vtour rewrite disabled;
- URL skip removed;
- delete-old DB guard removed;
- size-only comparison.

---

## 2026-10-07 - TASK-002 Step 2: No New Non-ASCII Storage Paths (Prevention)

### Overview
New and changed storage paths are stored ASCII-only. Legacy values are not touched until the migration (ADR "ASCII-only Storage Paths: Convert New Values Only").

### Changes Made
- `app/Models/Traits/HasAsciiPaths.php`: on `saving`, it applies `AsciiPath::fromPath` to dirty path attributes. Used by:
  - `Beach` (`folder_name`);
  - `Media` (`file_path`, `file_name`);
  - `City`, `Region`, `Island`, `Continent` (`main_image`);
  - `Country` (`image_path`).
- Filament: every `FileUpload` (city, region, island, country and continent images; beach media) names files with `AsciiPath::uploadFileName()` (ULID + ASCII extension). The `folder_name` helper text explains the rule.
- `AsciiStoragePathsInventoryTest` now writes legacy rows with `DB::table`, as the old data was written, because the model guard converts them.
- Known gap until the migration: uploads to a beach whose legacy `folder_name` is non-ASCII still go into that folder, because the main image URL is built from `folder_name`. The inventory reports them.

### Affected endpoints
None. Admin saves of beaches, media and geographic images store ASCII paths for new values.

### Testing
```bash
php artisan test tests/Feature/AsciiPathPreventionTest.php
```
Deliberate breaks (each caught):
- trait disabled → 4 failures;
- dirty check removed → the legacy-value test fails;
- one `FileUpload` unwired → the source-scan test fails.

---

## 2026-10-07 - TASK-002 Step 1: `AsciiPath` + Read-Only Inventory of Non-ASCII Storage Paths

### Overview
First part of TASK-002 (ASCII-only storage paths). It adds the transliteration rule and a **read-only** inventory command. Nothing on Spaces or in the DB changes yet. The owner runs the inventory on the VPS and reviews the CSV before any copy step is built and run.

### Changes Made
- `app/Support/AsciiPath.php`: `fromPath()` normalises to NFC, then čšžć → cszc and đ → dj (plus the uppercase forms). Other scripts go through `Str::ascii`. Per segment, anything outside `[A-Za-z0-9._-]` becomes `-` and repeated `-` collapse. Case and `/` are kept, and the function is idempotent. Also `isAscii()` and `unicodeForm()` (NFC/NFD/mixed).
- `php artisan storage:ascii-paths --inventory` (`app/Console/Commands/AsciiStoragePaths.php`):
  - scans Spaces keys under `public/img/`;
  - scans path-like text/JSON DB columns, discovered from the schema (`beach.folder_name`, `media.file_path`, `media.file_name`, `*.main_image`, `countries.image_path`, …);
  - finds vtour text files that reference non-ASCII names;
  - writes an `old → new` CSV and flags collisions (two old keys map to one new key, or the new key already exists).
- Runbook: `docs/runbooks/ascii-storage-paths.md`.

### Affected endpoints
None.

### Testing
```bash
php artisan test tests/Unit/AsciiPathTest.php tests/Feature/AsciiStoragePathsInventoryTest.php
```

---

## 2026-10-07 - MCP: Deploy Actually Replaces the Legacy Process; Image URLs With Šumniki

### Overview
After Beachrex/backend#8 the dev MCP still served the **old** code. The deploy log showed `beachrex-mcp-dev` with 36 restarts and 0 s uptime, a crash loop. The legacy `beachrex-mcp` process is not in the deploy user's pm2 (`not found`), so it most likely runs under root's pm2 (started by hand before CI managed it) and still holds port 3100. The deploy reported success anyway. Separately, image URLs with šumniki (`…/hr-114-o-brač/…`) opened only sometimes, because Imagor does not serve UTF-8 keys reliably.

### Changes Made
- `.github/workflows/deploy.yml` (MCP block):
  - dev deploys also run `sudo -n pm2 delete beachrex-mcp` + `pm2 save --force` for root's pm2;
  - after `startOrReload`, the deploy **verifies** that `http://127.0.0.1:<port>/health` reports `"env":"<env>"`. The old process doesn't report it. If the check fails, the deploy prints the health response, the port owner (`ss -ltnp`) and the pm2 logs, then **fails**.
  - The expanded remote script was checked with `bash -n`; the health check was tested against a locally running MCP and against the old response format.
- `mcp-server/src/api-client.ts` `cdn()`: paths with non-ASCII characters bypass Imagor and link the original on Spaces (`BEACHREX_CDN_ASSETS`, default `https://beachrex.fra1.digitaloceanspaces.com/public/`), with NFD-encoded segments. This is the same rule as web-client `buildCdnUrl`. The root fix is TASK-002 (ASCII-only storage paths).
- Tests: `mcp-server/test/api-client.test.ts`, image URL cases. 32/32 pass; on the old `api-client.ts` the non-ASCII case fails.

### Testing
```bash
cd mcp-server && npm run build && npm test
```
After the dev deploy, the "Build and start on VPS" log ends with `MCP server rebuilt, restarted and verified on port 3100`. If it ends with `ERROR: MCP did not come up…`, the printed `ss` line shows which process holds the port.

---

## 2026-10-07 - AI Itineraries Rebuilt: Real Beaches, Geographic Days, LLM Writes Narrative Only (TASK-001)

### Overview
The owner reported very poor itineraries. The root causes (`docs/tasks/TASK-001-itinerary-quality.md`) were:
- filters on nonexistent columns;
- no beach data or coordinates in the prompt;
- no geographic logic;
- no JSON mode, a fixed token limit, and gpt-3.5-turbo.

Now the **code builds the plan** and the **LLM only writes the text** (ADR 2026-10-07). pgvector is deferred (same ADR).

### Changes Made
- `ItineraryContextBuilderService` (rewritten): real filters and translations, scoring, relaxation rules (accessibility is never relaxed).
- `Itinerary\ItineraryPlanner` (new): k-means day clusters, nearest-neighbour order, haversine distances, max km per day.
- `ItineraryService` (rewritten): skeleton → LLM narrative (JSON mode, scaled `max_tokens`, language name, one retry) → merge with DB facts. If the LLM fails, a deterministic fallback text is used (`metadata.narrative="fallback"`).
- `ItineraryController`: **422 `not_enough_beaches`** (`found`, `needed`) when fewer beaches match than days. No credit is used.
- `ChatCompletionRequest::$responseFormat`, passed by `OpenAIProvider` as `response_format`.
- `config/ai.php`: the default `OPENAI_MODEL` is now `gpt-4o-mini`.

### Affected Endpoints
- `POST /api/v1/ai/itineraries`
  - The response schema is unchanged at the top level.
  - New fields: `beach.type`, `beach.suitable_for`, `beach.latitude`, `beach.longitude`, `logistics.notes`, `metadata.narrative`, `metadata.candidates_considered`.
  - `beach.surface` = localized beach-type name; `beach.distance_km` = straight-line km from the previous stop; `logistics.estimated_travel_km` is computed.
  - New 422 `not_enough_beaches`.

### Testing
```bash
./vendor/bin/sail artisan test --filter='ItineraryQualityTest|ItineraryControllerTest|ItineraryPlannerTest'
```
Real DB with 12 seeded beaches in 3 areas; only the LLM is mocked. Details and deliberate breaks: `docs/tasks/TASK-001.feedback.md`.
## 2026-10-07 - MCP Server Fixes + `country_code` Filter Alias

### Overview
Live testing of the Beachrex MCP server on dev found it was broken in several ways:
- `get_beach_filters` crashed;
- `search_beaches` with `country_code=HR` returned beaches from every country;
- lookup by name never worked ("zlatni rat" → not found);
- weather returned all zeros;
- nearby results had flat, broken beach URLs.

The MCP server is rewritten against the real API shapes. The API now also accepts `country_code` wherever it accepted `country`.

### Changes Made
- **API:** `BeachFilterService::applyFilters()` and `buildRawFilters()` accept `country` **or** `country_code`. `/beaches` used to read only `country`, so the MCP's `country_code` was ignored. Regression tests are in `tests/Unit/BeachFilterServiceRawFiltersTest.php`; on the old code the two `country_code` cases fail.
- **MCP** (`mcp-server/`, v1.1.0; details in `mcp-server/README.md`):
  - New tool `find_beach_by_name`, backed by `/api/v1/search` (Meilisearch). `get_beach_details` and `get_beach_weather` resolve names the same way: ID → slug → search.
  - `search_beaches` and `find_beaches_nearby` take attribute filters (beach_type, location_infrastructure, user_type, sporting_activity, …, the same params as `BeachFilterService`). `get_beach_filters` returns the codes.
  - `find_beaches_nearby` now uses `/spatial/beaches/within-radius`, which supports a radius and filters and returns full hierarchical URLs. It corrects the API's EPSG:3857 distance error client-side (see the open item below).
  - Weather is mapped from the real response; when there is no data it says "weather unavailable" instead of zeros.
  - Details show localized region, island and city names, the description, facilities, activities and user types. "0 views" is hidden.
  - All 19 locales are supported (`si`→`sl` for the API, `si` in URLs).
  - `ecosystem.config.cjs` is per environment via `MCP_ENV`: `beachrex-mcp-{dev,uat,prod}` on ports 3100/3101/3102. Before this, every deploy restarted the dev process.
  - Tests: `npm test` (30 tests; `fetch` mocked with fixtures derived from the PHP Resources).
- **CI** (`.github/workflows/deploy.yml`):
  - New `mcp-test` job (`npm ci`, build, typecheck, tests) on PRs and pushes; the deploy needs it.
  - The deploy runs `MCP_ENV=<env> pm2 startOrReload ecosystem.config.cjs`. A dev deploy deletes the legacy `beachrex-mcp` process once.

### Affected Endpoints
- `GET /api/v1/beaches`, `GET /api/v1/spatial/beaches/*`: `country_code` now filters (same as `country`).

### Testing
```bash
./vendor/bin/sail artisan test --filter=BeachFilterServiceRawFiltersTest
cd mcp-server && npm ci && npm run build && npm test
```
After the dev deploy: in Claude (with the MCP connected) ask for "Zlatni rat", "beaches in Croatia" and "sandy beaches with a lifeguard near Split".

---

## 2026-10-07 - Ops Safety Net: Daily DB Backups, Scheduler, Health Endpoint, Log Rotation, PR CI

### Overview
Before this change the VPS had no database backups, no running Laravel scheduler, unbounded Docker logs, no health endpoint and no CI on pull requests. All of it is now in the repo as code. Enabling backups needs an owner step (a private bucket and key); see `docs/runbooks/restore-backup.md`.

### Changes Made
- `app/Console/Commands/BackupDatabase.php` (new): `php artisan db:backup [--keep-days=N] [--no-prune]`.
  - Runs `pg_dump -Fc` with the password passed via `PGPASSWORD`, not on the command line, and checks the `PGDMP` header.
  - Uploads to the private `backups` disk as `<prefix>/<db>-YYYYMMDD-HHMMSS.dump` and verifies the size.
  - Writes `storage/app/backup-last-success.txt` and prunes its own files older than the retention period. On error it fails loudly and uploads nothing.
- `config/backup.php` (new), plus the `backups` disk in `config/filesystems.php` (s3 driver, DO Spaces, `visibility: private`).
- `routes/console.php`: `db:backup` runs daily at `BACKUP_TIME` (02:30 UTC), `withoutOverlapping`, only when `BACKUP_ENABLED=true`.
- `app/Http/Controllers/Api/V1/HealthController.php` (new): `GET /api/v1/health`.
- `deploy/docker-compose.vps.yml`:
  - New `scheduler_{prod,uat,dev}` services that reuse the `beachrex/api:<env>` image and run `php artisan schedule:work`.
  - Log rotation (`json-file`, 10m × 5) on all app containers. `pgsql`/`redis` are left unchanged so a dev deploy never restarts the shared database.
- `.github/workflows/deploy.yml`:
  - The `test` job now also runs on pull requests (the deploy job is skipped). PR runs use their own concurrency group.
  - The deploy writes `REVISION` (the commit SHA) before rsync and recreates `scheduler_<env>` with `--no-deps`.
- `.env.example`: `BACKUP_*` variables (names only). `.gitignore`: `REVISION`.
- Docs: `docs/runbooks/restore-backup.md` (enable, restore, drill); `DEPLOYMENT_GUIDE.md` §9 updated.

### Affected Endpoints
- `GET /api/v1/health` (new, public). Returns `{status, revision, checks:{database, redis}, backup:{enabled, last_success_at}}`: **200** when healthy, **503** when the database or Redis is down.

### Testing
```bash
./vendor/bin/sail artisan test --filter='BackupDatabaseTest|HealthApiTest'
# BackupDatabaseTest runs the real pg_dump against the test DB: valid dump, retention prune
# (only its own files and prefix), failure with wrong credentials, schedule gated by BACKUP_ENABLED
curl -s https://dev-api.beachrex.com/api/v1/health | jq   # after deploy: revision = merge SHA
```
The compose and workflow changes can't be run in the agent sandbox (no Docker or SSH). They are verified by the dev deploy run.

---

## 2026-10-07 - Security: Admin-Only Write Endpoints + Locale, Filter and Cache Fixes

### Overview
Clean-up before further development. One security fix (anyone could create beaches or modify media) and three correctness fixes found while bringing this file up to date. Commits: ced7755, e20e284, cad55e2, 2f01f23.

### Changes Made
- **Security (ced7755):** `POST /api/v1/beaches` had no auth at all. The media write routes only required a Sanctum token, and any visitor can get one via `/register` or Google sign-in.
  - New `app/Http/Middleware/EnsureUserIsAdmin.php`, registered as the `admin` alias in `bootstrap/app.php`. It checks `users.is_admin` and returns `403 {"message":"Forbidden."}`.
  - `routes/api.php`: `POST /beaches` and the media writes moved into `auth:sanctum` + `admin`.
  - OpenAPI: added `security={{"sanctum":{}}}` and 401/403 responses; `storage/api-docs/api-docs.json` regenerated.
  - `UserFactory::admin()` state added. `MediaApiTest` now acts as an admin. New tests: `BeachCreateApiTest` and `MediaApiTest::non_admin_cannot_modify_media`.
- **Spatial filters (e20e284):** `BeachFilterService::buildRawFilters()` read `region_code`/`island_code` but `city`, while the spatial OpenAPI docs say `region`/`island`/`city`. All three now accept both spellings; the `*_code` name wins if both are sent. Test: `tests/Unit/BeachFilterServiceRawFiltersTest.php`.
- **Locales (cad55e2):** `SetLocaleFromHeader::SUPPORTED_LOCALES` is now a public constant and the single source of truth (19 DB codes, Slovenian = `sl`). It is now referenced by:
  - `FirstPageContentTranslation`: cache invalidation skipped da/pt/nl/el/sq, so admin edits in those languages stayed stale.
  - `FixDuplicateSlugs` (Filament page) and `beaches:fix-duplicate-slugs`: both used `si`, which never matches the DB, so Slovenian was skipped; the new languages were skipped too.
  - `TranslationCompletenessWidget`: "complete" meant exactly 14 translations.
  - `StaticPage` (it already had all 19 locales).
  - Test: `tests/Feature/FirstPageContentCacheTest.php`.
- **Cache rule (2f01f23):** `StaticPageController` caches `Resource::resolve()` arrays instead of Eloquent models. The response shape is unchanged. Test: `tests/Feature/StaticPageApiTest.php`.

### Affected Endpoints
- `POST /api/v1/beaches`: now **401** without a token and **403** for non-admins (previously public)
- `POST /api/v1/media/upload`, `PUT /api/v1/media/{id}`, `DELETE /api/v1/media/{id}`, `POST /api/v1/media/reorder`: now **403** for non-admins
- `GET /api/v1/spatial/beaches/{within-radius,bounding-box,nearest,density-grid,statistics}`: `region`, `island`, `city` and their `*_code` variants all filter
- `GET /api/v1/static-pages`, `GET /api/v1/static-pages/{slug}`: no change in output

No current client is affected. The frontend and MCP server never call the write endpoints or pass location filters to the spatial endpoints. Filament keeps writing directly through Eloquent.

### Testing
```bash
./vendor/bin/sail artisan test --filter='BeachCreateApiTest|MediaApiTest|BeachFilterServiceRawFiltersTest|FirstPageContentCacheTest|StaticPageApiTest'
# Full suite: 292 passed; only the 4 known env-dependent failures remain (PlanEnforcementTest, CountrySlugSupportTest)
```

---

## 2026-09-17 - 360° Sphere Tour Path Fix + Sphere Diagnostic/Fix Commands

### Overview
360° tours 404'd for beaches uploaded via the mobile app (e.g. Albania), because `tour_url` was always built under `img/beaches/` while mobile uploads live under `img/beaches_mobile/`. Fixed the URL builder and added two artisan commands to find and repair inconsistent sphere media records on DigitalOcean Spaces. Commits: 3776f3a, f9e3ea9 (2026-09-16), 330aa82, eb1e94b (2026-09-17).

### Changes Made
- `app/Http/Resources/BeachResource.php`, `app/Http/Resources/BeachDetailsResource.php`: vtour URL is now `img/{beaches|beaches_mobile}/{folder_name}/vtour/tour.html`, chosen by `beaches.mobile_upload`. Still only emitted when the beach has `beach_360_sphere` media.
- `app/Console/Commands/DiagnoseSphereTours.php` (NEW), `php artisan beaches:diagnose-spheres`:
  - For beaches with an active `beach_360_sphere` media record, checks that `tour.html` exists on the `spaces` disk (S3 API via `Storage::disk('spaces')->exists()`, not HTTP HEAD, because the CDN/Imagor endpoint does not serve HTML). Reports `mobile_upload` mismatches (tour exists under the other folder) and missing tours.
  - For active beaches without a sphere record, finds orphaned `tour.html` files and, after an interactive confirm, inserts `media` rows (`sphere_360` type). `file_path` is set to `{folder}-Panorama.jpg` only if that image exists, otherwise `''` (frontend falls back to the first gallery image).
- `app/Console/Commands/FixSphereThumbnails.php` (NEW), `php artisan beaches:fix-sphere-thumbnails`: sets `file_path = ''` on active sphere media whose panorama image is missing on Spaces.
- Both commands write to the DB. Run `php artisan cache:clear` afterwards, as the commands themselves say.

### Affected Endpoints
- `GET /api/v1/beaches`, `GET /api/v1/beaches/most-visited`, `GET /api/v1/beaches/{id}` (BeachResource): `media.spheres_360[].tour_url`
- `GET /api/v1/beach-details/{identifier}` (BeachDetailsResource): `spheres_360[].tour_url`

### Testing
```bash
./vendor/bin/sail artisan beaches:diagnose-spheres     # read-only unless you confirm the insert prompt
./vendor/bin/sail artisan beaches:fix-sphere-thumbnails
./vendor/bin/sail artisan cache:clear
# For a beach with mobile_upload=true, tour_url should start with img/beaches_mobile/
```
Both commands need working `spaces` disk credentials.

---

## 2026-09-11 - Fix: city_code / region_code / island_code Filters Were Ignored

### Overview
`BeachFilterService` checked `$request->has('city')`, `'region'` and `'island'`, but the documented API parameters are `city_code`, `region_code` and `island_code`. Those filters were silently ignored. For example, `?city_code=zadar` returned up to the default limit of random beaches instead of the 14 Zadar beaches. Commit 3edea3c.

### Changes Made
- `app/Services/BeachFilterService.php`
  - `applyFilters()` (Eloquent path): now reads `region_code`, `island_code` and `city_code`. Values are still parsed by `parseFilterValue()`, so single, comma-separated, code or numeric ID all work.
  - `buildRawFilters()` (raw SQL path used by PostGIS): now reads `region_code` and `island_code`. **The city filter in this method still read `city`, not `city_code`** (fixed 2026-10-07: both spellings accepted, see that entry).
  - `generateCacheKeySuffix()`: adds `country_code`, `region_code`, `island_code` and `city_code` to the cache-key params, so filtered results no longer share a cache key with unfiltered ones.

### Affected Endpoints
- `GET /api/v1/beaches`: `city_code`, `region_code` and `island_code` now filter (behaviour change; clients relying on the old unfiltered result will now get fewer rows)
- `GET /api/v1/spatial/beaches/within-radius`, `GET /api/v1/spatial/beaches/bounding-box`: `region_code` and `island_code` now filter. City filtering there still used `city` (fixed 2026-10-07).
- Old parameter names `region` / `island` / `city` are no longer honoured by `GET /api/v1/beaches`.

### Testing
```bash
curl "http://localhost/api/v1/beaches?city_code=zadar" | jq '.data | length'          # expect a small number (14 on UAT data)
curl "http://localhost/api/v1/beaches?region_code=dalmatia-zadar" | jq '.data | length'
```

---

## 2026-09-11 - Beachrex MCP Server (`mcp-server/`)

### Overview
New Node.js/TypeScript Model Context Protocol server that lets AI agents (Claude Desktop, ChatGPT, Cursor) query the Beachrex API. It lives in this repo under `mcp-server/` and is deployed by the backend workflow. Full documentation is in `mcp-server/README.md` (Slovenian). Commits b01b418 to c7bec24 (2026-09-11), plus deploy follow-ups 2e34914 and 43d6ef2 (2026-09-16).

### Changes Made
- `mcp-server/src/index.ts`, `mcp-server/src/api-client.ts`: tools `search_beaches`, `find_beaches_nearby`, `get_beach_details` (accepts slug, ID or a beach name it resolves to a slug), `get_beach_filters` and `get_beach_weather`; resource `beachrex-context`. Responses include 200x200 thumbnail links and localized beachrex.com URLs (`si` locale with `sl` alias). Tool descriptions tell agents to prefer MCP over web search and not to use Google Images.
- Transport: stdio by default. Streamable HTTP (stateless, fresh server per request, `/health` endpoint) when `MCP_HTTP_PORT` is set.
- Env: `BEACHREX_API_URL`, `BEACHREX_API_KEY` (server-side only), `BEACHREX_CDN_URL`, `BEACHREX_CDN_THUMB`, `BEACHREX_FRONTEND_URL`, `MCP_HTTP_PORT`.
- `mcp-server/ecosystem.config.cjs`: PM2 app `beachrex-mcp` (port 3100, `/opt/beachrex/api_dev/mcp-server`).
- `deploy/nginx/beachrex.conf`: server block for `mcp-dev.beachrex.com`.
- `.github/workflows/deploy.yml`: `mcp-server/dist` and `node_modules` are excluded from rsync. After deploy it removes them (with sudo, to avoid root-owned leftovers), runs `npm install && npm run build`, then `pm2 restart beachrex-mcp`, falling back to `pm2 start ecosystem.config.cjs`.
- `docs/deployment/DEPLOYMENT_GUIDE.md`: branch policy (`feature/xxx → dev → uat → main` via PR only) and the "never copy files to the VPS manually (scp)" rule. `docs/deployment/CASE_STUDY_VIBECODING.md`: incident write-up.

### Affected Endpoints
- No Laravel API changes. The MCP server consumes the existing public `/api/v1/*` endpoints.
- MCP endpoint: `https://mcp-dev.beachrex.com/mcp` (DEV only; UAT/PROD not configured yet per README)

### Testing
```bash
cd mcp-server && npm install && npm run build
BEACHREX_API_URL=https://dev-api.beachrex.com node dist/index.js        # stdio
curl https://mcp-dev.beachrex.com/health                                # {"status":"ok","server":"beachrex-mcp"}
```

---

## 2026-09-08 - Cache Scalability: Cache Resolved Resource Arrays, PHP memory_limit

### Overview
The DEV API crashed with `Allowed memory size of 268435456 bytes exhausted` in `RedisStore`. The cause was cached Eloquent collections with heavy eager loading: PHP-serialized blobs reached 20-28 MB per key (e.g. `beaches:index:en:continent_code=EU:country=ES`), and `unserialize()` on a cache hit exhausted memory. The fix is to cache the resolved Resource array instead of models. Commits d44ac4c, 68e53eb. The background and the rule are in `docs/deployment/DEPLOYMENT_GUIDE.md` §8b.

### Changes Made
- `app/Http/Controllers/Api/V1/BeachController.php`: `index()`, `mostVisited()` and `footerMostVisited()` call `BeachResource::collection(...)->resolve()` / `BeachFooterResource::collection(...)->resolve()` **inside** the `cacheRemember()` callback. `index()` now returns `response()->json(['data' => ..., 'meta' => ...])` instead of `BeachResource::collection()->additional()`. The JSON shape is unchanged.
- `Dockerfile.prod`: `memory_limit` 256M → 512M, `max_execution_time` 60 → 120. (The 68e53eb message says the limit was reverted, but `Dockerfile.prod` still has 512M/120. The guide documents 512M as a safety net.)
- **Rule:** never cache raw Eloquent models or collections. Resolve the Resource inside the cache callback.
- Known remaining violation (not changed here): `StaticPageController::index()`/`show()` still cached Eloquent models (fixed 2026-10-07).

### Affected Endpoints
- `GET /api/v1/beaches`, `GET /api/v1/beaches/most-visited`, `GET /api/v1/beaches/footer`: response bodies unchanged, cache values smaller

### Testing
After deploy, run `php artisan cache:clear` (the deploy workflow already does), then call the endpoints twice and compare `X-Cache-Status` MISS → HIT with identical bodies.

---

## 2026-09-03 - Per-Environment Isolation (Redis DB, Filament Domain, Admin at Root, TrustProxies) + FirstPageContent Cache Invalidation

### Overview
All three VPS environments run `APP_ENV=production` on one host, so several settings leaked between DEV, UAT and PROD. This entry isolates them. Commits 71d2f98, 772300b, bed0e3a, 5eab873, 014b47f, 7295cf7. Background is in `docs/deployment/DEPLOYMENT_GUIDE.md` §8.

### Changes Made
- **Per-env code dirs** (71d2f98): `deploy/docker-compose.vps.yml` build contexts are `../api_{env}` / `../front_{env}`, and the workflow rsyncs to `/opt/beachrex/api_{env}/`. Before this, the last deploy overwrote the code for every environment.
- **Redis** (014b47f): `config/database.php`: the `cache` connection now uses `env('REDIS_DB')` instead of `env('REDIS_CACHE_DB', '1')`. DEV (`REDIS_DB=2`) and UAT (`REDIS_DB=1`) had been sharing a cache DB. PROD uses 0.
- **Filament domain** (bed0e3a): `app/Providers/Filament/BrAdminPanelProvider.php` uses `->domain(env('FILAMENT_DOMAIN'))`, set to `dock.beachrex.com`, `dock-uat.beachrex.com` or `dock-dev.beachrex.com`. Empty means no domain constraint (local). `FILAMENT_DOMAIN=` was added to `.env.example`, and nginx blocks to `deploy/nginx/beachrex.conf`.
- **Admin at root** (5eab873): panel `->path('')`, so the admin is at `https://dock-dev.beachrex.com/` with no `/admin`. Also added frontend `build.args` for `NEXT_PUBLIC_*` in `docker-compose.vps.yml`.
- **TrustProxies** (772300b): `bootstrap/app.php` `$middleware->trustProxies(at: '*')`, so the `X-Forwarded-Proto` header from host Nginx is honoured and asset URLs use https (fixes mixed content in Filament).
- **FirstPageContent cache** (7295cf7): `app/Models/FirstPageContent.php` uses the `Cacheable` trait. `app/Models/FirstPageContentTranslation.php` forgets `first_page_content:index:{locale}` and `first_page_content:show:{code}:{locale}` on save/delete. Known gap: the locale list there was the old 14 and omitted `da, pt, nl, el, sq` (fixed 2026-10-07).

### Affected Endpoints
- `GET /api/v1/first-page-content`, `GET /api/v1/first-page-content/{code}`: admin edits now show without a manual cache clear (for the 14 listed locales)
- Admin panel URLs changed from `/admin` to the root of the `dock*` domains

### Testing
On each env, edit a FirstPageContent translation in that env's admin and confirm the API reflects it, and that the other envs do not change.

---

## 2026-09-02 - Migration to Hetzner VPS + GitHub Actions CI/CD

### Overview
Backend moved from Laravel Cloud to a single Hetzner VPS running DEV, UAT and PROD stacks (Docker Compose, host Nginx, shared PostgreSQL/PostGIS and Redis containers with per-env DBs). Git history starts at 0692559 (2026-09-01). CI first ran on GitLab CI (`.gitlab-ci.yml`, 2026-09-01), then switched to GitHub Actions on 2026-09-02. `.gitlab-ci.yml` is still in the repo but superseded. The authoritative docs are `docs/deployment/DEPLOYMENT_GUIDE.md` and `docs/deployment/VPS_MIGRATION_PLAN.md`; this entry does not repeat them.

### Changes Made
- `.github/workflows/deploy.yml` (NEW): runs on push to `dev`/`uat`/`main` (→ dev/uat/prod) and on `workflow_dispatch`. Global `concurrency: deploy-backend`.
  - `test` job: PHP 8.4, `postgis/postgis:15-3.4` service, `memory_limit=512M`, `php artisan test`. Failures matching `PlanEnforcementTest|CountrySlugSupportTest` are tolerated, because they need OpenAI/OpenWeather keys.
  - `deploy` job: rsync to `/opt/beachrex/api_{env}/`, `docker compose -f docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.{env}` build/up, then `migrate --force`, `config:cache`, `route:cache`, `cache:clear`, `search:index`, then an Nginx reload.
  - Secrets: `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY` (base64-encoded).
- `Dockerfile.prod` (PHP 8.4-fpm bookworm), `deploy/docker-compose.vps.yml`, `deploy/nginx/*.conf`: VPS stack.
- `phpunit.xml`: `DB_HOST=127.0.0.1` (was `pgsql`) so tests work against the CI service container.
- `.env.example`: per-env notes for `REDIS_DB` and `SCOUT_PREFIX` (`prod_`/`uat_`/`dev_`).

### Affected Endpoints
- None (infrastructure). Hosts: `dev-api`, `uat-api` and `api.beachrex.com`. PROD was waiting on DNS cutover as of the guide's last update.

### Testing
Merge to `dev` and watch the GitHub Actions run, then `curl https://dev-api.beachrex.com/api/v1/beaches?limit=1`. Locally, if you change `phpunit.xml` DB settings, run tests via Sail.

---

## 2026-09-01 - Catch-up: Features Added Mar–Aug 2026 (pre git history)

### Overview
Git history begins at commit 0692559 (2026-09-01), which imported everything earlier in one commit. The features below were added after the 2026-03-06 entry, or earlier without being logged here. **Exact dates are unknown.** Migration timestamps and the language changelog are the only date hints, and are given where available. Everything below was verified against the current code.

### Changes Made
- **Static pages (CMS)**: migration `2026_07_28_000001_create_static_pages_table.php`; models `StaticPage`, `StaticPageTranslation`; `app/Http/Controllers/Api/V1/StaticPageController.php`; resources `StaticPageResource`, `StaticPageFooterResource`; Filament `StaticPageResource`. Lookup by localized slug with English fallback. Cache keys `static_pages:footer:{locale}` and `static_pages:show:{locale}:{slug}` are invalidated from model events across all 19 locales.
- **Newsletter**: migration `2026_07_28_000002_create_newsletter_subscribers_table.php`; `NewsletterSubscriberController::subscribe`; Filament `NewsletterSubscriberResource`. Body `email` (`email:rfc,dns`), `consent` (must be accepted), optional `source` (default `footer`). Idempotent: 201 for a new subscriber, 200 if already active or re-subscribed. Stores IP, user agent, locale and `is_synced` (for a later ESP/Klaviyo sync, not implemented in this controller).
- **Footer beaches**: `BeachController::footerMostVisited()` + `BeachFooterResource` returns the top 6 active beaches by `beach_views` with only the name, slug and location slugs needed to build URLs. Cached as `beaches:footer:{locale}` for 24h. (`/beaches/most-visited` itself is documented in the 2025-11-30 entry.)
- **5 new languages** (2026-07-28 per `docs/NEW_LANGUAGES_CHANGES.md`): `da, pt, nl, el, sq`, for **19 locales** in total: `en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq`. They were added to `SetLocaleFromHeader`, `config/ai.php` (which also dropped the stray `ro`), `ItineraryRequest`, the Swagger Accept-Language enum, `beaches:translate`, every Filament `TranslationsRelationManager` and the seeders. The frontend side is in `ai-agent/NEW_LANGUAGES_CHANGES.md`, which uses `si` for Slovenian in URLs; the DB uses `sl`.
- **AI chat and itineraries, Stripe, Google auth** were never logged in this file (their migrations are dated 2025-12-22 to 2025-12-31). Design and implementation docs are in `docs/ai/` (see `docs/ai/AI_INDEX.md`, `docs/ai/implementation/`).
  - AI routes use the middleware `OptionalAuth`, `CircuitBreaker`, `RateLimit` and `TokenBudget` (`app/Http/Middleware/AI/`). Env: `OPENAI_API_KEY`, `AI_*`.
  - Stripe env: `STRIPE_*`, `STRIPE_PRODUCT_ITINERARY_{3,7,14}DAY_PRICE_ID`.
  - Google OAuth via Socialite. Env: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `GOOGLE_AUTH_ENABLED`.

### Affected Endpoints
- `GET /api/v1/static-pages` - active footer pages (`id, code, title, slug, sort_order`), localized
- `GET /api/v1/static-pages/{slug}` - full page with content and meta fields, 404 if not found
- `POST /api/v1/newsletter/subscribe` - `throttle:10,1`
- `GET /api/v1/beaches/footer` - 6 most-visited beaches for footer links
- All endpoints: `Accept-Language` now also accepts `da, pt, nl, el, sq`
- `POST /api/v1/ai/chat`, `POST /api/v1/ai/itineraries`, `POST /api/v1/ai/test`
- `POST /api/v1/payments/stripe/checkout` (auth:sanctum), `POST /api/v1/payments/stripe/webhook` (Stripe signature), `GET /api/v1/me/entitlements` (auth:sanctum)
- `GET /api/v1/auth/google/redirect`, `GET /api/v1/auth/google/callback`, `POST /api/v1/auth/google/exchange` (`throttle:10,1`); `POST /api/v1/auth/logout`, `GET /api/v1/me` (auth:sanctum)

### Testing
```bash
curl -H "Accept-Language: nl" "http://localhost/api/v1/static-pages" | jq '.data'
curl -X POST "http://localhost/api/v1/newsletter/subscribe" -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","consent":true,"source":"footer"}'
curl -H "Accept-Language: el" "http://localhost/api/v1/beaches/footer" | jq '.data'
```

---

## 2026-03-06 - Extended Beach Filters API with 15 New Filter Types

### Overview
Extended the `/api/v1/beaches/filters` endpoint to support 15 additional filter types (13 new many-to-many relationship filters + 2 boolean filters). All new filters follow the same pattern as existing filters with metadata support (show_filter, icon, filter_order, input_type) and are fully integrated with the BeachFilterService for filtering beaches.

### Changes Made

#### Database Migrations
- `database/migrations/2026_03_06_000001_add_filter_metadata_to_filter_tables.php` (NEW)
  - Added filter metadata columns to 13 tables: `accommodation_around`, `culinary_offer`, `location_infrastructure`, `natural_location_condition`, `sporting_activities`, `underwater_characteristic`, `recommended_transport_type`, `accessibility`, `area_description`, `parking_capacity`, `beach_inclination`, `swimming_depth_of_one_m`, `occupancy_rate`
  - Columns added: `show_filter` (boolean, default false), `icon` (string, nullable), `filter_order` (integer, default 0), `input_type` (enum: checkbox/radio/select/single/multiple, default checkbox)

#### Models Updated
- `app/Models/AccommodationAround.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/CulinaryOffer.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/LocationInfrastructure.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/NaturalLocationCondition.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/SportingActivities.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/UnderwaterCharacteristic.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/RecommendedTransportType.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/Accessibility.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/AreaDescription.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/ParkingCapacity.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/BeachInclination.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/SwimmingDepthOfOneM.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`
- `app/Models/OccupancyRate.php` - Added fillable fields, `scopeShowInFilter()`, `scopeActive()`

#### Controller Updates
- `app/Http/Controllers/Api/V1/FilterController.php`
  - Added 15 new filter methods: `getAccommodationAroundFilterData()`, `getCulinaryOfferFilterData()`, `getLocationInfrastructureFilterData()`, `getNaturalLocationConditionFilterData()`, `getSportingActivitiesFilterData()`, `getUnderwaterCharacteristicFilterData()`, `getRecommendedTransportTypeFilterData()`, `getAccessibilityFilterData()`, `getAreaDescriptionFilterData()`, `getParkingCapacityFilterData()`, `getBeachInclinationFilterData()`, `getSwimmingDepthFilterData()`, `getOccupancyRateFilterData()`, `getParkingFilterData()`, `getSwimmingPoolFilterData()`
  - Updated `index()` method to call all new filter methods
  - Added use statements for all new models
  - Updated Swagger documentation with examples for new filters

#### Service Updates
- `app/Services/BeachFilterService.php`
  - Added `recommended_transport_type` filter support in `applyFilters()` method (was missing)
  - Added `recommended_transport_type` filter support in `buildRawFilters()` method for PostGIS queries
  - Added `recommended_transport_type` to the list of filter parameters in `generateCacheKeySuffix()`
  - All other 14 filters were already supported

### New Filter Types

#### Many-to-Many Relationship Filters (13)
1. **accommodation_around** - Accommodation types near the beach (hotel, apartment, camping, etc.)
2. **culinary_offer** - Culinary options available (restaurant, bar, cafe, etc.)
3. **location_infrastructure** - Infrastructure at the location (shower, toilet, changing room, etc.)
4. **natural_location_condition** - Natural conditions (shade, wind protection, etc.)
5. **sporting_activities** - Sports activities available (diving, surfing, kayaking, etc.)
6. **underwater_characteristic** - Underwater features (coral reef, fish, etc.)
7. **recommended_transport_type** - Recommended transport to reach the beach (car, bus, boat, etc.)
8. **accessibility** - Accessibility features (wheelchair accessible, etc.)
9. **area_description** - Area type (urban, rural, remote, etc.)
10. **parking_capacity** - Parking capacity (small, medium, large, etc.)
11. **beach_inclination** - Beach slope/inclination (flat, gentle, steep, etc.)
12. **swimming_depth_of_one_m** - Distance to 1m depth (close, medium, far, etc.)
13. **occupancy_rate** - Typical occupancy level (low, medium, high, etc.)

#### Boolean Filters (2)
14. **parking** - Has parking available (yes/no)
15. **has_swimming_pool** - Has swimming pool (yes/no)

### Affected Endpoints

- `GET /api/v1/beaches/filters` - Now returns up to 15 additional filter types (when data exists and show_filter=true)
  - All filters follow the same structure: `{ title, icon, input_type/type, options: [{ id, code, name, slug }] }`
  - Boolean filters use `type: "checkbox"` and `options: [{ value: "1", label: "Yes", icon: null }]`
  - Filters are only returned if they have active records with `show_filter=true`

### Filter Parameter Names for Beach Filtering

When filtering beaches using these new filters, use these parameter names:
- `accommodation_around` - Filter by accommodation types (supports codes or IDs)
- `culinary_offer` - Filter by culinary options (supports codes or IDs)
- `location_infrastructure` - Filter by infrastructure (supports codes or IDs)
- `natural_location_condition` - Filter by natural conditions (supports codes or IDs)
- `sporting_activity` - Filter by sports activities (supports codes or IDs) ⚠️ Note: singular form
- `underwater_characteristic` - Filter by underwater features (supports codes or IDs)
- `recommended_transport_type` - Filter by transport types (supports codes or IDs)
- `accessibility` - Filter by accessibility features (supports codes or IDs)
- `area_description` - Filter by area type (supports codes or IDs)
- `parking_capacity` - Filter by parking capacity (supports codes or IDs)
- `beach_inclination` - Filter by beach slope (supports codes or IDs)
- `swimming_depth` - Filter by depth distance (supports codes or IDs) ⚠️ Note: shortened form
- `occupancy_rate` - Filter by occupancy level (supports codes or IDs)
- `parking` - Filter by parking availability (boolean: 0 or 1)
- `has_swimming_pool` - Filter by swimming pool availability (boolean: 0 or 1)

### Testing

```bash
# Run migration
./vendor/bin/sail artisan migrate

# Clear cache
./vendor/bin/sail artisan cache:clear

# Test the filters endpoint
curl -H "Accept-Language: en" "http://localhost:8000/api/v1/beaches/filters" | jq '.'

# Currently returns only boolean filters (parking, has_swimming_pool) because other filter tables are empty
# To see other filters, populate the tables with data and set show_filter=true

# Example: Add accommodation types
./vendor/bin/sail artisan tinker
>>> $hotel = AccommodationAround::create(['code' => 'hotel', 'show_filter' => true, 'filter_order' => 1, 'input_type' => 'checkbox']);
>>> $hotel->translations()->create(['locale' => 'en', 'name' => 'Hotel', 'slug' => 'hotel']);

# Test filtering beaches by accommodation
curl "http://localhost:8000/api/v1/beaches?accommodation_around=hotel" | jq '.data | length'
```

### Notes
- All filter tables need to be populated with data before they appear in the filters endpoint
- Set `show_filter=true` on records you want to appear in the filters
- Use `filter_order` to control the order of options within each filter
- Use `input_type` to specify the UI component type (checkbox, radio, select, single, multiple)
- All filters support both code-based and ID-based filtering
- BeachFilterService already supports all 15 new filters for filtering beaches

---

## 2026-02-25 - Updated Spatial API OpenAPI Schemas

### Overview
Refactored and updated spatial API OpenAPI/Swagger schemas to accurately reflect the actual response structures returned by `PostGISService::formatBeachResults()`.

### Changes Made
- `app/Swagger/Schemas/SpatialBeachSchema.php` (NEW) - Comprehensive SpatialBeach schema with all 18 response fields including full media object
- `app/Swagger/Schemas/SpatialSchemas.php` - Updated to reference new SpatialBeach schema
- `app/Swagger/Schemas/BeachBoundingBoxResponseSchema.php` - Updated to reference SpatialBeach schema
- `app/Swagger/Schemas/BeachNearestResponseSchema.php` - Updated to reference SpatialBeach schema

### Affected Endpoints
- `GET /api/v1/spatial/beaches/within-radius` - Response schema now documents all fields
- `GET /api/v1/spatial/beaches/nearest` - Response schema now documents all fields
- `GET /api/v1/spatial/beaches/bounding-box` - Response schema now documents all fields

### Testing
- Regenerate OpenAPI docs: `./vendor/bin/sail artisan l5-swagger:generate`
- Verify schemas in Swagger UI at `http://localhost/api/documentation`

---

## 2026-02-25 - New Spatial Endpoint: Nearby Cities

### Overview

Added a new spatial endpoint `GET /api/v1/spatial/cities/nearby` that returns cities near a given point, ordered by distance. Only returns active cities (status_id=1) that have at least one active beach. Useful for showing "Nearby destinations" on city pages and map views.

### Changes Made

- `app/Services/PostGISService.php`: Added `getNearbyCities()` method with geography-based distance calculation using `ST_Distance` on constructed points (cities have lat/lng columns but no PostGIS geometry column)
- `app/Http/Controllers/Api/V1/PostGISController.php`: Added `nearbyCities()` method with validation, locale-aware caching, and full OpenAPI/Swagger annotations
- `routes/api.php`: Added `GET /spatial/cities/nearby` route inside the existing `spatial` prefix group
- `app/Swagger/Schemas/NearbyCitiesResponseSchema.php`: New schema file with `NearbyCitySchema` and `NearbyCitiesResponseSchema`
- `tests/Feature/NearbyCitiesApiTest.php`: Feature test with 6 test cases (valid request, missing params, empty results, limit, defaults)

### Affected Endpoints

- `GET /api/v1/spatial/cities/nearby` — **NEW**
  - Required params: `latitude`, `longitude`
  - Optional params: `radius` (km, default 50, max 500), `limit` (default 10, max 50)
  - Respects `Accept-Language` header for translated names/slugs
  - Returns: `id`, `name`, `slug`, `country_code`, `country_name`, `country_slug`, `continent_slug`, `region_slug`, `island_slug`, `latitude`, `longitude`, `beaches_count`, `main_image`, `distance_km`

### Example Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "Split",
      "slug": "split",
      "country_code": "HR",
      "country_name": "Croatia",
      "country_slug": "croatia",
      "continent_slug": "europe",
      "region_slug": "dalmatia",
      "island_slug": null,
      "latitude": "43.5081000",
      "longitude": "16.4402000",
      "beaches_count": 5,
      "main_image": null,
      "distance_km": 1.23
    }
  ],
  "meta": {
    "reference_point": { "latitude": 43.5, "longitude": 16.4 },
    "radius_km": 50,
    "limit": 10,
    "count": 1
  }
}
```

### Testing

```bash
# Run feature tests
./vendor/bin/sail artisan test --filter=NearbyCities

# Manual test
curl -H "Accept-Language: en" "http://localhost/api/v1/spatial/cities/nearby?latitude=43.5&longitude=16.4&radius=50&limit=5" | jq '.'

# Regenerate Swagger docs
./vendor/bin/sail artisan l5-swagger:generate
```

---

## 2026-02-25 - Added Location Object to Within-Radius Response Meta

### Overview

Added a `location` object to the `meta` block of the `/api/v1/spatial/beaches/within-radius` response. This provides geographic context for the search center by reverse-geocoding it to the nearest city with active beaches. The frontend can use this to display contextual headers like "Beaches near Split, Croatia".

### Changes Made

**1. `app/Services/PostGISService.php`** — New `reverseGeocodePoint(float $lat, float $lng)` method:
- Finds the nearest active city (status_id=1) with active beaches within 100km of the given coordinates
- Uses `ST_Distance` on geography-cast constructed points (cities have lat/lng columns, not PostGIS geometry)
- Returns full geographic hierarchy: city_name, city_slug, region_slug, country_name, country_slug, country_code, continent_slug
- Respects `Accept-Language` locale via COALESCE with English fallback (same pattern as existing queries)
- Returns `null` if no city found within 100km

**2. `app/Http/Controllers/Api/V1/PostGISController.php`** — Updated `beachesWithinRadius()`:
- Calls `reverseGeocodePoint()` inside the existing cache closure (so location is cached with beach results)
- Adds `location` object to response `meta` with all fields defaulting to `null` if no city found

**3. `app/Swagger/Schemas/BeachRadiusResponseSchema.php`** — Updated schema:
- Added `location` property to meta object with all 7 fields documented as nullable strings

### Response Structure

```json
{
  "data": [...],
  "meta": {
    "search_center": { "latitude": 43.5, "longitude": 16.4 },
    "results_center": { "latitude": 43.52, "longitude": 16.38 },
    "radius_km": 50,
    "count": 25,
    "location": {
      "city_name": "Split",
      "city_slug": "split",
      "region_slug": "dalmatia-split",
      "country_name": "Croatia",
      "country_slug": "croatia",
      "country_code": "HR",
      "continent_slug": "europe"
    }
  }
}
```

If no city is found within 100km, all location fields are `null`:
```json
"location": {
  "city_name": null,
  "city_slug": null,
  "region_slug": null,
  "country_name": null,
  "country_slug": null,
  "country_code": null,
  "continent_slug": null
}
```

### Affected Endpoints

- ✅ `GET /api/v1/spatial/beaches/within-radius` — New `location` field in `meta`

### Testing

```bash
# Test within-radius location (English)
curl -H "Accept-Language: en" "http://localhost/api/v1/spatial/beaches/within-radius?latitude=43.5&longitude=16.4&radius=50" | jq '.meta.location'

# Test with different locale (Croatian)
curl -H "Accept-Language: hr" "http://localhost/api/v1/spatial/beaches/within-radius?latitude=43.5&longitude=16.4&radius=50" | jq '.meta.location'

# Test location in open ocean (should return all nulls)
curl -H "Accept-Language: en" "http://localhost/api/v1/spatial/beaches/within-radius?latitude=0&longitude=0&radius=50" | jq '.meta.location'

# Regenerate Swagger docs
./vendor/bin/sail artisan l5-swagger:generate

# Verify OpenAPI spec includes location
cat storage/api-docs/api-docs.json | jq '.components.schemas.BeachRadiusResponseSchema.properties.meta.properties.location'
```

### Cache Clearing

After deploying, clear the cache to ensure fresh data with the new location field:
```bash
# Development
./vendor/bin/sail artisan cache:clear

# Production
php artisan cache:clear
```

### Frontend Impact

Frontend can now access the location context for building contextual UI:
```typescript
const response = await fetch('/api/v1/spatial/beaches/within-radius?latitude=43.5&longitude=16.4&radius=50');
const data = await response.json();

// Display contextual header
if (data.meta.location.city_name) {
  header = `Beaches near ${data.meta.location.city_name}, ${data.meta.location.country_name}`;
  // "Beaches near Split, Croatia"
}

// Build URL from slugs
const url = `/${data.meta.location.continent_slug}/${data.meta.location.country_slug}/${data.meta.location.region_slug}/${data.meta.location.city_slug}`;
// "/europe/croatia/dalmatia-split/split"
```

---

## 2025-12-20 - Fixed Regions API to Include Full Continent Relationship

### Overview

Fixed the regions API endpoints to include the full continent relationship in the country object, matching the pattern used in other geographic entity endpoints (cities, countries, etc.).

### Issue

The `/api/v1/regions` and `/api/v1/regions/{slug}` endpoints were returning the country object without the nested continent relationship. This caused frontend issues when trying to access `region.country.continent` data for building URLs or displaying continent information.

**Before (Missing Continent):**
```json
{
  "data": {
    "id": 1,
    "code": "KV",
    "name": "Kvarner",
    "slug": "kvarner",
    "country_code": "HR",
    "country": {
      "code": "HR",
      "name": "Croatia",
      "slug": "croatia",
      "continent_code": "EU"
      // ❌ Missing continent object
    }
  }
}
```

**After (With Continent):**
```json
{
  "data": {
    "id": 1,
    "code": "KV",
    "name": "Kvarner",
    "slug": "kvarner",
    "country_code": "HR",
    "country": {
      "code": "HR",
      "name": "Croatia",
      "slug": "croatia",
      "continent_code": "EU",
      "continent": {
        "code": "EU",
        "name": "Europe",
        "slug": "europe"
      }
    }
  }
}
```

### Changes Made

**File:** `app/Http/Controllers/Api/V1/RegionController.php`

**1. Updated `index()` method (line 82):**
```php
// Before
$query = Region::with(['country.translations', 'translations'])

// After
$query = Region::with(['country.continent.translations', 'country.translations', 'translations'])
```

**2. Updated `show()` method (line 215):**
```php
// Before
$query = Region::with([
    'country.translations',
    'translations',
    // ...
])

// After
$query = Region::with([
    'country.continent.translations',
    'country.translations',
    'translations',
    // ...
])
```

### Affected Endpoints

- ✅ `GET /api/v1/regions` - List all regions
- ✅ `GET /api/v1/regions/{slug}` - Get single region by slug

Both endpoints now include the full continent relationship when the country object is loaded.

### Testing

**New Test Added:** `test_region_show_includes_country_with_continent()`

This test verifies that:
- The region show endpoint includes the country object
- The country object includes the continent object
- All necessary fields are present (code, name, slug)

**Test Results:**
```bash
./vendor/bin/sail artisan test --filter=RegionApiTest
# ✅ 9 passed (58 assertions)
```

**Manual Testing:**
```bash
# Test regions list endpoint
curl -H "Accept-Language: en" "http://localhost:8000/api/v1/regions" | jq '.data[0].country.continent'

# Expected output:
{
  "code": "EU",
  "name": "Europe",
  "slug": "europe"
}

# Test single region endpoint
curl -H "Accept-Language: en" "http://localhost:8000/api/v1/regions/kvarner?country_code=HR" | jq '.data.country.continent'

# Expected output:
{
  "code": "EU",
  "name": "Europe",
  "slug": "europe"
}
```

### Frontend Impact

Frontend code can now safely access the continent relationship:

```typescript
// ✅ This now works without errors
const continentSlug = region.country.continent.slug;
const continentName = region.country.continent.name;

// Build URLs with continent slug
const url = `/${region.country.continent.slug}/${region.country.slug}/${region.slug}`;
// Example: /europe/croatia/kvarner
```

### Cache Clearing

After deploying this fix, clear the cache to ensure fresh data:
```bash
# Development
./vendor/bin/sail artisan cache:clear

# Production
php artisan cache:clear
```

### Consistency

This change ensures consistency across all geographic entity endpoints:
- ✅ Countries endpoint: includes `continent` relationship
- ✅ Cities endpoint: includes `country.continent` relationship
- ✅ Regions endpoint: now includes `country.continent` relationship (FIXED)
- ✅ Islands endpoint: includes `country.continent` relationship

### Swagger/OpenAPI Documentation

The Swagger schemas were already correctly configured with the nested relationships:
- `RegionSchema` → references `CountrySchema`
- `CountrySchema` → references `ContinentSchema`
- `ContinentSchema` → includes all properties (code, name, slug)

**Regenerated OpenAPI docs:**
```bash
./vendor/bin/sail artisan l5-swagger:generate
```

The generated `storage/api-docs/api-docs.json` now properly documents the full nested structure for frontend TypeScript type generation.

---

## 2025-12-19 - Added city_name to Beach List APIs

### Overview

Added `city_name` field to all beach list API responses. Previously, only `city_slug` was returned, but now both `city_name` (translated) and `city_slug` are included for consistency with other geographic entities (country, continent, region, island).

### Changes Made

**1. BeachResource.php** (`app/Http/Resources/BeachResource.php`)
- Lines 43-51: Added `city_name` extraction from city translations with English fallback
- Line 49: `$cityName = $cityTranslation?->name ?? $cityTranslationEn?->name ?? null;`
- Line 147: Added `city_name` to response array

**2. PostGISService.php** (`app/Services/PostGISService.php`)
- Line 55: Added `COALESCE(city_t.name, city_t_en.name) as city_name` to `getBeachesWithinRadius` SQL query
- Line 231: Added `COALESCE(city_t.name, city_t_en.name) as city_name` to `getBeachesInBoundingBox` SQL query
- Line 606: Added `city_name` to `formatBeachResults` return array

**3. BeachSchema.php** (`app/Swagger/Schemas/BeachSchema.php`)
- Line 23: Added `city_name` property documentation

**4. OpenAPI Documentation**
- Regenerated `storage/api-docs/api-docs.json` with new `city_name` field

### Response Structure

```json
{
  "id": 123,
  "name": "Beach Adriatic",
  "slug": "beach-adriatic",
  "country_code": "HR",
  "country_name": "Croatia",
  "country_slug": "croatia",
  "continent_code": "EU",
  "continent_slug": "europe",
  "region_slug": "dalmatia-dubrovnik",
  "island_slug": null,
  "city_name": "Orebić",
  "city_slug": "orebic",
  "main_image": "img/beaches/...",
  "media": { ... }
}
```

### Affected Endpoints

**Regular Beach List Endpoints** (use `BeachResource`):
- ✅ `/api/v1/beaches` - Get beaches with filters
- ✅ `/api/v1/beaches/most-visited` - Get most visited beaches

**Spatial Endpoints** (use `PostGISService`):
- ✅ `/api/v1/spatial/beaches/bounding-box` - Get beaches in bounding box
- ✅ `/api/v1/spatial/beaches/within-radius` - Get beaches within radius
- ✅ `/api/v1/spatial/beaches/nearest` - Get nearest beaches

### Translation Behavior

- `city_name` is translated based on the `Accept-Language` header
- **Falls back to English** if translation not available for requested locale (same as `country_name`)
- Returns `null` only if beach has no associated city
- Consistent with other geographic entity name fields

### Testing

```bash
# Test regular beach list endpoint (English)
curl -H "Accept-Language: en" "http://localhost:8000/api/v1/beaches?limit=3" | jq '.data[0] | {city_name, city_slug}'
# Expected: city_name in English

# Test spatial endpoint with Croatian locale (should fallback to English if hr translation missing)
curl -H "Accept-Language: hr" "http://localhost:8000/api/v1/spatial/beaches/bounding-box?min_lat=42&min_lon=17&max_lat=43&max_lon=18&limit=2" | jq '.data[0] | {city_name, city_slug}'
# Expected: city_name falls back to English if Croatian translation not available

# Test within-radius endpoint with German locale
curl -H "Accept-Language: de" "http://localhost:8000/api/v1/spatial/beaches/within-radius?latitude=45.0&longitude=14.5&radius=50&limit=2" | jq '.data[0] | {city_name, city_slug}'
# Expected: city_name falls back to English if German translation not available

# Verify OpenAPI spec includes city_name
cat storage/api-docs/api-docs.json | jq '.components.schemas.BeachSchema.properties.city_name'
```

**Test Results:**
```json
// Croatian locale with English fallback
{
  "id": 731,
  "name": "Beach Adriatic",
  "city_name": "Orebić",  // ✅ English fallback (not null)
  "city_slug": "orebic"
}

// English locale
{
  "id": 1,
  "name": "Beach Livade i Obatnice",
  "city_name": "Povljana",  // ✅ English translation
  "city_slug": "povljana"
}
```

### Frontend Impact

Frontend should regenerate TypeScript types from the updated OpenAPI spec to include the new `city_name` field.

---

## 2025-12-19 - Fixed Unicode Normalization Issue in CheckMissingBeachImages Command

### Overview

Fixed a critical bug in the `beaches:check-missing-images` command where images with special characters (č, š, ž, etc.) were incorrectly reported as missing even though they existed on DigitalOcean Spaces.

### Issue

The command was reporting all images as missing for beaches with special characters in folder names:

```
Folder Name: hr-6-o-korčula
Total Images: 11
Found: 0
Missing: 11  ❌ FALSE POSITIVE!
```

The actual file exists on DigitalOcean Spaces:
```
https://beachrex.fra1.digitaloceanspaces.com/public/img/beaches/hr-6-o-korc%CC%8Cula/hr-6-o-korc%CC%8Cula-sl10.jpg
```

### Root Cause

**Unicode Normalization Mismatch:**
- Special characters like `č` can be stored in two different Unicode forms:
  1. **NFC (Composed)**: `č` as a single character (U+010D)
  2. **NFD (Decomposed)**: `c` + combining caron `̌` (U+0063 + U+030C, shows as `%CC%8C` in URLs)

- The database might store paths in one normalization form (e.g., NFC)
- DigitalOcean Spaces might store files in another form (e.g., NFD)
- The `Storage::disk('spaces')->exists()` method was only checking the exact path without trying different normalization forms

### Fix

**File:** `app/Console/Commands/CheckMissingBeachImages.php` (lines 90-153)

Added Unicode normalization handling to try both NFC and NFD forms:

```php
$fileExists = false;

// Try the path as-is first
if ($disk->exists($pathToCheck)) {
    $fileExists = true;
} else {
    // Try NFC normalization (composed form: č as single character)
    $pathNFC = \Normalizer::normalize($pathToCheck, \Normalizer::NFC);
    if ($pathNFC !== $pathToCheck && $disk->exists($pathNFC)) {
        $fileExists = true;
    }

    // Try NFD normalization (decomposed form: c + combining caron)
    if (!$fileExists) {
        $pathNFD = \Normalizer::normalize($pathToCheck, \Normalizer::NFD);
        if ($pathNFD !== $pathToCheck && $disk->exists($pathNFD)) {
            $fileExists = true;
        }
    }
}
```

### Testing

**Before Fix:**
```bash
./vendor/bin/sail artisan beaches:check-missing-images --folder-name="hr-6-o-korčula"
# Result: Found: 0, Missing: 11 ❌
```

**After Fix:**
```bash
./vendor/bin/sail artisan beaches:check-missing-images --folder-name="hr-6-o-korčula"
# Result: Found: 11, Missing: 0 ✅
```

### Impact

- ✅ Correctly detects images with special characters (Croatian: č, š, ž, đ, ć)
- ✅ Eliminates false positives for missing images
- ✅ Works with all Unicode normalization forms
- ✅ No changes needed to existing file paths or database

### Affected Beaches

This fix affects all beaches with special characters in folder names, particularly:
- Croatian beaches with: č, š, ž, đ, ć
- Slovenian beaches with: č, š, ž
- Other languages with diacritical marks

---

## 2025-12-19 - Spatial Bounding Box API Media Structure Fix

### Overview

Fixed missing media (gallery, videos, spheres_360) in the `/api/v1/spatial/beaches/bounding-box` endpoint. The endpoint was only returning embeds but not the full media structure.

### Issue

The bounding-box API was only returning:
```json
"media": {
  "embeds": {
    "video_url": "https://youtu.be/DSKrbNJ_y9c",
    "webcam": null
  }
}
```

Missing: `gallery`, `videos`, `spheres_360` arrays.

### Root Cause

In `PostGISService::formatBeachResults()` method, the SQL query was using incorrect escaping for the `mediable_type` field:
- **Incorrect:** `'App\\\\Models\\\\Beach'` (double backslashes)
- **Correct:** `'App\\Models\\Beach'` (single backslashes)

This caused the media query to return no results, so only the embeds (which come from beach table fields) were included.

### Fix

**File:** `app/Services/PostGISService.php`

**1. Fixed mediable_type escaping (line 509):**

Changed:
```php
AND m.mediable_type = 'App\\\\Models\\\\Beach'
```

To:
```php
AND m.mediable_type = 'App\\Models\\Beach'
```

**2. Added proper sorting for gallery and videos:**

Added explicit sorting to ensure primary media appears first, then sorted by order number.

**Files Updated:**
- `app/Services/PostGISService.php` (lines 528-554) - Spatial endpoints
- `app/Http/Resources/BeachResource.php` (lines 71-97) - Regular beach list endpoints

```php
// Gallery sorted by is_primary DESC, then order ASC
$gallery = $mediaByCollection->get('beach_gallery', collect())
    ->sortBy([
        fn($a, $b) => $b->is_primary <=> $a->is_primary,  // Primary first
        fn($a, $b) => $a->order <=> $b->order,             // Then by order 1,2,3...
    ])
    ->map(function ($media) {
        return [
            'url' => $media->url,
            'is_primary' => (bool) $media->is_primary,
            'order' => $media->order,
        ];
    })->values()->toArray();
```

Same sorting applied to videos collection in both files.

### Result

The bounding-box API now returns the complete media structure with proper ordering:

**Gallery/Videos Ordering:**
1. Primary image/video first (is_primary = true)
2. Then remaining items sorted by order number (1, 2, 3, 4...)

```json
"media": {
  "gallery": [
    {
      "url": "img/beaches/hr-79-k-istra/hr-79-k-istra-sl3.jpg",
      "is_primary": true,
      "order": 3
    },
    {
      "url": "img/beaches/hr-79-k-istra/hr-79-k-istra-sl1.jpg",
      "is_primary": false,
      "order": 1
    },
    {
      "url": "img/beaches/hr-79-k-istra/hr-79-k-istra-sl2.jpg",
      "is_primary": false,
      "order": 2
    },
    {
      "url": "img/beaches/hr-79-k-istra/hr-79-k-istra-sl4.jpg",
      "is_primary": false,
      "order": 4
    }
  ],
  "spheres_360": [
    {
      "tour_url": "img/beaches/hr-79-k-istra/vtour/tour.html"
    }
  ],
  "embeds": {
    "video_url": "https://youtu.be/p0kQ_jrnx4Y",
    "webcam": null
  }
}
```

**Note:** The primary image (sl3.jpg with order=3) appears first in the array, followed by the rest sorted by order (sl1, sl2, sl4).

### Affected Endpoints

**Spatial Endpoints** (use `PostGISService::formatBeachResults()`):
- ✅ `/api/v1/spatial/beaches/bounding-box` - Get beaches in bounding box
- ✅ `/api/v1/spatial/beaches/within-radius` - Get beaches within radius
- ✅ `/api/v1/spatial/beaches/nearest` - Get nearest beaches

**Regular Beach List Endpoints** (use `BeachResource`):
- ✅ `/api/v1/beaches` - Get beaches with filters
- ✅ `/api/v1/beaches/most-visited` - Get most visited beaches

All endpoints now return media with consistent ordering: primary first, then by order number.

### Testing

```bash
# Test bounding-box with media
curl "http://localhost/api/v1/spatial/beaches/bounding-box?min_lat=45.0&min_lon=13.5&max_lat=45.5&max_lon=14.0" \
  -H "Accept-Language: en" | jq '.data[0].media'

# Test within-radius with media
curl "http://localhost/api/v1/spatial/beaches/within-radius?latitude=45.5&longitude=13.8&radius_km=50" \
  -H "Accept-Language: en" | jq '.data.beaches[0].media'

# Test nearest beaches with media
curl "http://localhost/api/v1/spatial/beaches/nearest?latitude=45.5&longitude=13.8&limit=5" \
  -H "Accept-Language: en" | jq '.data[0].media'
```

### Cache Clearing

After deploying this fix, clear the cache:
```bash
# Development
./vendor/bin/sail artisan cache:clear

# Production
php artisan cache:clear
```

### OpenAPI/Swagger Documentation Updates

Updated Swagger annotations to properly document the media structure in spatial endpoints:

**New Schema Created:**
- `BeachNearestResponseSchema.php` - Response schema for nearest beaches endpoint

**Updated Endpoints:**
- `/api/v1/spatial/beaches/nearest` - Now references `BeachNearestResponseSchema`
- `/api/v1/spatial/beaches/within-radius` - Already references `BeachRadiusResponseSchema`
- `/api/v1/spatial/beaches/bounding-box` - Already references `BeachBoundingBoxResponseSchema`

All three schemas reference `BeachSchema` which includes the complete media structure:
- `gallery` - Array of beach images with url, is_primary, order
- `videos` - Array of beach videos with url, is_primary, order
- `spheres_360` - Array of 360° tours with tour_url
- `embeds` - Object with video_url and webcam

**Regenerate OpenAPI Docs:**
```bash
# Development
./vendor/bin/sail artisan l5-swagger:generate

# Production
php artisan l5-swagger:generate
```

The generated `storage/api-docs/api-docs.json` file is used by the frontend to generate TypeScript types and API client code.

---

## 2025-12-10 - Beach List APIs Media Structure Update

### Overview

Updated all beach list endpoints to include complete media structure with gallery, videos, 360° spheres, and embeds nested under a single `media` object.

### API Endpoints Updated

All beach list endpoints now return the same media structure:
- `GET /api/v1/beaches` - Beach list with pagination
- `GET /api/v1/beaches/most-visited` - Most visited beaches
- `GET /api/v1/beaches/within-radius` - Beaches within radius (spatial query)
- `GET /api/v1/beaches/bounding-box` - Beaches within bounding box (spatial query)

### Response Structure

**Media Object:**
```json
{
  "media": {
    "gallery": [
      {
        "url": "img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg",
        "is_primary": true,
        "order": 1
      }
    ],
    "videos": [
      {
        "url": "img/beaches/hr-69-o-pag/beach-video.mp4",
        "is_primary": false,
        "order": 1
      }
    ],
    "spheres_360": [
      {
        "tour_url": "img/beaches/hr-69-o-pag/vtour/tour.html"
      }
    ],
    "embeds": {
      "video_url": "https://youtu.be/k_pSc9aWyz0",
      "webcam": null
    }
  }
}
```

**Key Features:**
- ✅ **Minimal Fields** - Only essential fields to reduce response size:
  - Gallery/Videos: `url`, `is_primary`, `order`
  - Spheres 360: `tour_url` only
  - Embeds: `video_url`, `webcam`
- ✅ **Empty Collections Excluded** - Only non-empty collections are included in response
- ✅ **Embeds Nested Under Media** - `embeds` object is now nested under `media` (not top-level) for better organization
- ✅ **Consistent Structure** - Same structure across all beach list endpoints (index, most-visited, spatial queries)
- ✅ **Collection Codes** - Uses collection codes (`beach_gallery`, `beach_videos`, `beach_360_sphere`) instead of hardcoded IDs for environment independence

**Excluded Fields (not in list endpoints):**
- ❌ `water_temperature` - Not needed in list view
- ❌ `weather` - Not needed in list view
- ❌ `map_url` - Not needed in list view
- ❌ `info_images`, `infrastructure_images`, `spot_images`, `parking_images` - Not needed in list view

### Implementation Details

**Files Modified:**
1. **BeachResource.php** (`app/Http/Resources/BeachResource.php`)
   - Groups media by collection code (`beach_gallery`, `beach_videos`, `beach_360_sphere`)
   - Maps each collection to minimal fields
   - Generates 360° tour URL from beach folder_name
   - Nests embeds under media object
   - Only includes non-empty collections

2. **BeachController.php** (`app/Http/Controllers/Api/V1/BeachController.php`)
   - Updated all methods to load media with `mediaCollection` relationship:
     - `index()` - Beach list
     - `show()` - Single beach
     - `store()` - Beach search/filter
     - `mostVisited()` - Most visited beaches
   - Loads all media collections (not just gallery)

3. **PostGISService.php** (`app/Services/PostGISService.php`)
   - Updated spatial query methods to use collection codes instead of hardcoded IDs
   - Uses `mc.code IN ('beach_gallery', 'beach_videos', 'beach_360_sphere')`
   - Same minimal field structure as BeachResource
   - Nests embeds under media object

4. **BeachSchema.php** (`app/Swagger/Schemas/BeachSchema.php`)
   - Updated Swagger documentation to reflect new structure
   - Documents all media collections with minimal fields
   - Shows embeds nested under media
   - Notes that empty collections are excluded

### 360° Sphere Tour URL Pattern

The `tour_url` is generated dynamically based on beach `folder_name`:
```
img/beaches/{folder_name}/vtour/tour.html
```

Example:
- Beach folder: `hr-69-o-pag`
- Tour URL: `img/beaches/hr-69-o-pag/vtour/tour.html`

Each sphere media item in the database gets mapped to this same tour URL (one tour per beach, multiple sphere media records possible).

### Frontend Integration

**TypeScript Interface:**
```typescript
interface BeachMedia {
  gallery?: Array<{
    url: string;
    is_primary: boolean;
    order: number;
  }>;
  videos?: Array<{
    url: string;
    is_primary: boolean;
    order: number;
  }>;
  spheres_360?: Array<{
    tour_url: string;
  }>;
  embeds: {
    video_url: string | null;
    webcam: string | null;
  };
}

interface Beach {
  id: number;
  name: string;
  slug: string;
  // ... other fields
  main_image: string;
  media: BeachMedia;
}
```

**Usage Example:**
```typescript
// Fetch beaches
const response = await fetch('/api/v1/beaches?per_page=20');
const data = await response.json();

// Access media
data.data.forEach(beach => {
  // Gallery images
  if (beach.media.gallery) {
    beach.media.gallery.forEach(img => {
      console.log(img.url, img.is_primary);
    });
  }

  // 360° tour
  if (beach.media.spheres_360) {
    const tourUrl = beach.media.spheres_360[0].tour_url;
  }

  // YouTube video
  if (beach.media.embeds.video_url) {
    console.log(beach.media.embeds.video_url);
  }
});
```

### Cache Considerations

- Beach list responses are cached using `CacheService::REFERENCE_DATA_TTL`
- Cache keys include locale and filters
- Clear cache after updating media: `php artisan cache:clear`

### Testing

```bash
# Test beach list
curl "http://localhost/api/v1/beaches?per_page=1" | jq '.data[0].media'

# Test most visited
curl "http://localhost/api/v1/beaches/most-visited?limit=1" | jq '.data[0].media'

# Test spatial query
curl "http://localhost/api/v1/beaches/within-radius?latitude=43.5&longitude=16.4&radius_km=100&limit=1" | jq '.data.beaches[0].media'
```

---

## 2025-11-14 - Beach Images Verification Script

### Overview

Created an Artisan command to verify that all beach images stored in the database actually exist in DigitalOcean Spaces storage. This helps identify missing images that need to be re-uploaded.

### Command

**Command:** `php artisan beaches:check-missing-images`

**Location:** `app/Console/Commands/CheckMissingBeachImages.php`

### Features

- ✅ **Comprehensive Check** - Verifies all active beach images against DigitalOcean Spaces
- ✅ **Detailed Report** - Generates a text file with complete missing images information
- ✅ **Flexible Filtering** - Can check all beaches or filter by specific beach ID or folder name
- ✅ **Progress Tracking** - Shows progress bar during execution
- ✅ **Summary Statistics** - Displays total beaches, images checked, found, and missing

### Command Options

```bash
# Check all beaches (generates missing_images_report.txt)
./vendor/bin/sail artisan beaches:check-missing-images

# Check specific beach by ID
./vendor/bin/sail artisan beaches:check-missing-images --beach-id=123

# Check specific folder
./vendor/bin/sail artisan beaches:check-missing-images --folder-name=hr-13-k-istra

# Custom output file
./vendor/bin/sail artisan beaches:check-missing-images --output=my_report.txt

# Production
php artisan beaches:check-missing-images
```

### Report Contents

The generated report includes:

1. **Summary Section:**
   - Total beaches checked
   - Total images checked
   - Images found vs missing
   - Number of beaches with missing images

2. **Detailed Missing Images:**
   - Beach ID and folder name
   - Mobile upload flag
   - Total images vs found images
   - List of missing files with:
     - Media ID
     - File name
     - Database file path
     - Expected path in Spaces
     - Primary image flag
     - Error messages (if any)

3. **Quick Reference:**
   - List of all folder names with missing images

### Example Output

```
MISSING BEACH IMAGES REPORT
Generated: 2025-11-14 10:30:00
================================================================================

SUMMARY:
  Total beaches checked: 1167
  Total images checked: 4523
  Images found: 4450
  Images missing: 73
  Beaches with missing images: 15
================================================================================

BEACHES WITH MISSING IMAGES:
--------------------------------------------------------------------------------

Beach ID: 123
Folder Name: hr-13-k-istra
Mobile Upload: No
Total Images: 8
Found: 6
Missing: 2

Missing Files:
  - Media ID: 456
    File: hr-13-k-istra-sl3.jpg
    Path: img/beaches/hr-13-k-istra/hr-13-k-istra-sl3.jpg
    Expected: public/img/beaches/hr-13-k-istra/hr-13-k-istra-sl3.jpg
    Primary: No

--------------------------------------------------------------------------------
```

### How It Works

1. **Query Active Images:**
   - Fetches all beaches with `folder_name` set
   - Only checks media with `status_id = 1` (active)
   - Only checks media type `image` (not videos or 360° spheres)

2. **Path Resolution:**
   - Database stores paths without `public/` prefix (e.g., `img/beaches/...`)
   - DigitalOcean Spaces stores with `public/` prefix
   - Script automatically prepends `public/` when checking existence

3. **Storage Check:**
   - Uses Laravel's `Storage::disk('spaces')` facade
   - Calls `exists()` method for each image path
   - Catches exceptions for connection errors

4. **Report Generation:**
   - Collects all missing images per beach
   - Generates formatted text report
   - Saves to file and displays console summary

### Use Cases

1. **After Migration** - Verify all images were successfully uploaded to Spaces
2. **Regular Audits** - Periodic checks to ensure data integrity
3. **Troubleshooting** - Identify specific missing images when users report issues
4. **Bulk Re-upload** - Generate list of folders that need images re-uploaded

### Notes

- **Performance:** Checking thousands of images can take several minutes
- **Network:** Requires active connection to DigitalOcean Spaces
- **Credentials:** Ensure `.env` has correct `DO_SPACES_*` configuration
- **Path Format:** Database paths should NOT include `public/` prefix (script adds it)

### Related Files

- Command: `app/Console/Commands/CheckMissingBeachImages.php`
- Model: `app/Models/Beach.php`
- Model: `app/Models/Media.php`
- Config: `config/filesystems.php` (Spaces disk configuration)

---

## 2025-12-01 - Meilisearch Hetzner Deployment Guide

### Overview

Created comprehensive deployment documentation for setting up Meilisearch on a dedicated Hetzner server, since Laravel Cloud doesn't support Meilisearch natively.

### Why Separate Server?

- ✅ **Dedicated Resources** - Search operations don't compete with API/database
- ✅ **Independent Scaling** - Scale search independently from main infrastructure
- ✅ **Better Security** - Isolated network, separate firewall rules
- ✅ **Cost Efficient** - Hetzner pricing (~€8/month) vs managed services (€50+/month)
- ✅ **High Availability** - Main API continues working if search is down

### Documentation Created

1. **Full Setup Guide** - `docs/deployment/MEILISEARCH_HETZNER_SETUP.md`
   - Comprehensive step-by-step guide (300+ lines)
   - Covers server creation, installation, SSL setup, security, monitoring, backups
   - Detailed troubleshooting section
   - Production-ready configuration

2. **Quick Start Guide** - `docs/deployment/MEILISEARCH_QUICK_START.md`
   - Condensed 30-minute setup checklist
   - Essential commands only
   - Quick reference for experienced users

3. **Automated Setup Script** - `docs/deployment/scripts/setup-meilisearch-hetzner.sh`
   - Bash script that automates entire setup
   - Interactive prompts for domain and email
   - Generates secure master key
   - Configures systemd, nginx, SSL, firewall, backups
   - ~5 minutes hands-on time

4. **Deployment Summary** - `MEILISEARCH_DEPLOYMENT_SUMMARY.md`
   - Overview of all documentation
   - Quick reference guide
   - Architecture diagram
   - Cost breakdown
   - Maintenance schedule

### Server Specifications

**Recommended (Production):**
- Type: Hetzner CPX22 (or CPX21 if available)
- vCPU: 2-3 cores
- RAM: 4 GB
- Storage: 80 GB SSD
- Cost: €5-8/month
- Location: Nuremberg, Germany

**Note:** CPX22 (2 vCPU, 4GB RAM, 80GB SSD) is often the lowest available option and works perfectly.

**Current Data Size:**
- ~4,120 documents (beaches, cities, islands, regions, countries)
- ~50-100 MB indexed data

### Setup Process

**Option 1: Automated Script (Recommended)**
```bash
ssh root@YOUR_SERVER_IP
wget https://raw.githubusercontent.com/.../setup-meilisearch-hetzner.sh
chmod +x setup-meilisearch-hetzner.sh
sudo ./setup-meilisearch-hetzner.sh
```

**Option 2: Manual Setup**
Follow `docs/deployment/MEILISEARCH_QUICK_START.md`

### Configuration

**On Hetzner Server:**
- Domain: `search.beachrex.com`
- SSL: Let's Encrypt (auto-renewing)
- Port: 443 (HTTPS only)
- Firewall: UFW (ports 22, 80, 443)
- Service: Systemd (auto-start on boot)

**On Laravel Cloud:**
```env
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=true
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY_HERE
```

### Post-Deployment

1. Index data: `php artisan search:index`
2. Verify: `curl https://search.beachrex.com/health`
3. Test search: `curl "https://api.beachrex.com/api/v1/search?q=miami"`

### Maintenance

- **Daily:** Automated backups (2 AM)
- **Weekly:** Review logs, check disk space
- **Monthly:** Test backup restoration, update packages

### Security Features

- ✅ Firewall configured (UFW)
- ✅ SSL certificate (Let's Encrypt)
- ✅ Master key authentication
- ✅ Localhost-only binding
- ✅ Nginx reverse proxy
- ✅ Non-root service user
- ✅ Strict file permissions

### Cost

- Hetzner CPX22: €5-8/month
- Total yearly: ~€60-96
- Bandwidth: Included (20TB)

**Comparison:**
- Algolia: ~€50-200/month
- Elasticsearch Cloud: ~€50-100/month
- **Hetzner + Meilisearch: €5-8/month** ✅

### Important Setup Notes

1. **Stay as root during installation** - Don't create or switch to non-root user until after Meilisearch is installed
2. **Cloudflare DNS** - Set Proxy status to "DNS only" (gray cloud), not "Proxied" (orange cloud)
3. **Nginx configuration** - Start with HTTP-only config, then let Certbot add SSL automatically to avoid certificate errors
4. **Firewall order** - Allow SSH (port 22) BEFORE enabling UFW to avoid lockout

### Local Development Configuration

The local Meilisearch service has been **removed from docker-compose.yml**. Both local development and production now use the remote Hetzner Meilisearch server.

**Benefits:**
- ✅ Consistency between development and production
- ✅ Shared indexed data across all developers
- ✅ Less local resources needed
- ✅ Faster setup for new developers

**Configuration:**
```env
# Local .env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://search.beachrex.com
MEILISEARCH_KEY=YOUR_MASTER_KEY
SCOUT_QUEUE=false  # Immediate indexing for development
```

See `docs/deployment/MEILISEARCH_LOCAL_SETUP.md` for detailed configuration.

---

## 2025-12-01 - Country endpoints: support for code or slug

- Updated `CountryController@show` to accept either a 2–3 letter ISO country code (e.g. `it`, `IT`) or a localized slug (e.g. `italy`, `slovenia`).
  - Detection: `/^[A-Za-z]{2,3}$/` is treated as a code (normalized to upper case); everything else is treated as a slug (normalized to lower case).
  - Slug lookups use `country_translation.slug` with locale fallback: first the current `app()->getLocale()`, then English (`en`).
  - Response continues to be shaped by `CountryResource`, including the localized `slug` field.
  - Caching key now encodes identifier type and normalized value: `country:show:{locale}:{code|slug}:{normalized}` to avoid collisions.
- Updated `CountryController@weather` to accept the same `codeOrSlug` identifier and reuse the same code/slug + locale-fallback lookup pattern before building the `WeatherConfigurationResponse`.
- API routes were updated so both endpoints now use `{codeOrSlug}` instead of `{code}`:
  - `GET /api/v1/countries/{codeOrSlug}`
  - `GET /api/v1/countries/{codeOrSlug}/weather`
- Swagger/OpenAPI annotations for both endpoints were updated:
  - Path templates now use `{codeOrSlug}`.
  - Path parameter description explicitly mentions "code or localized slug" and includes examples for both forms.
  - Existing response schema (including `slug`, navigation block, and weather shape) remains unchanged.

**Examples**

- `GET /api/v1/countries/it` → lookup by ISO code (Italian country, any locale)
- `GET /api/v1/countries/italy` → lookup by localized slug; with `Accept-Language: en`, uses `italy` in `en` translation; with `Accept-Language: de`, prefers `de` translation if available, otherwise falls back to `en`.
- `GET /api/v1/countries/italy/weather` → same identifier handling as above, returns the country-centroid weather configuration.

Notes:
- Existing integrations that use codes (e.g. `/api/v1/countries/it`) keep working without changes.
- For new frontend work, prefer using slugs in URLs for SEO-friendly paths, aligned with regions/cities behavior (`/countries/italy`, `/regions/istria`, etc.).


This file tracks backend changes that are important for frontend integration and future development.

## 📚 Documentation

All technical documentation is organized in the **[docs/](./docs/README.md)** folder:

- **[docs/weather/](./docs/weather/README.md)** - OpenWeatherMap integration (weather endpoints)
- **[docs/api/](./docs/api/)** - API endpoint documentation
- **[docs/admin/](./docs/admin/)** - Admin panel documentation
- **[docs/features/](./docs/features/)** - Feature-specific guides (Imagor, translations, etc.)
- **[docs/deployment/](./docs/deployment/)** - Deployment and server configuration

👉 **Start here:** [docs/README.md](./docs/README.md)

---

## 2025-11-30 – Meilisearch Global Search Integration

### 1. Overview

**Context**
- Integrated Meilisearch for powerful global search across beaches, cities, islands, regions, and countries
- Provides instant search with typo tolerance, faceted filtering, and grouped results
- Similar to Laravel Filament admin search experience
- Self-hosted solution for full control and cost efficiency

**Benefits**
- ✅ Instant search results (<50ms response time)
- ✅ Typo-tolerant search (handles misspellings)
- ✅ Grouped results by entity type (beaches, cities, islands, regions, countries)
- ✅ Filterable by country, entity type
- ✅ Cached results for optimal performance
- ✅ Only indexes active entities (status_id = 1 and has active beaches)
- ✅ Fully documented in Swagger/OpenAPI

---

### 2. API Endpoint

**Endpoint:** `GET /api/v1/search`

**Headers:**
- `Accept-Language` (string, optional) - Locale for localized slugs (e.g., `en`, `si`, `de`, `hr`). Defaults to `en` if not provided or translation not available.

**Query Parameters:**
- `q` (string, required) - Search query string (min: 1, max: 255 characters)
- `limit` (integer, optional) - Maximum results per entity type (default: 5, max: 20)
- `country_code` (string, optional) - Filter by country code (e.g., `HR`, `US`)
- `types` (string, optional) - Comma-separated entity types to search (e.g., `beach,city,island,region,country`)

**Localization:**
- All slugs in the response are localized based on the `Accept-Language` header
- Includes all necessary slugs for URL building: `continent_slug`, `country_slug`, `region_slug`, `city_slug`, `island_slug`
- Falls back to English (`en`) if translation not available for requested locale
- Cache keys include locale to ensure correct localized results

**Examples:**
```bash
# Search for "miami" across all entity types with English slugs
curl -H "Accept-Language: en" "http://localhost/api/v1/search?q=miami"

# Search with Slovenian slugs
curl -H "Accept-Language: si" "http://localhost/api/v1/search?q=miami"

# Search for "split" with max 10 results per type
GET /api/v1/search?q=split&limit=10

# Search for beaches and cities only in Croatia
GET /api/v1/search?q=beach&types=beach,city&country_code=HR

# Search for "hvar" in all types
GET /api/v1/search?q=hvar
```

**Response Format:**
```json
{
  "success": true,
  "data": {
    "beaches": [
      {
        "id": 123,
        "name": "Miami Beach",
        "slug": "miami-beach",
        "country_code": "US",
        "country_name": "United States",
        "region_name": "Florida",
        "city_name": "Miami",
        "island_name": "",
        "main_image": "miami-beach.jpg",
        "type": "beach"
      }
    ],
    "cities": [
      {
        "id": 45,
        "code": "miami",
        "name": "Miami",
        "slug": "miami",
        "country_code": "US",
        "country_name": "United States",
        "region_name": "Florida",
        "island_name": "",
        "main_image": "miami.jpg",
        "type": "city"
      }
    ],
    "islands": [],
    "regions": [],
    "countries": []
  },
  "meta": {
    "query": "miami",
    "total_results": 7,
    "results_by_type": {
      "beaches": 5,
      "cities": 2,
      "islands": 0,
      "regions": 0,
      "countries": 0
    }
  },
  "message": "Search completed successfully"
}
```

---

### 3. Backend Implementation

**Dependencies Installed:**
- `laravel/scout` (v10.22.1) - Laravel's official search package
- `meilisearch/meilisearch-php` (v1.16.1) - Meilisearch PHP client
- `http-interop/http-factory-guzzle` (1.2.0) - HTTP factory for Meilisearch

**Models Updated:**
All searchable models now include the `Laravel\Scout\Searchable` trait:
- `app/Models/Beach.php`
- `app/Models/City.php`
- `app/Models/Island.php`
- `app/Models/Region.php`
- `app/Models/Country.php`

Each model implements:
- `toSearchableArray()` - Defines which fields to index
- `searchableAs()` - Defines the index name
- `shouldBeSearchable()` - Determines if entity should be indexed (only active entities with beaches)

**Controller Created:**
- `app/Http/Controllers/Api/V1/SearchController.php` - Handles global search requests with full Swagger/OpenAPI documentation

**Route Added:**
- `GET /api/v1/search` - Global search endpoint

**Artisan Command Created:**
- `app/Console/Commands/IndexSearchableModels.php` - Command to index all searchable models

**Swagger/OpenAPI Documentation:**
- Full API documentation with request/response examples
- Tag: "Search" - Global search API endpoints
- All parameters, responses, and error codes documented
- TypeScript interfaces can be auto-generated from Swagger schema

---

### 4. Configuration

**Environment Variables (.env):**
```env
SCOUT_DRIVER=meilisearch
SCOUT_PREFIX=
SCOUT_QUEUE=false  # Set to true for production with queue workers

# Local dev (Docker Compose): http://meilisearch:7700
# Production server: http://localhost:7700 or http://127.0.0.1:7700
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey  # Change to secure key in production
MEILISEARCH_NO_ANALYTICS=true
MEILISEARCH_ENV=development  # Use 'production' on server
FORWARD_MEILISEARCH_PORT=7700
```

**Meilisearch Index Settings (config/scout.php):**
```php
'index-settings' => [
    'beaches' => [
        'filterableAttributes' => ['country_code', 'region_name', 'city_name', 'island_name', 'type'],
        'sortableAttributes' => ['name'],
        'searchableAttributes' => ['name', 'description', 'country_name', 'region_name', 'city_name', 'island_name'],
    ],
    'cities' => [
        'filterableAttributes' => ['country_code', 'region_name', 'type'],
        'sortableAttributes' => ['name'],
        'searchableAttributes' => ['name', 'country_name', 'region_name'],
    ],
    // ... similar for islands, regions, countries
]
```

---

### 5. Deployment & Setup

**Docker Compose Configuration:**

Meilisearch service is already added to `docker-compose.yml`:
```yaml
services:
  meilisearch:
    image: 'getmeili/meilisearch:v1.11'
    ports:
      - '${FORWARD_MEILISEARCH_PORT:-7700}:7700'
    environment:
      MEILI_MASTER_KEY: '${MEILISEARCH_KEY:-masterKey}'
      MEILI_NO_ANALYTICS: '${MEILISEARCH_NO_ANALYTICS:-true}'
      MEILI_ENV: '${MEILISEARCH_ENV:-development}'
    volumes:
      - 'sail-meilisearch:/meili_data'
    networks:
      - sail
    healthcheck:
      test:
        - CMD
        - wget
        - '--no-verbose'
        - '--spider'
        - 'http://localhost:7700/health'
      retries: 3
      timeout: 5s

volumes:
  sail-meilisearch:
    driver: local
```

**Environment Variables (.env):**
```env
# Scout Search Configuration
SCOUT_DRIVER=meilisearch
SCOUT_QUEUE=false  # Set to true for production with queue workers

# Meilisearch Configuration
# ⚠️ IMPORTANT: Local dev uses "meilisearch", production uses "localhost" or server IP
# Local dev (Docker Compose): http://meilisearch:7700
# Production server: http://localhost:7700 or http://127.0.0.1:7700
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey  # Change to secure key in production
MEILISEARCH_NO_ANALYTICS=true
MEILISEARCH_ENV=development  # Use 'production' on server
FORWARD_MEILISEARCH_PORT=7700
```

**Step 1: Start Meilisearch**
```bash
./vendor/bin/sail up -d meilisearch
```

**Step 2: Index All Models (First Time Only)**
```bash
# Index all models at once
./vendor/bin/sail artisan search:index

# Or index specific model
./vendor/bin/sail artisan search:index Beach

# Flush and re-index (useful after schema changes)
./vendor/bin/sail artisan search:index --flush
```

**Step 3: Verify Indexing**
Visit Meilisearch dashboard at `http://localhost:7700` to verify indexes are created.

**⚠️ Important Notes:**
- **Automatic Indexing:** After initial setup, models automatically update the search index when created/updated/deleted
- **No Manual Re-indexing Needed:** When you add/edit beaches in Filament admin, they're automatically indexed
- **Only Re-index When:** Changing `toSearchableArray()` method or index settings in `config/scout.php`
- **Production:** Set `SCOUT_QUEUE=true` and run queue workers for better performance

---

### 6. Frontend Integration

**TypeScript Interface (Auto-generate from Swagger):**
```typescript
interface SearchResult {
  id: number;
  name: string;
  slug: string;  // Localized based on Accept-Language header
  country_code: string;
  country_name: string;
  region_name?: string;
  city_name?: string;
  island_name?: string;
  main_image: string;
  type: 'beach' | 'city' | 'island' | 'region' | 'country';
  // URL building slugs (all localized)
  continent_slug: string;
  country_slug: string;
  region_slug?: string | null;
  city_slug?: string | null;
  island_slug?: string | null;
}

interface SearchResponse {
  success: boolean;
  data: {
    beaches: SearchResult[];
    cities: SearchResult[];
    islands: SearchResult[];
    regions: SearchResult[];
    countries: SearchResult[];
  };
  meta: {
    query: string;
    total_results: number;
    results_by_type: {
      beaches: number;
      cities: number;
      islands: number;
      regions: number;
      countries: number;
    };
  };
  message: string;
}
```

**Example Frontend Usage:**
```typescript
// Search function
async function globalSearch(query: string, limit: number = 5): Promise<SearchResponse> {
  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_ENDPOINT}/api/v1/search?q=${encodeURIComponent(query)}&limit=${limit}`
  );
  return response.json();
}

// Usage in component
const results = await globalSearch('miami', 5);

// Display grouped results
results.data.beaches.forEach(beach => {
  console.log(`Beach: ${beach.name} in ${beach.city_name}, ${beach.country_name}`);
});

results.data.cities.forEach(city => {
  console.log(`City: ${city.name}, ${city.country_name}`);
});
```

---

### 7. Cache Strategy

- Search results are cached for **5 minutes** (SHORT_TTL)
- Cache key includes query, limit, country_code, and types
- Cache is automatically invalidated when models are updated (Scout handles this)

---

### 8. Performance Notes

- Meilisearch typically responds in <50ms
- Results are cached for 5 minutes to reduce load
- Only active entities (status_id = 1) are indexed
- Geographic entities only indexed if they have active beaches
- Automatic re-indexing when models are created/updated/deleted

---

### 9. Testing

**Test Search Endpoint:**
```bash
# Test basic search
curl "http://localhost/api/v1/search?q=miami"

# Test with filters
curl "http://localhost/api/v1/search?q=beach&country_code=HR&limit=10"

# Test specific types
curl "http://localhost/api/v1/search?q=split&types=beach,city"
```

---

### 10. Maintenance

**Re-index All Models:**
```bash
./vendor/bin/sail artisan search:index --flush
```

**Re-index Specific Model:**
```bash
./vendor/bin/sail artisan search:index Beach --flush
```

**Check Scout Queue (if using queues):**
```bash
./vendor/bin/sail artisan queue:work
```

---

## 2025-11-30 – Most Visited Beaches Endpoint

### 1. Overview

**Context**
- Added new endpoint to fetch most visited beaches based on `beach_views` counter
- Supports filtering by continent, country, region, city, or island
- Returns up to 20 beaches by default (configurable, max 100)
- Cached for 24 hours for optimal performance

**Benefits**
- ✅ Frontend can display "Most Visited" sections for any geographic entity
- ✅ Reusable across continent, country, region, city, and island pages
- ✅ Efficient caching reduces database load
- ✅ Fully documented in Swagger/OpenAPI

---

### 2. API Endpoint

**Endpoint:** `GET /api/v1/beaches/most-visited`

**Query Parameters:**
- `continent_code` (string, optional) - Filter by continent code (e.g., `EU`, `AS`)
- `country_code` (string, optional) - Filter by country code (e.g., `HR`, `IT`, `SI`)
- `region_code` (string, optional) - Filter by region code (e.g., `dalmatia`, `istria`)
- `city_code` (string, optional) - Filter by city code (e.g., `split`, `dubrovnik`)
- `island_code` (string, optional) - Filter by island code (e.g., `hvar`, `krk`)
- `limit` (integer, optional) - Number of beaches to return (default: 20, max: 100)

**Headers:**
- `Accept-Language` (string, optional) - Locale for translations (e.g., `en`, `de`, `hr`, `sl`, `it`)

**Examples:**
```bash
# Get top 20 most visited beaches globally
GET /api/v1/beaches/most-visited

# Get top 10 most visited beaches in Croatia
GET /api/v1/beaches/most-visited?country_code=HR&limit=10

# Get top 5 most visited beaches in Dalmatia region
GET /api/v1/beaches/most-visited?region_code=dalmatia&limit=5

# Get top 20 most visited beaches on Hvar island
GET /api/v1/beaches/most-visited?island_code=hvar

# Get top 15 most visited beaches in Split city
GET /api/v1/beaches/most-visited?city_code=split&limit=15

# Get top 20 most visited beaches in Europe
GET /api/v1/beaches/most-visited?continent_code=EU
```

**Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "Paradise Beach",
      "slug": "paradise-beach",
      "latitude": "43.5081",
      "longitude": "16.4402",
      "country_code": "HR",
      "country_name": "Croatia",
      "country_slug": "croatia",
      "continent_code": "EU",
      "continent_slug": "europe",
      "region_slug": "dalmatia-split",
      "island_slug": "hvar",
      "city_slug": "hvar",
      "main_image": "img/beaches/paradise/main.jpg"
    }
  ],
  "message": "Most visited beaches retrieved successfully",
  "meta": {
    "total": 20,
    "limit": 20
  }
}
```

---

### 3. Implementation Details

**File:** `app/Http/Controllers/Api/V1/BeachController.php`

**Method:** `mostVisited(Request $request)`

**Key Features:**
- Orders beaches by `beach_views` field (descending)
- Only returns active beaches (`status_id = 1`)
- Loads all necessary relationships (translations, geographic entities, media)
- Generates cache key based on filters and locale
- Cache TTL: 24 hours (86400 seconds)

**Cache Key Format:**
```
beaches:most_visited:{locale}:{filters}
```

**Examples:**
- `beaches:most_visited:en:all` - All beaches, English
- `beaches:most_visited:en:country:HR_limit:10` - Top 10 Croatian beaches, English
- `beaches:most_visited:hr:region:dalmatia_limit:5` - Top 5 Dalmatia beaches, Croatian
- `beaches:most_visited:en:island:hvar_limit:20` - Top 20 Hvar beaches, English

---

### 4. Route Configuration

**File:** `routes/api.php`

**Route Added:**
```php
Route::get('/beaches/most-visited', [BeachController::class, 'mostVisited']);
```

**Important:** This route must be defined BEFORE the `beaches/{id}` route to prevent route conflicts.

---

### 5. Swagger Documentation

**Fully documented in OpenAPI/Swagger:**
- Operation ID: `getMostVisitedBeaches`
- Tag: `Beaches`
- All query parameters documented with examples
- Response schema references `BeachSchema`
- Includes meta information (total, limit)

**Regenerate Swagger docs:**
```bash
./vendor/bin/sail artisan l5-swagger:generate
```

---

### 6. Frontend Integration

**Use Cases:**
1. **Homepage:** Display "Most Visited Beaches" globally
2. **Country Pages:** Show most visited beaches in that country
3. **Region Pages:** Show most visited beaches in that region
4. **City Pages:** Show most visited beaches in that city
5. **Island Pages:** Show most visited beaches on that island

**Example Frontend Code:**
```typescript
// Get most visited beaches in Croatia
const response = await fetch('/api/v1/beaches/most-visited?country_code=HR&limit=10', {
  headers: {
    'Accept-Language': 'en'
  }
});
const data = await response.json();
const beaches = data.data; // Array of beach objects
```

---

### 7. Testing

**Test the endpoint:**
```bash
# Test globally
curl http://localhost/api/v1/beaches/most-visited

# Test with filters
curl "http://localhost/api/v1/beaches/most-visited?country_code=HR&limit=5"

# Test with locale
curl -H "Accept-Language: hr" "http://localhost/api/v1/beaches/most-visited?region_code=dalmatia"
```

---

## 2025-11-29 – Islands API Region Filtering & Test Database Fix

### 1. Overview

**Context**
- Added `region_code` filtering to Islands API (index, show, and weather endpoints)
- Updated Island model with `scopeByRegion()` method supporting both region ID and code
- Fixed test database setup issues with data-seeding migrations
- Updated all Swagger/OpenAPI documentation
- Ensured proper Redis cache key segmentation for all filter combinations

**Benefits**
- ✅ Islands can now be filtered by region (e.g., `?region_code=kvarner`)
- ✅ Filters can be combined (e.g., `?country_code=HR&region_code=kvarner`)
- ✅ Cache keys properly segmented to prevent collisions
- ✅ All tests passing (7/7 Island API tests)
- ✅ Consistent filtering across all geographic entity APIs

---

### 2. API Changes

#### 2.1 Islands API - New Region Filtering

**Endpoints Updated:**
1. `GET /api/v1/islands` - List all islands
2. `GET /api/v1/islands/{slug}` - Get single island
3. `GET /api/v1/islands/{slug}/weather` - Get island weather

**New Query Parameter:**
- `region_code` (string, optional) - Filter islands by region code (e.g., `istria`, `kvarner`, `dalmatia-split`)

**Examples:**
```bash
# Get all islands in Kvarner region
GET /api/v1/islands?region_code=kvarner

# Get all islands in Croatia's Kvarner region
GET /api/v1/islands?country_code=HR&region_code=kvarner

# Get Krk island details (only if in Kvarner region)
GET /api/v1/islands/krk?region_code=kvarner

# Get weather for Krk island (validate it's in Kvarner)
GET /api/v1/islands/krk/weather?region_code=kvarner
```

**Filter Behavior:**
- All filters are optional and can be combined
- When `region_code` is provided on `show` endpoint, returns 404 if island doesn't match
- Filters work with both region ID (numeric) and region code (string)

---

### 3. Model Changes

#### 3.1 Island Model - New Scope

**File:** `app/Models/Island.php`

**New Method:**
```php
/**
 * Scope to filter islands by region (accepts region ID or code)
 *
 * @param \Illuminate\Database\Eloquent\Builder $query
 * @param int|string $regionIdOrCode Region ID (numeric) or region code (string, e.g., istria, kvarner)
 * @return \Illuminate\Database\Eloquent\Builder
 *
 * @example Island::byRegion('kvarner')->get()  // Get all islands in Kvarner region
 * @example Island::byRegion(7)->get()          // Get all islands in region ID 7
 */
public function scopeByRegion($query, $regionIdOrCode)
{
    if (is_numeric($regionIdOrCode)) {
        return $query->where('region_id', $regionIdOrCode);
    }
    return $query->whereHas('region', function ($q) use ($regionIdOrCode) {
        $q->where('code', $regionIdOrCode);
    });
}
```

---

### 4. Cache Key Updates

**Cache Key Format:**
```
islands:index:{locale}:country:{code}:region:{code}
island:show:{locale}:{slug}:{country_code}:{region_code}
island:weather:{locale}:{slug}:{country_code}:{region_code}
```

**Examples:**
- `islands:index:en:all` - All islands, English
- `islands:index:en:country:HR` - Croatian islands, English
- `islands:index:en:country:HR:region:kvarner` - Kvarner islands, English
- `island:show:en:krk:HR:kvarner` - Krk island with filters

---

### 5. Data Seeding Architecture Fix

**Issue:**
- Initially created migrations for data operations (assigning badges/articles, cleaning data)
- **Problem:** Migrations run BEFORE seeders, so:
  - Badge/article assignments would fail (foreign key violations)
  - Data cleanup operations would run on empty tables
- This would cause failures on fresh production database setup

**Solution:**
Converted all data-manipulation migrations to proper seeders that run AFTER data is seeded:

**Files Created:**
- `database/seeders/RegionBadgeArticleSeeder.php` - Assigns badges and articles to regions
- `database/seeders/CityBadgeArticleSeeder.php` - Assigns badges and articles to cities
- `database/seeders/DataCleanupSeeder.php` - Performs data quality fixes (runs LAST):
  - Removes leading slashes from icon paths (waterbody, beach_type, user_type)
  - Fixes escaped quotes in beach translations (name, description, slug)
  - Removes double spaces from beach translations

**Files Deleted:**
- `database/migrations/2025_11_29_212833_assign_badges_and_articles_to_regions.php` (converted to seeder)
- `database/migrations/2025_11_29_220741_assign_badges_and_articles_to_cities.php` (converted to seeder)
- `database/migrations/2025_11_26_000001_remove_leading_slashes_from_icon_paths.php` (converted to seeder)
- `database/migrations/2025_11_26_000002_fix_escaped_quotes_in_beach_translations.php` (converted to seeder)
- `database/migrations/2025_11_26_000003_remove_double_spaces_from_beach_translations.php` (converted to seeder)

**DatabaseSeeder Updated:**
Added new seeders in correct order:
```php
IslandSeeder::class,
ArticleSeeder::class,
BadgeSeeder::class,
RegionBadgeArticleSeeder::class,  // Assign badges/articles to regions (after BadgeSeeder/ArticleSeeder)
CityBadgeArticleSeeder::class,    // Assign badges/articles to cities (after BadgeSeeder/ArticleSeeder)
GenderSeeder::class,
// ... other seeders
GeographicEntityMainImageSeeder::class,
DataCleanupSeeder::class,  // Data cleanup operations (run LAST after all data is seeded)
```

**Impact:**
- ✅ Seeders run in correct order (data created first, then manipulated)
- ✅ Works correctly on fresh production database setup
- ✅ All tests passing
- ✅ Data quality fixes applied after seeding

---

### 6. Swagger Documentation Updates

**Files Updated:**
- `app/Http/Controllers/Api/V1/IslandController.php` - Added `region_code` parameter documentation
- `app/Swagger/Schemas/IslandSchema.php` - Added `country_code` and `region_id` properties

**Parameter Documentation:**
```php
@OA\Parameter(
    name="region_code",
    in="query",
    description="Filter islands by region code (e.g., istria, kvarner, dalmatia-split). Can be combined with country_code.",
    required=false,
    @OA\Schema(type="string", example="kvarner")
)
```

---

### 7. Test Updates

**File:** `tests/Feature/IslandApiTest.php`

**Changes:**
- Added `country_code` to island test data to support filtering tests
- All 7 tests passing:
  - ✅ can get all islands
  - ✅ returns all islands with beaches
  - ✅ can get single island by slug
  - ✅ returns 404 for nonexistent island
  - ✅ islands respect locale
  - ✅ islands only return those with active beaches
  - ✅ island includes beach count

---

## 2025-11-29 – Geographic Entity Main Image Seeder & API Consistency

### 1. Overview

**Context**
- Created database seeder to automatically populate `main_image` for all geographic entities
- Images are sourced from the most viewed beach in each geographic segment
- Standardized all geographic entity APIs to use `main_image` field consistently
- Removed `image_path` from Country API responses for consistency
- Updated all Swagger/OpenAPI documentation

**Benefits**
- ✅ Automatic image population based on actual beach data
- ✅ Images represent the most popular beach in each geographic area
- ✅ Consistent API field naming across all geographic entities
- ✅ No manual image upload required for initial data
- ✅ Can be re-run to update images based on latest beach view counts

---

### 2. Database Seeder

#### 2.1 GeographicEntityMainImageSeeder

**File:** `database/seeders/GeographicEntityMainImageSeeder.php`

**Purpose:** Populates `main_image` field for all geographic entities (continents, countries, regions, islands, cities) by selecting the primary image from the most viewed beach in each segment.

**Selection Logic:**
1. Finds the beach with highest `beach_views` for each geographic entity
2. Only considers beaches with `status_id = 1` (active beaches)
3. Gets the primary image from beach's media gallery:
   - Filters: `media_type_id = 1` (images), `media_collection_id = 1` (beach_gallery), `status_id = 1`
   - Prioritizes: `is_primary = 1` (true)
   - Fallback: First image with `is_primary = 0` ordered by `order ASC, created_at ASC`
4. Updates entity's `main_image` field with the image path
5. Skips entities that don't have beaches or don't have valid images

**Database Fields Updated:**
- `continents.main_image`
- `countries.image_path` (aliased as `main_image` in API)
- `region.main_image`
- `island.main_image`
- `city.main_image`

**How to Run:**
```bash
# Development
./vendor/bin/sail artisan db:seed --class=GeographicEntityMainImageSeeder

# Production
php artisan db:seed --class=GeographicEntityMainImageSeeder
```

**Results (as of 2025-11-29):**
- ✅ 1 continent populated (EU - Europe)
- ✅ 13 countries populated
- ✅ 73 regions populated
- ✅ 29 islands populated
- ✅ 1050 cities populated

**Image Path Format:**
- Web uploads: `img/beaches/{folder_name}/{filename}`
- Mobile uploads: `img/beaches_mobile/{folder_name}/{filename}`
- Path determined by `beach.mobile_upload` boolean field

**Safe to Re-run:**
- Yes, can be run multiple times to update images based on latest view counts
- Will overwrite existing `main_image` values

---

### 3. API Changes - Consistent `main_image` Field

#### 3.1 Country API Standardization

**Previous Behavior:**
- Country API returned both `image_path` and `main_image` (duplicate data)

**New Behavior:**
- Country API now returns only `main_image` (consistent with all other entities)
- Database still uses `image_path` column, but it's mapped to `main_image` in API responses

**Files Updated:**
- `app/Http/Resources/CountryResource.php` - Removed `image_path`, kept only `main_image`
- `app/Swagger/Schemas/CountrySchema.php` - Updated to show only `main_image`
- `app/Http/Controllers/Api/V1/CountryController.php` - Updated Swagger annotations

#### 3.2 All Geographic Entity APIs

All geographic entity list and detail endpoints now consistently return `main_image`:

**Endpoints:**
- `GET /api/v1/continents` - Returns `main_image`
- `GET /api/v1/continents/{code}` - Returns `main_image`
- `GET /api/v1/countries` - Returns `main_image` (no longer `image_path`)
- `GET /api/v1/countries/{code}` - Returns `main_image` (no longer `image_path`)
- `GET /api/v1/regions` - Returns `main_image`
- `GET /api/v1/regions/{slug}` - Returns `main_image`
- `GET /api/v1/islands` - Returns `main_image`
- `GET /api/v1/islands/{slug}` - Returns `main_image`
- `GET /api/v1/cities` - Returns `main_image`
- `GET /api/v1/cities/{slug}` - Returns `main_image`

**Files Updated:**
- `app/Http/Controllers/Api/V1/ContinentController.php` - Added `main_image` to select queries
- `app/Http/Controllers/Api/V1/RegionController.php` - Added `main_image` to select queries
- `app/Http/Controllers/Api/V1/IslandController.php` - Added `main_image` to select queries
- `app/Http/Controllers/Api/V1/CityController.php` - Added `main_image` to select queries
- `app/Http/Resources/ContinentResource.php` - Added `main_image` to response
- `app/Http/Resources/RegionResource.php` - Added `main_image` to response
- `app/Http/Resources/IslandResource.php` - Added `main_image` to response
- `app/Http/Resources/CityResource.php` - Added `main_image` to response

---

### 4. Swagger/OpenAPI Documentation Updates

All Swagger schemas and controller annotations updated to reflect `main_image` field:

**Schema Files Updated:**
- `app/Swagger/Schemas/ContinentSchema.php` - Added `main_image` property
- `app/Swagger/Schemas/CountrySchema.php` - Updated `main_image` description (removed `image_path` reference)
- `app/Swagger/Schemas/RegionSchema.php` - Added `main_image` property
- `app/Swagger/Schemas/IslandSchema.php` - Added `main_image` property
- `app/Swagger/Schemas/CitySchema.php` - Added `main_image` property

**Controller Annotations Updated:**
- `app/Http/Controllers/Api/V1/ContinentController.php` - Updated index and show endpoint schemas
- `app/Http/Controllers/Api/V1/CountryController.php` - Updated examples to match actual data format

**Property Definition:**
```php
@OA\Property(
    property="main_image",
    type="string",
    nullable=true,
    description="Relative path to main image from most viewed beach",
    example="img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg"
)
```

---

### 5. API Response Examples

#### 5.1 Continent Response
```json
{
  "code": "EU",
  "name": "Europe",
  "slug": "europe",
  "main_image": "img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg",
  "beaches_count": 2876
}
```

#### 5.2 Country Response
```json
{
  "code": "HR",
  "name": "Croatia",
  "full_name": "Croatia",
  "slug": "croatia",
  "iso3": "HRV",
  "continent_code": "EU",
  "main_image": "img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg",
  "beaches_count": 1167
}
```

#### 5.3 Region/Island/City Response
```json
{
  "id": 2,
  "code": "dalmatia-dubrovnik",
  "name": "Dalmatia - Dubrovnik",
  "slug": "dalmatia-dubrovnik",
  "country_code": "HR",
  "main_image": "img/beaches/hr-766-k-jdalmacija/hr-766-k-jdalmacija-sl1.jpg",
  "beaches_count": 166
}
```

---

### 6. Frontend Integration Notes

**Image URL Construction:**
- API returns relative paths (e.g., `img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg`)
- Frontend should prepend CDN domain: `https://beachrex.fra1.digitaloceanspaces.com/public/`
- Full URL: `https://beachrex.fra1.digitaloceanspaces.com/public/img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg`

**Imagor Integration:**
- Can use Imagor for image transformation
- Prepend Imagor domain instead of CDN domain
- Example: `http://localhost:8001/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg`

**Null Handling:**
- `main_image` is nullable
- Some entities may not have images if they don't have beaches or beaches don't have images
- Frontend should handle null values gracefully with placeholder images

**TypeScript Types:**
- All geographic entity types should include `main_image?: string | null`
- Can be auto-generated from updated Swagger/OpenAPI schema

---

## 2025-11-29 – Geographic Entity Navigation and Image Upload

### 1. Overview

**Context**
- Added `main_image` field to all geographic entities (regions, islands, cities, continents)
- Implemented S3/DigitalOcean Spaces image upload in admin panel for all geographic entities
- Added navigation (previous/next) to all geographic entity detail endpoints
- Navigation only includes entities that have beaches with `status_id = 1`
- All navigation structures now consistent across all entity types

**Benefits**
- ✅ Consistent image handling across all geographic entities
- ✅ S3-based image storage with Filament dropzone upload
- ✅ Navigation allows browsing through entities with beaches
- ✅ Full Swagger/OpenAPI documentation for all navigation structures
- ✅ Frontend can generate TypeScript types from OpenAPI schema

---

### 2. Database Changes

#### 2.1 New Fields Added

**Migration:** `database/migrations/2025_11_29_120000_add_main_image_to_geographic_entities.php`

Added `main_image` field to:
- `city` table - VARCHAR(255), nullable
- `region` table - VARCHAR(255), nullable
- `island` table - VARCHAR(255), nullable
- `continents` table - VARCHAR(255), nullable

**Note:** Countries already had `image_path` field, which is aliased to `main_image` in API responses for consistency.

**Model Updates:**
- `app/Models/City.php` - Added `main_image` to fillable
- `app/Models/Region.php` - Added `main_image` to fillable
- `app/Models/Island.php` - Added `main_image` to fillable
- `app/Models/Continent.php` - Added `main_image` to fillable

---

### 3. Admin Panel - Image Upload

All geographic entity admin resources now have S3-based image upload with Filament FileUpload component:

**Files Updated:**
- `app/Filament/BrAdmin/Resources/CountryResource.php` - Updated to use S3 (`disk('spaces')`)
- `app/Filament/BrAdmin/Resources/RegionResource.php` - Added image upload field
- `app/Filament/BrAdmin/Resources/IslandResource.php` - Added image upload field
- `app/Filament/BrAdmin/Resources/CityResource.php` - Added image upload field
- `app/Filament/BrAdmin/Resources/ContinentResource.php` - Added image upload field

**Configuration:**
- Disk: `spaces` (DigitalOcean Spaces)
- Directories:
  - Countries: `public/img/countries/`
  - Regions: `public/img/regions/`
  - Islands: `public/img/islands/`
  - Cities: `public/img/cities/`
  - Continents: `public/img/continents/`
- Image editor with aspect ratios: null, 16:9, 4:3, 1:1
- Max file size: 10MB
- Visibility: public
- Features: downloadable, openable, previewable

---

### 4. Navigation Implementation

#### 4.1 Navigation Logic

All geographic entity detail endpoints now include navigation (previous/next) in their responses:

**Endpoints with Navigation:**
- `GET /api/v1/continents/{code}` - Navigate between continents with beaches
- `GET /api/v1/countries/{code}` - Navigate between countries with beaches in same continent
- `GET /api/v1/regions/{slug}` - Navigate between regions with beaches in same country
- `GET /api/v1/islands/{slug}` - Navigate between islands with beaches in same region/country
- `GET /api/v1/cities/{slug}` - Navigate between cities with beaches in same island/region/country

**Navigation Filtering:**
All navigation queries use `EXISTS` subqueries to ensure only entities with active beaches (`status_id = 1`) are included:

- **Countries**: Only countries that have beaches with `status_id = 1`
- **Regions**: Only regions that have beaches with `status_id = 1`
- **Islands**: Only islands that have beaches with `status_id = 1`
- **Cities**: Only cities that have beaches with `status_id = 1`
- **Continents**: Only continents that have countries with beaches with `status_id = 1`

**Ordering:**
- Countries: Ordered by `code` within same continent
- Regions: Ordered by `id` within same country
- Islands: Ordered by `id` within same region (or country if no region)
- Cities: Ordered by `id` within same island/region/country
- Continents: Ordered by `code`

#### 4.2 Navigation Response Structure

All navigation responses follow the same structure with entity-specific fields and parent location slugs:

**Country Navigation:**
```json
{
  "navigation": {
    "previous": {
      "code": "HR",
      "name": "Croatia",
      "slug": "croatia",
      "latitude": "45.1",
      "longitude": "15.2",
      "main_image": "img/countries/croatia.jpg",
      "location": {
        "continent_slug": "europe"
      }
    },
    "next": { /* same structure */ }
  }
}
```

**Region Navigation:**
```json
{
  "navigation": {
    "previous": {
      "id": 1,
      "name": "Istria",
      "slug": "istria",
      "latitude": "45.1",
      "longitude": "13.9",
      "main_image": "img/regions/istria.jpg",
      "location": {
        "continent_slug": "europe",
        "country_slug": "croatia"
      }
    },
    "next": { /* same structure */ }
  }
}
```

**Island Navigation:**
```json
{
  "navigation": {
    "previous": {
      "id": 1,
      "name": "Krk",
      "slug": "krk",
      "latitude": "45.0",
      "longitude": "14.6",
      "main_image": "img/islands/krk.jpg",
      "location": {
        "continent_slug": "europe",
        "country_slug": "croatia",
        "region_slug": "kvarner"
      }
    },
    "next": { /* same structure */ }
  }
}
```

**City Navigation:**
```json
{
  "navigation": {
    "previous": {
      "id": 1,
      "name": "Split",
      "slug": "split",
      "latitude": "43.5",
      "longitude": "16.4",
      "main_image": "img/cities/split.jpg",
      "location": {
        "continent_slug": "europe",
        "country_slug": "croatia",
        "region_slug": "dalmatia",
        "island_slug": null
      }
    },
    "next": { /* same structure */ }
  }
}
```

**Continent Navigation:**
```json
{
  "navigation": {
    "previous": {
      "code": "AS",
      "name": "Asia",
      "slug": "asia",
      "main_image": "img/continents/asia.jpg",
      "location": {}
    },
    "next": { /* same structure */ }
  }
}
```

---

### 5. Controller Updates

All geographic entity controllers updated with navigation methods:

**Files Modified:**
- `app/Http/Controllers/Api/V1/ContinentController.php`
  - Added `getNavigationContinents()` method
  - Navigation filters by continents with beaches
  - SQL queries include `main_image` field

- `app/Http/Controllers/Api/V1/CountryController.php`
  - Updated `getNavigationCountries()` method
  - Navigation filters by countries with beaches in same continent
  - SQL queries include `main_image` field (aliased from `image_path`)

- `app/Http/Controllers/Api/V1/RegionController.php`
  - Added `getNavigationRegions()` method
  - Navigation filters by regions with beaches in same country
  - SQL queries include `main_image` field

- `app/Http/Controllers/Api/V1/IslandController.php`
  - Added `getNavigationIslands()` method
  - Navigation filters by islands with beaches in same region/country
  - SQL queries include `main_image` field

- `app/Http/Controllers/Api/V1/CityController.php`
  - Added `getNavigationCities()` method
  - Navigation filters by cities with beaches in same island/region/country
  - SQL queries include `main_image` field

---

### 6. Swagger/OpenAPI Documentation

All geographic entity show endpoints now have complete navigation documentation:

**Files Updated:**
- `app/Http/Controllers/Api/V1/ContinentController.php` - Added navigation schema to show endpoint
- `app/Http/Controllers/Api/V1/CountryController.php` - Updated navigation schema with `main_image`
- `app/Http/Controllers/Api/V1/RegionController.php` - Added navigation schema to show endpoint
- `app/Http/Controllers/Api/V1/IslandController.php` - Added navigation schema to show endpoint
- `app/Http/Controllers/Api/V1/CityController.php` - Added navigation schema to show endpoint

**Schema Updates:**
- `app/Swagger/Schemas/CountrySchema.php` - Changed `image_path` to `main_image`

Each navigation schema includes:
- Complete structure with all fields (id/code, name, slug, latitude, longitude, main_image)
- Location object with parent slugs
- Nullable properties where appropriate
- Examples for all fields

---

### 7. Deployment Instructions

#### 7.1 Development Environment

```bash
# Run migration
./vendor/bin/sail artisan migrate

# Clear cache
./vendor/bin/sail artisan cache:clear

# Regenerate Swagger docs
./vendor/bin/sail artisan l5-swagger:generate
```

#### 7.2 Production Server

```bash
# Run migration
php artisan migrate

# Clear cache
php artisan cache:clear

# Regenerate Swagger docs
php artisan l5-swagger:generate
```

---

### 8. Frontend Integration

#### 8.1 TypeScript Type Generation

The frontend can generate TypeScript types for all navigation structures from the OpenAPI schema:

```bash
# Generate types from Swagger/OpenAPI
npx openapi-typescript https://api.beachrex.com/api/documentation/json -o src/lib/api-client/types.gen.ts
```

This will include updated types for:
- `ContinentResponse` with navigation
- `CountryResponse` with navigation
- `RegionResponse` with navigation
- `IslandResponse` with navigation
- `CityResponse` with navigation

#### 8.2 Example Usage

```typescript
import { apiClient } from '@/lib/api-client';

// Get country with navigation
const country = await apiClient.GET('/api/v1/countries/{code}', {
  params: {
    path: { code: 'HR' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});

// Access navigation
const previousCountry = country.data?.navigation?.previous;
const nextCountry = country.data?.navigation?.next;

// Get region with navigation
const region = await apiClient.GET('/api/v1/regions/{slug}', {
  params: {
    path: { slug: 'dalmatia' },
    query: { country_code: 'HR' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});
```

#### 8.3 Image URL Construction

All `main_image` fields are relative paths. Frontend should prepend CDN/base URL:

```typescript
// For geographic entity images
const imagorEndpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT; // "https://s3.beachrex.com"
const imageUrl = `${imagorEndpoint}/unsafe/fit-in/800x600/filters:quality(90)/${entity.main_image}`;
// Result: https://s3.beachrex.com/unsafe/fit-in/800x600/filters:quality(90)/img/countries/croatia.jpg
```

---

### 9. Testing

#### 9.1 Test Navigation Endpoints

```bash
# Continent navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/continents/EU

# Country navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/countries/HR

# Region navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/regions/dalmatia?country_code=HR

# Island navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/islands/hvar

# City navigation
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/cities/split?country_code=HR
```

#### 9.2 Verify Navigation Filtering

All navigation should only include entities with beaches:

```sql
-- Verify countries in navigation have beaches
SELECT c.code, c.name, COUNT(b.id) as beach_count
FROM countries c
LEFT JOIN beach b ON b.country_code = c.code AND b.status_id = 1
GROUP BY c.code, c.name
HAVING COUNT(b.id) > 0
ORDER BY c.code;

-- Verify regions in navigation have beaches
SELECT r.id, r.code, COUNT(b.id) as beach_count
FROM region r
LEFT JOIN beach b ON b.region_id = r.id AND b.status_id = 1
GROUP BY r.id, r.code
HAVING COUNT(b.id) > 0
ORDER BY r.id;
```

---

### 10. Notes

- **Image Field Naming**: Countries use `image_path` in database but return `main_image` in API for consistency
- **Navigation Filtering**: All navigation only includes entities with active beaches (`status_id = 1`)
- **Null Values**: Navigation can be null if no previous/next entity exists
- **Location Object**: Contains only parent slugs (no child slugs)
- **Caching**: Navigation data is cached with entity details using `CacheService::REFERENCE_DATA_TTL`

---

## 2025-11-29 – Weather Endpoints for Geographic Entities

### 1. Overview

**Context**
- Extended weather functionality from beaches to all geographic entities (cities, regions, islands, countries)
- Added latitude/longitude fields to geographic entity tables
- Created seeder to populate coordinates from beach centroids
- All entities now support weather API endpoints following the same pattern as beaches

**Benefits**
- ✅ Consistent weather data across all geographic levels
- ✅ Weather endpoints for cities, regions, islands, and countries
- ✅ Coordinates calculated automatically from beach centroids
- ✅ Full Swagger/OpenAPI documentation for all new endpoints
- ✅ Frontend can generate TypeScript types from OpenAPI schema

---

### 2. Database Changes

#### 2.1 New Fields Added

**Migration:** `database/migrations/2025_11_29_000000_add_coordinates_to_geographic_entities.php`

Added `latitude` and `longitude` fields to:
- `city` table - DECIMAL(10,7), nullable, with index
- `region` table - DECIMAL(10,7), nullable, with index
- `island` table - DECIMAL(10,7), nullable, with index
- `countries` table - DECIMAL(10,7), nullable, with index

**Model Updates:**
- `app/Models/City.php` - Added `latitude`, `longitude` to fillable
- `app/Models/Region.php` - Added `latitude`, `longitude` to fillable
- `app/Models/Island.php` - Added `latitude`, `longitude` to fillable
- `app/Models/Country.php` - Added `latitude`, `longitude` to fillable

#### 2.2 Coordinate Population

**Seeder:** `database/seeders/GeographicEntityCoordinatesSeeder.php`

Populates coordinates by calculating centroid (average) from beaches:
- **Cities**: Average of all beaches where `beach.city_id = city.id`
- **Islands**: Average of all beaches where `beach.island_id = island.id`
- **Regions**: Average of all beaches in cities within the region (joins through city table)
- **Countries**: Average of all beaches where `beach.country_code = countries.code`

**Features:**
- Only uses active beaches (`status_id = 1`)
- Rounds coordinates to 7 decimal places (~1.1cm precision)
- Skips entities where coordinates are already up-to-date
- Provides detailed feedback on updated vs skipped entities
- Safe to run multiple times

**Documentation:** `database/seeders/README_GEOGRAPHIC_COORDINATES.md`

---

### 3. New API Endpoints

All endpoints follow the same pattern as beach weather endpoint, using the `ProvidesWeather` trait.

#### 3.1 City Weather

**Endpoint:** `GET /api/v1/cities/{slug}/weather`

**Parameters:**
- `slug` (path, required) - City slug (e.g., "split", "dubrovnik")
- `country_code` (query, optional) - Filter by country code to disambiguate cities with same name
- `Accept-Language` (header) - Locale for response

**Response:**
```json
{
  "success": true,
  "data": {
    "city_id": 1,
    "city_slug": "split",
    "coordinates": {
      "latitude": "43.5081000",
      "longitude": "16.4402000"
    },
    "weather": {
      "temperature": 9.9,
      "feels_like": 8.7,
      "condition": "Clouds",
      "description": "broken clouds",
      "icon": "04d",
      "icon_url": "https://openweathermap.org/img/wn/04d@2x.png",
      "wind_speed": 2.52,
      "humidity": 58,
      ...
    },
    "sea_temperature": 9.9,
    "cached_at": "2025-11-29T10:20:47+00:00"
  }
}
```

#### 3.2 Region Weather

**Endpoint:** `GET /api/v1/regions/{slug}/weather`

**Parameters:**
- `slug` (path, required) - Region slug (e.g., "dalmatia", "istria")
- `country_code` (query, optional) - Filter by country code
- `Accept-Language` (header) - Locale for response

**Response:** Same structure as city weather, with `region_id` and `region_slug`

#### 3.3 Island Weather

**Endpoint:** `GET /api/v1/islands/{slug}/weather`

**Parameters:**
- `slug` (path, required) - Island slug (e.g., "hvar", "krk", "brac")
- `Accept-Language` (header) - Locale for response

**Response:** Same structure as city weather, with `island_id` and `island_slug`

#### 3.4 Country Weather

**Endpoint:** `GET /api/v1/countries/{code}/weather`

**Parameters:**
- `code` (path, required) - Country code (e.g., "HR", "SI", "IT")
- `Accept-Language` (header) - Locale for response

**Response:** Same structure as city weather, with `country_id` and `country_slug`

---

### 4. Swagger/OpenAPI Schema Updates

#### 4.1 Entity Schemas Updated

Added `latitude` and `longitude` fields to all geographic entity schemas:

**Files Updated:**
- `app/Swagger/Schemas/CitySchema.php`
- `app/Swagger/Schemas/RegionSchema.php`
- `app/Swagger/Schemas/IslandSchema.php`
- `app/Swagger/Schemas/CountrySchema.php`

**New Properties:**
```php
@OA\Property(
    property="latitude",
    type="number",
    format="double",
    nullable=true,
    description="Entity latitude (centroid of beaches)",
    example=43.5081
),
@OA\Property(
    property="longitude",
    type="number",
    format="double",
    nullable=true,
    description="Entity longitude (centroid of beaches)",
    example=16.4402
)
```

#### 4.2 Weather Endpoint Documentation

All four controllers have full OpenAPI annotations:
- `app/Http/Controllers/Api/V1/CityController.php` - `weather()` method
- `app/Http/Controllers/Api/V1/RegionController.php` - `weather()` method
- `app/Http/Controllers/Api/V1/IslandController.php` - `weather()` method
- `app/Http/Controllers/Api/V1/CountryController.php` - `weather()` method

Each includes:
- Complete parameter documentation
- Response schemas with weather data structure
- Error responses (404, 400, 503)
- Examples for all fields

---

### 5. Implementation Details

#### 5.1 Controllers Updated

All controllers now use the `ProvidesWeather` trait:

```php
use App\Traits\ProvidesWeather;

class CityController extends Controller
{
    use CacheAware, ProvidesWeather;

    public function weather(string $slug, Request $request): JsonResponse
    {
        // Find entity by slug
        // Get entity slug in current locale
        // Use ProvidesWeather trait to build response
        return $this->buildWeatherResponse($entity, 'city', $entitySlug);
    }
}
```

#### 5.2 Routes Added

**File:** `routes/api.php`

```php
// City weather
Route::get('/cities/{slug}/weather', [CityController::class, 'weather']);

// Region weather
Route::get('/regions/{slug}/weather', [RegionController::class, 'weather']);

// Island weather
Route::get('/islands/{slug}/weather', [IslandController::class, 'weather']);

// Country weather
Route::get('/countries/{code}/weather', [CountryController::class, 'weather']);
```

---

### 6. Deployment Instructions

#### 6.1 Development Environment

```bash
# Run migration
./vendor/bin/sail artisan migrate

# Populate coordinates from beaches
./vendor/bin/sail artisan db:seed --class=GeographicEntityCoordinatesSeeder

# Clear cache
./vendor/bin/sail artisan cache:clear

# Regenerate Swagger docs
./vendor/bin/sail artisan l5-swagger:generate
```

#### 6.2 Production Server

```bash
# Run migration
php artisan migrate

# Populate coordinates from beaches
php artisan db:seed --class=GeographicEntityCoordinatesSeeder

# Clear cache
php artisan cache:clear

# Regenerate Swagger docs (if needed)
php artisan l5-swagger:generate
```

---

### 7. Frontend Integration

#### 7.1 TypeScript Type Generation

The frontend can now generate TypeScript types for all weather endpoints from the OpenAPI schema:

```bash
# Generate types from Swagger/OpenAPI
npx openapi-typescript https://api.beachrex.com/api/documentation/json -o src/lib/api-client/types.gen.ts
```

This will include types for:
- `CityWeatherResponse`
- `RegionWeatherResponse`
- `IslandWeatherResponse`
- `CountryWeatherResponse`
- Updated `City`, `Region`, `Island`, `Country` schemas with `latitude` and `longitude` fields

#### 7.2 Example Usage

```typescript
import { apiClient } from '@/lib/api-client';

// Get city weather
const cityWeather = await apiClient.GET('/api/v1/cities/{slug}/weather', {
  params: {
    path: { slug: 'split' },
    query: { country_code: 'HR' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});

// Get region weather
const regionWeather = await apiClient.GET('/api/v1/regions/{slug}/weather', {
  params: {
    path: { slug: 'dalmatia' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});

// Get island weather
const islandWeather = await apiClient.GET('/api/v1/islands/{slug}/weather', {
  params: {
    path: { slug: 'hvar' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});

// Get country weather
const countryWeather = await apiClient.GET('/api/v1/countries/{code}/weather', {
  params: {
    path: { code: 'HR' }
  },
  headers: {
    'Accept-Language': 'en'
  }
});
```

---

### 8. Testing

#### 8.1 Test Endpoints

```bash
# City weather
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/cities/split/weather

# Region weather
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/regions/dalmatia/weather

# Island weather
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/islands/hvar/weather

# Country weather
curl -H "Accept-Language: en" https://api.beachrex.com/api/v1/countries/HR/weather
```

#### 8.2 Verify Coordinates

```sql
-- Check cities with coordinates
SELECT id, code, latitude, longitude
FROM city
WHERE latitude IS NOT NULL
ORDER BY id LIMIT 10;

-- Check regions with coordinates
SELECT id, code, latitude, longitude
FROM region
WHERE latitude IS NOT NULL
ORDER BY id;

-- Check islands with coordinates
SELECT id, code, latitude, longitude
FROM island
WHERE latitude IS NOT NULL
ORDER BY id LIMIT 10;

-- Check countries with coordinates
SELECT id, code, latitude, longitude
FROM countries
WHERE latitude IS NOT NULL
ORDER BY code;
```

---

### 9. Error Handling

All weather endpoints return consistent error responses:

**404 - Entity Not Found:**
```json
{
  "success": false,
  "message": "City not found"
}
```

**400 - No Coordinates:**
```json
{
  "success": false,
  "message": "City has no coordinates for weather data"
}
```

**503 - Weather Service Unavailable:**
```json
{
  "success": false,
  "message": "Weather data temporarily unavailable"
}
```

---

### 10. Caching

Weather data is cached for 1 hour (same as beach weather):
- Cache key includes entity type, ID, and coordinates
- Coordinates rounded to 2 decimals for cache key consistency
- Cache cleared automatically after 1 hour
- Manual cache clear: `php artisan cache:clear`

---

### 11. Notes

- **Centroid Calculation**: Coordinates represent the geographic center of all active beaches within each entity
- **Null Coordinates**: Entities without beaches (or beaches without coordinates) will have NULL coordinates and return 400 error
- **Coordinate Precision**: 7 decimal places (~1.1cm precision) - more than sufficient for weather data
- **Active Beaches Only**: Only beaches with `status_id = 1` are included in centroid calculation
- **Re-running Seeder**: Safe to run multiple times - skips entities where coordinates haven't changed

---

## 2025-11-28 – Image Handling Simplified (Imagor Removed from Backend)

### 1. Overview

**Context**
- **Removed all Imagor-related code from Laravel backend**
- Frontend now handles all image transformations directly via Imagor subdomain (`s3.beachrex.com`)
- Backend only returns relative image paths (`main_image`)
- Significantly reduced API response sizes and memory usage

**Benefits**
- ✅ Simpler backend architecture (no Imagor service dependencies)
- ✅ Smaller API responses (removed `description` and `main_image_imagor` fields from list endpoints)
- ✅ Reduced memory usage when caching (fixed memory exhaustion errors)
- ✅ Frontend has full control over image transformations
- ✅ Easier to maintain and deploy

---

### 2. API Response Changes

#### 2.1 List Endpoints (Beaches, Spatial APIs)

**Affected Endpoints:**
- `GET /api/v1/beaches`
- `GET /api/v1/beaches/{id}`
- `GET /api/v1/spatial/beaches/within-radius`
- `GET /api/v1/spatial/beaches/bounding-box`

**Fields Removed:**
- ❌ `description` - Removed from list endpoints to reduce memory usage when caching
- ❌ `main_image_imagor` - Removed, frontend constructs Imagor URLs directly

**Current Response:**
```json
{
  "id": 1,
  "name": "Beach Livade i Obatnice",
  "slug": "beach-livade-i-obatnice",
  "latitude": "44.5996000",
  "longitude": "14.9876000",
  "country_code": "HR",
  "main_image": "img/beaches/hr-69-o-pag/hr-69-o-pag-sl7.jpg"
}
```

**Frontend Usage:**
```typescript
// Frontend constructs Imagor URLs
const imagorEndpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT; // "https://s3.beachrex.com"
const imagorTransform = process.env.NEXT_PUBLIC_IMAGOR_TRANSFORM; // "unsafe/fit-in/400x300/filters:quality(85)/"
const imageUrl = `${imagorEndpoint}/${imagorTransform}${beach.main_image}`;
// Result: https://s3.beachrex.com/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl7.jpg
```

---

#### 2.2 Detail Endpoints (Beach Details)

**Affected Endpoints:**
- `GET /api/v1/beach-details/{identifier}`

**Fields Removed:**
- ❌ `imagor` object - Removed from media items, frontend constructs Imagor URLs directly

**Current Response:**
```json
{
  "media": {
    "gallery": [
      {
        "id": 2,
        "file_name": "hr-69-o-pag-sl1.jpg",
        "url": "img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg",
        "mime_type": "image/jpeg",
        "size": 245678,
        "width": 1920,
        "height": 1080
      }
    ]
  }
}
```

**Frontend Usage:**
```typescript
// Frontend constructs different sizes as needed
const imagorEndpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT; // "https://s3.beachrex.com"

// Thumbnail
const thumbnailUrl = `${imagorEndpoint}/unsafe/fit-in/150x150/smart/filters:quality(85)/${media.url}`;

// Small
const smallUrl = `${imagorEndpoint}/unsafe/fit-in/400x300/filters:quality(85)/${media.url}`;

// Medium
const mediumUrl = `${imagorEndpoint}/unsafe/fit-in/800x600/filters:quality(90)/${media.url}`;

// Large
const largeUrl = `${imagorEndpoint}/unsafe/fit-in/1200x900/filters:quality(90)/${media.url}`;

// Hero/Banner
const heroUrl = `${imagorEndpoint}/unsafe/1920x600/smart/filters:quality(90)/${media.url}`;
```

---

### 3. Implementation Details

#### 3.1 Backend Files Deleted

**Services:**
- ❌ `app/Services/ImagorService.php` - Deleted (no longer needed)

**Controllers:**
- ❌ `app/Http/Controllers/Api/ImagorController.php` - Deleted (no longer needed)

**Configuration:**
- ❌ `config/imagor.php` - Deleted (no longer needed)

#### 3.2 Backend Files Modified

**Models:**
- `app/Models/Media.php`
  - ❌ Removed all Imagor methods: `imagorUrl()`, `imagorThumbnail()`, `imagorSmall()`, `imagorMedium()`, `imagorLarge()`, `imagorHero()`

**Resources:**
- `app/Http/Resources/BeachResource.php`
  - ❌ Removed `main_image_imagor` field
  - ❌ Removed `description` field (to reduce memory usage)
- `app/Http/Resources/BeachDetailsResource.php`
  - ❌ Removed `imagor` object from media items
- `app/Http/Resources/MediaResource.php`
  - ❌ Removed `imagor` property

**Services:**
- `app/Services/PostGISService.php`
  - ❌ Removed `main_image_imagor` field generation

**Routes:**
- `routes/api.php`
  - ❌ Removed all Imagor routes (`/api/v1/imagor/*`)

**Swagger:**
- `app/Swagger/Schemas/BeachSchema.php`
  - ✅ Updated to reflect `main_image` only (no `main_image_imagor`)
- `app/Swagger/Schemas/BeachDetailsSchema.php`
  - ✅ Removed `imagor` object from media schema

---

### 4. Imagor URL Format (Frontend Reference)

**Unsigned URLs:**
```
https://s3.beachrex.com/unsafe/{transformations}/{image-path}
```

**Transformation Examples:**
- `fit-in/400x300` - Resize to fit within 400x300 (maintains aspect ratio)
- `400x300` - Exact dimensions (may crop)
- `smart` - Smart crop (detects important areas)
- `filters:quality(85)` - JPEG quality 85%
- `filters:format(webp)` - Convert to WebP format

**Example URLs:**
```
# Thumbnail
https://s3.beachrex.com/unsafe/fit-in/150x150/smart/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg

# Small
https://s3.beachrex.com/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg

# Medium
https://s3.beachrex.com/unsafe/fit-in/800x600/filters:quality(90)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg

# Large
https://s3.beachrex.com/unsafe/fit-in/1200x900/filters:quality(90)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg

# Hero/Banner
https://s3.beachrex.com/unsafe/1920x600/smart/filters:quality(90)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl1.jpg
```

---

### 5. Environment Configuration

**Laravel API `.env`** (NO Imagor configuration needed):
```env
# ❌ IMAGOR_URL - Removed (not needed)
# ❌ IMAGOR_SECRET - Removed (not needed)
# ❌ IMAGOR_UNSAFE - Removed (not needed)
```

**Frontend `.env`** (Next.js on Vercel):
```env
# Imagor endpoint (subdomain pointing to Imagor service)
NEXT_PUBLIC_IMAGOR_ENDPOINT=https://s3.beachrex.com

# Optional: Default transformation for list views
NEXT_PUBLIC_IMAGOR_TRANSFORM=unsafe/fit-in/400x300/filters:quality(85)/
```

**Imagor Service** (DigitalOcean App Platform):
Imagor runs as a separate service at `https://beachrex-imagor-xadoz.ondigitalocean.app` with subdomain `s3.beachrex.com` pointing to it.

Configuration (on DigitalOcean App Platform):
- `PORT=8080`
- `IMAGOR_UNSAFE=1` (unsigned URLs enabled)
- `IMAGOR_AUTO_WEBP=1` (automatic WebP conversion)
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` (for DigitalOcean Spaces)
- `S3_ENDPOINT=fra1.digitaloceanspaces.com`
- `S3_LOADER_BUCKET=beachrex`
- `S3_RESULT_STORAGE_BUCKET=beachrex`

---

### 6. Swagger Documentation Updates

**Updated Schemas:**
- `app/Swagger/Schemas/BeachSchema.php`
  - ✅ Updated `main_image` description to explain frontend constructs Imagor URLs
  - ❌ Removed `main_image_imagor` property
- `app/Swagger/Schemas/BeachDetailsSchema.php`
  - ❌ Removed `imagor` object from MediaItem schema

---

### 7. Performance Impact

**Before (with Imagor URLs in API):**
- List endpoint with 100 beaches: ~54KB (with `main_image_imagor`)
- Memory exhaustion errors when caching 5000+ beaches with descriptions

**After (Imagor removed, description removed):**
- List endpoint with 100 beaches: ~45KB (smaller, no `main_image_imagor`, no `description`)
- ✅ No more memory exhaustion errors
- ✅ Caching works reliably with 5000+ beaches

---

### 8. Frontend Integration Guide

**Step 1: Set Environment Variables**
```env
# Imagor endpoint (subdomain pointing to Imagor service)
NEXT_PUBLIC_IMAGOR_ENDPOINT=https://s3.beachrex.com

# Optional: Default transformation for list views
NEXT_PUBLIC_IMAGOR_TRANSFORM=unsafe/fit-in/400x300/filters:quality(85)/
```

**Step 2: Construct Imagor URLs in Frontend**
```typescript
// For list views (beaches, spatial results)
const imagorEndpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT;
const imagorTransform = process.env.NEXT_PUBLIC_IMAGOR_TRANSFORM;
const imageUrl = `${imagorEndpoint}/${imagorTransform}${beach.main_image}`;
// Result: https://s3.beachrex.com/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl7.jpg

// For detail views (beach details) - construct different sizes
const thumbnailUrl = `${imagorEndpoint}/unsafe/fit-in/150x150/smart/filters:quality(85)/${media.url}`;
const smallUrl = `${imagorEndpoint}/unsafe/fit-in/400x300/filters:quality(85)/${media.url}`;
const mediumUrl = `${imagorEndpoint}/unsafe/fit-in/800x600/filters:quality(90)/${media.url}`;
const largeUrl = `${imagorEndpoint}/unsafe/fit-in/1200x900/filters:quality(90)/${media.url}`;
const heroUrl = `${imagorEndpoint}/unsafe/1920x600/smart/filters:quality(90)/${media.url}`;
```

**Step 3: Create Reusable Helper Function**
```typescript
// utils/imagor.ts
export const getImagorUrl = (
  imagePath: string,
  size: 'thumbnail' | 'small' | 'medium' | 'large' | 'hero' = 'small'
): string => {
  const endpoint = process.env.NEXT_PUBLIC_IMAGOR_ENDPOINT;

  const transformations = {
    thumbnail: 'unsafe/fit-in/150x150/smart/filters:quality(85)',
    small: 'unsafe/fit-in/400x300/filters:quality(85)',
    medium: 'unsafe/fit-in/800x600/filters:quality(90)',
    large: 'unsafe/fit-in/1200x900/filters:quality(90)',
    hero: 'unsafe/1920x600/smart/filters:quality(90)',
  };

  return `${endpoint}/${transformations[size]}/${imagePath}`;
};

// Usage
const imageUrl = getImagorUrl(beach.main_image, 'small');
```

---

### 9. Testing

**Test Direct Imagor URLs:**
```bash
# Direct Imagor URL (via subdomain)
curl "https://s3.beachrex.com/unsafe/fit-in/400x300/filters:quality(85)/img/beaches/hr-69-o-pag/hr-69-o-pag-sl7.jpg"
```

**Test API Responses:**
```bash
# List endpoint - verify main_image is present (no main_image_imagor)
curl "https://dev-api.beachrex.com/api/v1/beaches?limit=10" | jq '.data[0] | {id, name, main_image}'

# Detail endpoint - verify media has url (no imagor object)
curl "https://dev-api.beachrex.com/api/v1/beach-details/beach-livade-i-obatnice" | jq '.data.media.gallery[0] | {id, file_name, url}'

# Spatial endpoint - verify main_image is present
curl "https://dev-api.beachrex.com/api/v1/spatial/beaches/within-radius?latitude=44.5&longitude=14.5&radius=50&limit=3" | jq '.data[0] | {id, name, main_image}'
```

---


## 2025-11-26 – Spatial API Cache Key Fixes and Error Handling

### 1. Fixed Missing Filter Parameters in Cache Key Generation

**Context**
- The spatial endpoints (`/beaches/within-radius` and `/beaches/bounding-box`) support filtering by `natural_location_condition` and `sporting_activity`.
- These filters were implemented in the query logic but were **missing** from the cache key generation in `BeachFilterService::generateCacheKeySuffix()`.
- This caused incorrect cached results to be returned when these filters were used.

**Implementation**
- **File:** `app/Services/BeachFilterService.php`
  - Added `natural_location_condition` and `sporting_activity` to the `$filterParams` array in `generateCacheKeySuffix()` method (lines 1587-1588).
  - Cache keys now properly include these filters when present in the request.

**Impact**
- Cache keys are now unique for each combination of filters, preventing incorrect cached results.
- Example cache key before: `beaches:within_radius:en:lat=46.0569:lng=14.5058:radius=50.00:country=HR`
- Example cache key after: `beaches:within_radius:en:lat=46.0569:lng=14.5058:radius=50.00:country=HR:natural_location_condition=shaded:sporting_activity=diving`

**Action Required**
- Clear Redis cache after deployment to ensure all cached data uses the new cache key structure.
- Command: `php artisan cache:clear` or `redis-cli FLUSHDB`

---

### 3. Fixed Image Paths with Leading Slashes

**Context**
- Some paths in the database and code were stored/returned with a leading slash (e.g., `/icons/filters/waterbody.svg`, `/img/beaches/...`).
- This is inconsistent with the relative path convention used throughout the application.
- All paths should be relative without leading slashes (e.g., `icons/filters/waterbody.svg`, `img/beaches/...`).

---

### 4. Fixed Escaped Quotes in Beach Translations

**Context**
- Beach translation data (names, descriptions, slugs) contained literal backslash characters escaping apostrophes.
- Example: Database had `Beach d\'en Perris` instead of `Beach d'en Perris`.
- This caused the API to return double-escaped strings like `Beach d\\'en Perris`.
- The issue affected 366 records across name and description fields.

**Root Cause**
- Data was incorrectly inserted with SQL-escaped strings stored as literal values.
- The backslash-apostrophe sequence (`\'`) was stored in the database instead of just the apostrophe (`'`).

**Database Tables Affected**
- `waterbody.icon` - 4 records fixed
- `beach_type.icon` - 8 records fixed
- `user_type.icon` - 3 records fixed

**Code Files Affected**
- `app/Http/Resources/BeachResource.php` - Removed hardcoded leading slash from `main_image` path (line 64)
- `app/Services/PostGISService.php` - Removed hardcoded leading slash from spatial API responses (line 480)

**Fixes Applied**

1. **Database icon paths** - Created migration to remove leading slashes:
   - **Migration file:** `database/migrations/2025_11_26_000001_remove_leading_slashes_from_icon_paths.php`
   - Updates `waterbody.icon`, `beach_type.icon`, and `user_type.icon` tables
   - Removes leading `/` from all icon paths
   - Includes rollback functionality in `down()` method

2. **BeachResource main_image** - Changed from:
   ```php
   $mainImage = '/' . ltrim($primaryMedia->file_path, '/');
   ```
   To:
   ```php
   $mainImage = $primaryMedia->file_path;
   ```

3. **PostGISService formatBeachResults** - Changed from:
   ```php
   // Ensure main_image starts with a forward slash
   $mainImage = null;
   if ($beach->main_image_path) {
       $mainImage = '/' . ltrim($beach->main_image_path, '/');
   }
   ```
   To:
   ```php
   // Return main_image path as-is (relative path without leading slash)
   $mainImage = $beach->main_image_path ?? null;
   ```
   This affects all spatial API endpoints:
   - `/api/v1/spatial/beaches/within-radius`
   - `/api/v1/spatial/beaches/bounding-box`
   - `/api/v1/spatial/beaches/nearest`

**Database Tables Affected**
- `beach_translation.name` - 105 records fixed
- `beach_translation.description` - 261 records fixed
- `beach_translation.slug` - 0 records (none had backslashes)

**Fix Applied**

Created migration: `database/migrations/2025_11_26_000002_fix_escaped_quotes_in_beach_translations.php`

The migration uses PostgreSQL's `CHR()` function and `REPLACE()` to fix the data:
```sql
UPDATE beach_translation
SET name = REPLACE(name, CHR(92) || CHR(39), CHR(39))
WHERE POSITION(CHR(92) IN name) > 0
```

This replaces `\'` (backslash + apostrophe) with just `'` (apostrophe).

**Deployment Instructions**

To apply all data quality fixes to development and production databases:

1. **Development:**
   ```bash
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail artisan cache:clear
   ```

2. **Production:**
   ```bash
   php artisan migrate
   php artisan cache:clear
   ```

**Migrations to Run (in order):**
1. `2025_11_26_000001_remove_leading_slashes_from_icon_paths.php` - Fixes icon paths
2. `2025_11_26_000002_fix_escaped_quotes_in_beach_translations.php` - Fixes escaped quotes
3. `2025_11_26_000003_remove_double_spaces_from_beach_translations.php` - Removes double spaces

**Impact**

After running both migrations:
- All icon paths will be relative without leading slashes
- All beach translation names and descriptions will have proper apostrophes instead of escaped backslashes
- API responses will return clean, properly formatted text

---

### 5. Removed Double Spaces from Beach Translations

**Context**
- Some beach translation names and descriptions contained multiple consecutive spaces.
- Example: `Beach  Sveti Duh` (with double space) instead of `Beach Sveti Duh`.
- This affected 13 names and 58 descriptions.

**Database Tables Affected**
- `beach_translation.name` - 13 records fixed
- `beach_translation.description` - 58 records fixed
- `beach_translation.slug` - 0 records (none had double spaces)

**Fix Applied**

Created migration: `database/migrations/2025_11_26_000003_remove_double_spaces_from_beach_translations.php`

The migration uses PostgreSQL's `REGEXP_REPLACE()` to replace multiple consecutive spaces with a single space:
```sql
UPDATE beach_translation
SET name = REGEXP_REPLACE(name, ' {2,}', ' ', 'g')
WHERE name ~ ' {2,}'
```

This replaces any sequence of 2 or more spaces with a single space.

**Examples**

Before → After:
- `Beach  Sveti Duh` → `Beach Sveti Duh`
- `Beach  Aiguablava` → `Beach Aiguablava`
- `Beach Sa  Rocca Tunda` → `Beach Sa Rocca Tunda`
- `Platja  Albaranes` → `Platja Albaranes`

The migration will automatically fix all icon paths with leading slashes. If you need to rollback:
```bash
php artisan migrate:rollback --step=1
```

**Verification**
- Comprehensive scan of all database tables confirmed no paths starting with `/` remain.
- Checked 117 table/column combinations across the entire database.
- Verified all API resources return paths without leading slashes.

**Example**
- Before: `/icons/filters/waterbody.svg`, `/img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`
- After: `icons/filters/waterbody.svg`, `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`

**Impact**
- All paths (media, icons) are now consistent with the relative path convention.
- Frontend can prepend base URL/CDN consistently across all paths.
- API responses for beaches, filters, and badges now return clean relative paths.

---

### 2. Added Defensive Checks for Centroid Properties

**Context**
- The spatial endpoints return centroid data (center coordinates and bounding box) calculated from the beaches found.
- When accessing cached data, if the centroid object structure changed or properties were missing, the API would throw "Undefined property: stdClass::$property" errors.
- This was particularly noticeable when accessing endpoints via browser vs curl, as browsers might retrieve stale cached data.

**Implementation**
- **File:** `app/Http/Controllers/Api/V1/PostGISController.php`
  - **Method:** `beachesInBoundingBox()` (lines 663-670)
    - Added `isset()` checks for all centroid properties: `min_lat`, `max_lat`, `min_lng`, `max_lng`, `center_lat`, `center_lng`
    - Falls back to request parameters or null if properties don't exist
  - **Method:** `beachesWithinRadius()` (lines 281-282)
    - Added `isset()` checks for `center_lat` and `center_lng`
    - Falls back to null if properties don't exist

**Example**
- Before: `$data['centroid']->min_lat` → Error if property doesn't exist
- After: `$data['centroid'] && isset($data['centroid']->min_lat) && $data['centroid']->min_lat !== null ? (float) $data['centroid']->min_lat : (float) $request->min_lat`

**Impact**
- API endpoints are now resilient to cached data with different structures
- No more "Undefined property" errors when accessing spatial endpoints
- Graceful fallback to request parameters when centroid data is unavailable

---

## 2025-11-26 – Media URLs, 360 Spheres, and Swagger Updates

### 1. Media URLs: CDN prefix removed

**Context**
- Previously, media URLs (images, videos, etc.) were returned with a full CDN/Spaces URL, e.g. `https://beachrex.fra1.digitaloceanspaces.com/public/...`.
- The requirement is now to return **relative paths only** and let the frontend prepend its own base URL/CDN.

**Implementation**
- **Model:** `app/Models/Media.php`
  - Accessors were updated so that `url` and `thumbnail_url` now return the stored `file_path` directly, *without* any domain/CDN prefix.
  - This affects all API responses that serialize `Media` models.

**Implications for frontend**
- All media-related fields (`url`, `thumbnail_url`, `main_image`, etc.) are now **relative paths** like:
  - `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`
- Frontend must prepend the appropriate base URL/CDN when building absolute URLs.

---

### 2. 360 Spheres (`spheres_360`) and Virtual Tour URL

**Context**
- In DB, `spheres_360` items store only the panorama image, e.g.:
  - `img/beaches/hr-6-o-hvar/hr-6-o-hvar-Panorama.jpg`
- The actual 360 experience is served from a virtual tour HTML file at:
  - `img/beaches/{folder_name}/vtour/tour.html`
- This `vtour/tour.html` path is **not stored in DB** and may not exist for all beaches.

**Implementation**
- **Resource:** `app/Http/Resources/BeachDetailsResource.php`
  - Method `getMediaCollections()` now passes the collection code for spheres:
    - `spheres_360` → `beach_360_sphere` (second argument to `formatMediaCollection`).
  - Method `formatMediaCollection($mediaItems, ?string $collectionCode = null)`:
    - If `collectionCode === 'beach_360_sphere'` **and** the beach has `folder_name` **and** the collection is not empty, it computes:
      - `vtourUrl = "img/beaches/{$this->folder_name}/vtour/tour.html"`
    - For each media item in `spheres_360`, the response now includes:
      - `tour_url = vtourUrl`
    - For all other collections, `tour_url` is explicitly `null`.

**Important behavior**
- We **do not** check file existence on Spaces for performance reasons.
- `tour_url` is derived purely from `folder_name` and collection type.
- If DB has no `spheres_360` entries, `tour_url` is not set (no spheres block or an empty array).

**Example (Beach Adam)**
- Before:
  - `spheres_360[].url` → `img/beaches/hr-6-o-hvar/hr-6-o-hvar-Panorama.jpg`
- After:
  - `spheres_360[].url` → same relative image path
  - `spheres_360[].tour_url` → `img/beaches/hr-6-o-hvar/vtour/tour.html`

**Frontend expectation**
- Use `spheres_360[*].tour_url` to open the 360 viewer.
- Use `url` / `thumbnail_url` as preview images.
- If `tour_url` 404s, handle it gracefully on the frontend (backend does not verify existence).

---

### 3. Swagger / OpenAPI Documentation Updates

The Swagger docs were updated to reflect the new behavior for media URLs and 360 spheres.

#### 3.1 Beach details – `MediaItem` and `spheres_360`

**File:** `app/Swagger/Schemas/BeachDetailsSchema.php`

- `spheres_360` description updated:
  - From: "360-degree panoramic images"
  - To:   "360-degree panoramic images and their virtual tours".

- `MediaItem` schema now documents:
  - `url` as a **relative** media path:
    - Example: `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`
    - Description: "Relative media path without CDN/domain prefix. Frontend should prepend its own base URL or CDN."
  - `thumbnail_url` as a **relative** thumbnail path:
    - Example: `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg`
    - Description: "Relative thumbnail path (same as url for now)."
  - New field `tour_url`:
    - Type: `string`, `nullable: true`
    - Example: `img/beaches/hr-6-o-hvar/vtour/tour.html`
    - Description: "Virtual tour HTML path for 360 spheres (present only for spheres_360 collection)."

- Navigation `main_image` fields in `BeachDetailsSchema` are now described as:
  - Example: `img/beaches/beach-folder/image.jpg`
  - Description: "Main beach image path (relative, without CDN/domain prefix)."

#### 3.2 Generic media schema

**File:** `app/Swagger/Schemas/MediaSchema.php`

- Updated examples and descriptions for media URLs:
  - `file_name`: example `hr-6-o-hvar-sl3.jpg`.
  - `url`: example `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg` with description that it is a **relative** path.
  - `thumbnail_url`: example `img/beaches/hr-6-o-hvar/hr-6-o-hvar-sl3.jpg` with description that it is a **relative** thumbnail path.

#### 3.3 Beach list schema

**File:** `app/Swagger/Schemas/BeachSchema.php`

- `main_image` property updated:
  - Example: `img/beaches/hr-779-k-jdalmacija/hr-779-k-jdalmacija-sl4.jpg`
  - Description: "Primary beach image path (relative, without CDN/domain prefix). Frontend should prepend its own base URL or CDN."

#### 3.4 First page content media schema

**File:** `app/Swagger/Schemas/FirstPageContentMediaSchema.php`

- `url` property updated from full CDN URL to relative path:
  - Example: `img/first-page/hero-bg.jpg`
  - Description: "Relative media path without CDN/domain prefix. Frontend should prepend its own base URL or CDN."

#### 3.5 Cleanup of legacy CDN references

- Searched `app/` for occurrences of:
  - `beachrex.fra1`
  - `cdn.example.com`
  - `CDN URL`
  - `prepend CDN`
- Updated or removed Swagger comments so they no longer state that the backend returns full CDN URLs. All documented examples now match the new **relative-path** behavior.

---

## Notes for Future Updates

- When adding new endpoints that return media, follow the same pattern:
  - Backend returns **relative paths only**.
  - Frontend is responsible for prepending base URL/CDN.
- For any new 360-related features, prefer deriving `tour_url` from the beach folder name where possible, to avoid extra DB columns and network/file existence checks.
- When modifying API responses, remember to update the corresponding Swagger schema in `app/Swagger/Schemas/*` so docs stay in sync with actual behavior.


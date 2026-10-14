# TASK-006 — Windy webcams: one-time import, local matching, widget rendering

Status: **Built and live on dev (2026-10-14): backend #114–#118, web-client #75.** Real import, first-country results and screenshots: owner steps (HANDOFF). Proof: [`TASK-006.feedback.md`](TASK-006.feedback.md).
Spec: owner's task file (chat, 2026-10-14). API facts: [`docs/technical/windy-webcams-api.md`](../technical/windy-webcams-api.md).
Legacy report: [`TASK-006-legacy-webcams-2026-10-14.csv`](TASK-006-legacy-webcams-2026-10-14.csv).

Working mode: **mode B** (owner, 2026-10-14, answering "Način dela za to nalogo?": "Mode B"), as in HANDOFF.

## 0. Owner decisions (2026-10-14)

| Question | Answer |
|---|---|
| Max matching radius | **3 km** (owner chose 3 km over the proposed 5 km) |
| Working mode | **Mode B** |
| Rendering | **Direct iframe** (`/webcams/public/embed/player?...`), no `player.js` |
| Other providers (2 WhatsUpCams, 2 Webcamgalore) | **Hidden** — only verified Windy webcams are shown |
| Thresholds 70 / 40 / +15, country list | accepted as proposed (not changed) |
| `WINDY_API_KEY`, full Windy terms | still open (owner) |

## 1. Phase 0 findings

### 1.1 Where things live

| What | Where |
|---|---|
| Places | one table `beach` (beaches; pools/baths/spas will be rows of the same table) |
| Coordinates | `beach.latitude`, `beach.longitude` (decimal) + PostGIS `beach.location geometry(Point,4326)` kept in sync by trigger `update_beach_location`, GIST index `beach_location_gist_idx`, plus a `geography` index (Beachrex/backend#21) |
| Place type | no explicit type column. `beach.waterbody_id` → `waterbody` (river / pool / sea / lake); pool details in `indoor_pools_count`, `has_thermal_pool`, `has_wellness`, … All 3 774 rows are waterbody `sea` today (QA #69) |
| Country | `beach.country_code` (ISO-2) |
| Legacy webcam | `beach.webcam text` ("Webcam embed code"), filled by `beaches:migrate-from-old-db` from the old MariaDB `beach.webcam` |
| API | `BeachResource` / `BeachDetailsResource` expose `webcam` (raw string) under `embeds`/`media.embeds`; `PostGISService` selects `b.webcam` for map/list |
| Web | `web-client/src/lib/utils/webcam.ts` `parseWebcamUrl()` takes the first `href`/`src` and **opens it in a new tab**; the HTML is never embedded. Used by the beach page, cards and the map |
| Mobile app | reads `/api/v1` → the `webcam` field must stay (additive-only rule) |

### 1.2 Counts (source: `database/sql/beachrex_old.sql`, the dump the dev DB was loaded from; 3 774 rows = dev count)

| `beach.webcam` value | Rows |
|---|---|
| empty / NULL | 1 586 |
| placeholder `"brez"` (+ `"bre"`, `"bret"`) | 1 934 |
| Lookr timelapse `<a name="lkr-timelapse-player" data-id=…>` + `api.lookr.com/…/timelapse.js` or `player.js` | 184 |
| Windy timelapse `<a name="windy-webcam-timelapse-player" data-id=…>` + `webcams.windy.com/…/player.js` | 66 |
| WhatsUpCams bare URL (SI: Piran, Portorož) | 2 |
| Webcamgalore link (IT) | 2 |
| **Rows with a Windy-compatible ID** | **250** (237 unique IDs; 13 IDs on 2 beaches each) |

Per country (all rows / with Windy-compatible ID): HR 1216/60, FR 659/21, ES 633/31, IT 357/72, GR 301/0, DE 113/30,
DK 110/0, PT 103/23, NL 101/11, ME 54/2, AL 53/0, BE 50/0, SI 23/0, BY 1/0. 154 rows have no coordinates.
No iframe and no `windy.com/webcams/{id}` link formats exist in the data. Neum (1616668973) is **not** in our DB (no BA beaches).

NOT RUN — the same counts on the live dev DB (the sandbox cannot reach it). Owner check:
`SELECT CASE WHEN webcam IS NULL OR btrim(webcam)='' THEN 'empty' WHEN webcam ILIKE 'bre%' THEN 'brez' WHEN webcam LIKE '%lkr-timelapse%' THEN 'lookr' WHEN webcam LIKE '%windy-webcam%' THEN 'windy' ELSE 'other' END k, count(*) FROM beach GROUP BY 1;`

### 1.3 Why the legacy webcams fail (all 237 IDs checked, 2026-10-14)

Check: `https://webcams.windy.com/webcams/public/embed/player?webcamId={id}&playerType=day` (public player, no API key).

| Result | Unique IDs | Beach rows |
|---|---|---|
| alive (player renders, title returned) | 73 | 76 |
| "embed player … **not publicly available**" (camera exists, embedding disabled) | 69 | 75 |
| "embed player … **currently not available**" (dead / inactive) | 95 | 99 |

Root causes:
1. **Dead IDs** — 95 of 237.
2. **Outdated script** — the 184 Lookr rows load `api.lookr.com/...` scripts (Lookr was Windy's predecessor). The **IDs are the
   same Windy IDs** and today's Windy `player.js` still accepts `name="lkr-timelapse-player"`; only the script URL is stale.
3. **Not embeddable** — 69 cameras exist but the owner/provider disabled the public embed. The API `status` field alone
   cannot be trusted to mean "embeddable" (to be confirmed on the first real import).
4. **Our site never embedded anything** — `parseWebcamUrl()` opens the `href`, which for Lookr rows is a `lookr.com` link.
5. **Loose assignment** — several alive cams show a nearby place, not the beach (e.g. beach "Ponta", Viganj → "Camping Antony Boy";
   "Sunčana uvala", Mali Lošinj → "beach Veli Žal"; "Strand Thiessow" → "ProBoarding Kitesurfschule Rügen"). The matcher score decides these.

5 manual samples: 1379748608 Dierhagen (alive, correct place) · 1552560649 Praia da Rainha (alive, correct) ·
1575671566 Laboe (alive, correct town) · 1249477491 Lučica (dead) · 1463401632 Marina di Ravenna (dead).

### 1.4 Windy API constraints that change the spec

- Free tier: `offset` ≤ 1 000, `limit` ≤ 50 → max **1 050 results per filter combination**; `countries` ≤ 10, `categories` ≤ 10,
  `webcamIds` ≤ 50 per call, `nearby` radius ≤ 250 km. Quotas/rate limits are **not published**.
- Professional (9 990 €/year) has `all-webcams.json` (everything in one call, but no `player`/title translations) and offset 10 000.
- Categories are a fixed list; **no `water`, `bay`, `spa`, `pool` category**. Use `beach` / `coast`, `lake`, `river`, `port`.
- `urls.detail` from the docs example returns 404; link to `https://www.windy.com/webcams/{id}`.
- The agent sandbox **cannot reach `api.windy.com`**; real imports must run on dev (artisan command), outputs sent back by the owner.
- Full terms of use (`account.windy.com/agreements/windy-webcams-api-terms-of-use`) not readable from the agent session.

## 2. Plan (for owner OK)

### 2.1 Data model (expand-only migrations, `beach.webcam` kept)

`windy_webcams` as in the spec, plus:
- `location geography(Point,4326)` + GIST index (matching uses `ST_DWithin`, same as the beach `geography` index);
- `embed_status` (`ok` / `not_public` / `unavailable` / `unknown`) + `embed_checked_at` — from the public player page, see 2.2.

`place_webcams` exactly as in the spec (`place_id` → `beach.id`), unique `(place_id, windy_id)`, partial unique index
"one primary per place" (`WHERE is_primary`), and a partial unique index on `windy_id WHERE review_status IN ('auto_approved','approved')`
for the one-webcam-one-place rule.

### 2.2 Commands (all `--dry-run`, idempotent, checkpointed in a `windy_import_runs` table, budget `WINDY_DAILY_REQUEST_BUDGET`, default 500)

| Command | API calls | What |
|---|---|---|
| `webcams:import --countries=HR,SI,…` | yes | per country, `categories=beach,coast,lake,river,port&categoryOperation=or`, `include=categories,location,player`; if `total > 1050`, re-slice by `regions` (from `/regions`), then by `bbox` tiles. `images` never requested. Upsert, `last_seen_at`, `missing_since` after a complete country. 429/5xx: exponential back-off, stop at budget, resume from checkpoint. |
| `webcams:embed-check` | **no** (public player page) | sets `embed_status`; ~1 req/s; only for webcams within 3 km of a place. A webcam must be `ok` to be auto-approved or rendered. |
| `webcams:legacy-extract` | only for IDs not in `windy_webcams` (`webcamIds`, 50 per call) | parser for both formats (Windy + Lookr `data-id`), plus Windy URLs/iframes for the admin paste box; same scoring/thresholds; legacy > 3 km never auto. CSV report. Never edits `beach.webcam`. |
| `webcams:match` | **no** | spec 6.3 with the category set adjusted (2.3). |
| `webcams:refresh` (monthly, scheduler) | yes | import → embed-check (changed only) → match affected places; alert e-mail if > 10 % lost. |

Request estimate (free tier): ~14 countries × 1–6 slices × ≤ 21 pages ≈ **150–600 requests** for the first full import,
legacy lookups ≤ 4 calls (≤ 164 missing IDs / 50). Croatia alone ≈ 5–30 requests. Monthly refresh the same order.
Alternative if slicing truncates anyway: one `nearby={lat},{lon},3` call per place (≈ 3 620 calls, spread over ~8 days at 500/day) — complete by construction; used only as a fallback per country.

### 2.3 Scoring config (`config/webcams.php`, all numbers here)

As the spec, with categories mapped to what Windy has:
- distance < 300 m / < 1 km / ≤ 3 km: +40 / +25 / +10; nothing beyond `MAX_RADIUS` = 3 km (owner)
- `beach`: +25 · `coast`, `lake`, `river`, `port`: +10
- name match (normalised, stopwords stripped, fuzzy ≥ 0.85): +30 · only town name: +5
- `city`, `traffic`, `mountain`, `airport`, `indoor`, `building`, `square`: −20 (for pools/spas `indoor` and `sportArea` become +10)
- excluded: inactive, `missing_since`, `embed_status ≠ ok`
- auto ≥ 70 and +15 over runner-up · pending 40–69 · none < 40

Profiles: `beach` (default, by `waterbody` sea/lake/river) and `pool_spa` (waterbody `pool` or pool/wellness flags), with their own stopwords
("kopališče", "terme", "therme", "spa", "aquapark", "bazen", "piscina", "piscine", "freibad", "hallenbad"…). Extensible map in config.

### 2.4 Admin review

Laravel admin page (existing `admin` middleware), list/approve/reject/primary/add-by-ID-or-paste, preview with the same iframe, link
`https://www.windy.com/webcams/{id}`. 401/403 tests. No API calls (optional single verify on manual add).

### 2.5 API + frontend

- API, additive: new `webcams: [{windy_id, title, is_primary}]` on beach details/list (approved + auto_approved only); `webcam` stays unchanged for old app builds.
- Web: `WindyWebcam` component. **Proposal:** render the iframe directly (`/webcams/public/embed/player?webcamId=…&playerType=day&loop=0&autoPlay=0&interactive=1`,
  `loading="lazy"`, 16:9) instead of injecting `player.js`. It is exactly what Windy's script produces, works with client-side navigation
  (the script only scans the DOM when it runs), and adds no third-party JS. Fallback: the script snippet loaded once on intersection.
  Windy link/credit line kept. Section hidden when there is no webcam. Feature flag `NEXT_PUBLIC_WEBCAMS_V2` (off = old link behaviour).
- `parseWebcamUrl()` and the "has webcam" filters switch to the new field when the flag is on.

### 2.6 Delivery (one task = several PRs, each ≤ ~800 lines)

1. Backend: migrations + models + config + extractor + scoring + tests (no API).
2. Backend: import / embed-check / legacy-extract / match / refresh commands + mocked-API tests.
3. Backend: admin review + API field + OpenAPI.
4. Web-client: component, flag, tests, 19 locales.
Then the owner runs dry run + one-country import on dev and sends the output (proof §10).

## 3. Owner decisions (answered 2026-10-14, see §0; still open: 3)

1. OK for the plan, or changes (radius 5 km, thresholds 70/40/+15, country list = the 14 countries we have beaches in).
2. Mode: stay in **mode A** for this task (agent prepares branches, owner commits) or **mode B** as in HANDOFF?
3. `WINDY_API_KEY` in `.env.dev` (free tier is enough for the plan above) — and read the full Windy terms (commercial use, storing metadata). Pro tier (9 990 €/y) only if free-tier slicing or player ads become a problem.
4. Render the iframe directly (recommended) vs the official script snippet.
5. The 2 WhatsUpCams (Piran, Portorož) and 2 Webcamgalore rows are other providers (out of scope): keep showing them as a link, or hide?

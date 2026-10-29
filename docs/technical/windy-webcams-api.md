# Windy Webcams API v3 — verified reference (TASK-006)

**Checked: 2026-10-14** by the web agent, against the official interactive documentation
(`https://api.windy.com/webcams/docs`, Swagger UI rendered in a browser), the pricing page
(`https://api.windy.com/webcams/pricing`), the short terms page (`https://api.windy.com/webcams/terms`)
and the live embed script `https://webcams.windy.com/webcams/public/embed/script/player.js`.
Status: **reference** (nothing below is used by Beachrex code yet).

Re-check this page before changing the importer; Windy changes limits per tier without versioning.

## Access

| Item | Value | Source |
|---|---|---|
| Base | `https://api.windy.com/webcams/api/v3/` | docs |
| Auth | header `x-windy-api-key: <key>` (query param not accepted) | docs; community thread 2023-09-27 |
| Keys | created at `https://api.windy.com/keys`; active after a few minutes | docs |
| Env var (Beachrex) | `WINDY_API_KEY` — owner sets it; never logged | TASK-006 |
| Sandbox | the agent sandbox **cannot reach** `api.windy.com` (egress allowlist); real calls run on dev via a command | checked 2026-10-14 |

## Endpoints

| Path | Purpose | Notes |
|---|---|---|
| `GET /webcams` | list with filters | main import endpoint |
| `GET /webcams/{webcamId}` | one webcam | `lang`, `include` |
| `GET /categories` | category ids | |
| `GET /continents`, `/countries`, `/regions`, `/cities` | geo codes for filters | region code e.g. `ES.51` |
| `GET /map/clusters` | map clustering by bbox + zoom (4–18) | not needed |
| `GET /webcams/export/all-webcams.json` | **all webcams in one file** (status, id, title, viewCount, categories, lat/lon, region/country/continent code, hasLivestream, hasPanorama) | **Professional tier only** |

## `GET /webcams` parameters (verbatim limits)

| Param | Type | Limit / values |
|---|---|---|
| `limit` | int | 0–50, default 10 |
| `offset` | int | min 0; **max 1 000 (free)**, 10 000 (professional) |
| `countries` | array | ISO codes, **max 10** |
| `regions` | array | region codes, max 10 |
| `continents` | array | `AF AN AS EU NA OC SA`, max 2 |
| `cities` | array | undocumented |
| `categories` | array | max 10; `airport beach building city coast forest indoor lake landscape meteo mountain observatory port river sportArea square traffic village` |
| `categoryOperation` | `and` \| `or` | default `and` |
| `bbox` | string | `north,east,south,west` |
| `nearby` | string | `lat,lon,radius_km`, **max radius 250 km** |
| `webcamIds` | array[int] | **max 50**; when set, all other filters are ignored |
| `sortKey` / `sortDirection` | `popularity`,`createdOn` / `asc`,`desc` | |
| `include` | array | `categories images location player urls` |
| `lang` | string | 43 codes incl. `sl`, `hr`, `de`, `it`… default `en` |

Array parameters are declared `explode: false` in the embedded OpenAPI spec (checked 2026-10-14), so they are sent
**comma-separated**: `categories=beach,coast`, `webcamIds=1,2,3`, `include=categories,location,player`.

Pagination consequence on the free tier: one filter combination can return at most offset 1 000 + limit 50
= **1 050 webcams**. Any country/category slice with `total > 1050` must be split (by `regions`, or `bbox`
tiles) or it is silently truncated. The importer must compare `total` with what it fetched.

There is **no** `water`, `bay`, `spa`, `pool` or `thermal` category. Pools/spas can only be matched by
title words and the `indoor` / `sportArea` categories.

## Response fields (webcam object)

`webcamId` (int), `title`, `status` (enum in the spec: `active`, `inactive`, `unapproved`, `disabled`, `rejected`, `duplicate`, `merged`), `viewCount`,
`lastUpdatedOn` (ISO), `categories[] {id, name}`, `location {latitude, longitude, city, city_code, region,
region_code, country, country_code, continent, continent_code}`, `player {live, day, month, year, lifetime}`
(public URLs, no token), `urls {detail, edit, provider}`, `images {current, daylight, sizes}` (**tokenised —
never stored**). List response: `{ total, webcams[] }`.

Note: the example `urls.detail` (`https://webcams.windy.com/webcams/{id}`) returned **"Cannot GET"** on
2026-10-14; the public link to use is `https://www.windy.com/webcams/{id}`.

## Tiers and terms

| | Free | Professional |
|---|---|---|
| Price | 0 | 9 990 € / year (pricing page, 2026-10-14) |
| Max offset | 1 000 | 10 000 |
| Bulk export `all-webcams.json` | no | yes |
| Image URL lifetime | docs page: 10 min; pricing page: 15 min (they disagree) | 24 h |
| Player | embeddable, size unlimited | ad-free player |
| Request quota / rate limit | **not published** | "unrestricted API access" |

Terms page (short form): use data only as delivered by the API; use images only with API-provided URLs;
link every image to Windy; "Mention us" credit line (example: "Webcams provided by Windy.com"); do not put
the API under inappropriate load; Windy may terminate access at any time. The full agreement
(`account.windy.com/agreements/windy-webcams-api-terms-of-use`) could **not** be opened from the agent
session — **owner must read it** for commercial use and storing metadata in our DB.

## Embed (widget) — current official form

The live `player.js` (fetched 2026-10-14) looks for elements named `lkr-timelapse-player`,
`windy-webcam-timelapse-player` or `windy-webcam-player-embed`, reads `data-id`, `data-play`, `data-loop`,
`data-auto-play`, `data-interactive`, and replaces each with an iframe:

```html
<!-- snippet (same markup Windy's share/embed produces; legacy rows use exactly this) -->
<a name="windy-webcam-timelapse-player" data-id="1616668973" data-play="day"
   href="https://windy.com/webcams/1616668973" target="_blank">Neum</a>
<script async type="text/javascript"
        src="https://webcams.windy.com/webcams/public/embed/script/player.js"></script>

<!-- iframe the script generates -->
<iframe name="windy-webcam-timelapse-player-iframe" allow="fullscreen" allowfullscreen frameborder="0"
        width="100%" src="https://webcams.windy.com/webcams/public/embed/player?webcamId=1616668973&playerType=day&loop=0&autoPlay=0&interactive=1&referrer=…&sr=…"></iframe>
```

`playerType`: `live` | `day` | `month` | `year` | `lifetime` (from the `player` object). Height = width / (16/9).

Important for a Next.js page: the script scans the DOM **once when it runs** (plus `DOMContentLoaded`); an
element added later by client-side navigation is not picked up unless the script is executed again.

A dead or disabled camera returns HTTP 200 with the body
"Sorry. The embed player for this webcam is currently not available." — useful for a
no-API liveness check of legacy IDs.

The UI "Share / Embed" dialog on windy.com could not be opened from the agent browser
(`www.windy.com/webcams/1616668973` redirected to the map). The snippet above is taken from the live script
and the legacy rows; the owner may confirm it once in the windy.com UI.

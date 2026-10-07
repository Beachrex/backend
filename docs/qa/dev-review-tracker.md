# Dev review tracker (tester's spreadsheet, "BUGI" sheet)

Source: the owner's spreadsheet `dev.beachrex_pregled_napak_delovanja.xlsx` (77 entries, 2026-04-13 → 2026-10-07).
The spreadsheet itself is **not** in the repo: it holds e-mail attachment links and personal data. The owner keeps it.
`#` below = the spreadsheet's "Zap. št.", so rows can be matched 1:1.

## How we work with it
1. **Assess before fixing.** Every row gets a verdict here first:
   - some rows are already fixed;
   - some are missing translations (data, not code);
   - some are feature requests or questions that need the owner's decision;
   - some are real bugs.

   A verdict marked *first pass* was made from the description only (screenshots are not available to the agent). It is verified in the code or on dev before any change.
2. **One fix = one PR to `dev`**, referencing the row: `QA #55` in the PR title or body.
3. **After the dev deploy**, the agent fills in `Fixed in` (PR + deploy run) and sets `Status` to **Retest**.
4. **The owner** copies Retest into the spreadsheet ("Stanje" = "odpravljena napaka – prosim retest") so the tester checks again. If it passes, the status becomes **Closed**. If it fails, it goes back to **Bug** with the tester's note.

Statuses:
- **Bug**: confirmed or very likely a code bug, to fix.
- **Retest**: fixed on dev, waiting for the tester.
- **Likely fixed**: probably fixed by an earlier change; retest first.
- **Translation/data**: missing translations or data, not code.
- **Decision**: a feature request, or the expected behaviour must be defined by the owner.
- **Closed**: the tester confirmed, or the spreadsheet already says "odpravljena napaka".
- **Not a bug**.

Priority: **P1** = page crash or broken navigation · **P2** = wrong data or broken feature · **P3** = cosmetic or translation.

## Summary (first pass, 2026-10-07)

| Group | Rows | Verdict |
|---|---|---|
| Already "odpravljena napaka" in the spreadsheet | 2, 3, 4, 5, 10, 11, 12, 13, 14, 15, 16, 22, 23 | Closed (no action) |
| Fixed in session 2026-10-07 | 18 (sea temperature) · 6, 7 (AI chat) | Retest |
| Probably fixed by session 2026-10-07 | 17, 38, 61 (360° tours) · 30 (languages) · 8, 9 (AI login) | Likely fixed, retest |
| **Crashes / empty pages (P1)** | 19, 52, 53, 54, 55, 56, 60, 77 | Bug, first |
| Language switch jumps to another locale (P1–P2) | 24, 25, 74 | Bug |
| Map and filter bugs (P2) | 39, 40, 43, 44, 45, 46, 69, 70, 72 | Bug, to verify |
| Media (video, webcam, image crop, media switch) (P2) | 36, 37, 48, 63, 64, 65, 66, 67, 68 | Bug or data, to verify |
| Search ranking and inconsistencies | 41, 50, 51 | Bug (51) / decision (50) / data (41) |
| Missing translations | 1, 26, 27, 28, 29, 35, 75, 76 | Translation/data (frontend `messages/*.json` + DB translations) |
| Owner must define behaviour or content | 20, 21, 31, 32, 34, 42, 47, 49, 57, 58, 59, 62, 71, 73 | Decision |
| Small data mismatch | 33 (2876 vs 2877 beaches) | Bug, to verify |

## Rows

| # | Summary (short, from the spreadsheet) | Type | Verdict (first pass unless noted) | Prio | Status | Fixed in |
|---|---|---|---|---|---|---|
| 1 | Language switch: not all beach text changes; UA/RU partly untranslated, also filter sub-levels | bug | Missing translations (UI strings and DB translations for UA/RU). Audit `messages/ua.json`, `ru.json` and the DB `*_translation` rows per locale | P3 | Translation/data | |
| 2 | Crash on language change (Digest 940020187) | bug | Spreadsheet: fixed | – | Closed | |
| 3 | Main menu buttons inactive | bug | Spreadsheet: fixed | – | Closed | |
| 4 | Logo/home link inactive | bug | Spreadsheet: fixed | – | Closed | |
| 5 | Search does not find countries (Germany, Greece, Albania) | bug | Spreadsheet: fixed | – | Closed | |
| 6 | AI: "best sandy beaches in Slovenia" shows no beaches | bug | Root cause confirmed: the chat had no beaches without GPS, English keywords only. Fixed by TASK-003; the owner confirmed the chat on dev (2026-10-07) | P2 | Retest | Beachrex/backend#26, dev run 127 |
| 7 | AI: same conversation goes in circles | bug | Same as #6 | P2 | Retest | Beachrex/backend#26, dev run 127 |
| 8 | AI asks to sign in but sign-in is not possible | bug | Plan enforcement (402) for paid features. Check what the AI page shows for 402 and whether the login link works | P2 | Likely fixed, retest | |
| 9 | After sign-in, redirected home and AI no longer reachable | bug | Frontend: login should return to `/ai`. Verify | P2 | Bug, to verify | |
| 10 | DE language crashes the site | bug | Spreadsheet: fixed | – | Closed | |
| 11 | All languages crash | bug | Spreadsheet: fixed | – | Closed | |
| 12 | Back buttons inactive | bug | Spreadsheet: fixed | – | Closed | |
| 13 | City level: beach link not clickable (Porto Palermo) | bug | Spreadsheet: fixed | – | Closed | |
| 14 | Next-city click broken | bug | Spreadsheet: fixed | – | Closed | |
| 15 | Cursor flickers between hand and arrow on buttons | bug | Spreadsheet: fixed | – | Closed | |
| 16 | Beach gallery images low quality and cropped | bug | Spreadsheet: fixed (but see #37) | – | Closed | |
| 17 | No 360° spheres or videos on some beaches (Albania, Montenegro) | bug | Two known causes fixed: the `mobile_upload` tour path in search/map (Beachrex/backend#23) and non-ASCII image paths (TASK-002, migration pending). Retest after the TASK-002 migration | P2 | Likely fixed, retest | Beachrex/backend#23 (+ TASK-002) |
| 18 | Sea temperature = air temperature, always cloudy | bug | Confirmed: `sea_temperature` was the air temperature. Now Open-Meteo Marine; the owner confirmed the widget (2026-10-07). "Always cloudy": verify separately | P2 | Retest | Beachrex/backend#25, dev run 125 |
| 19 | Home: click EUROPE → 404 / client-side exception | bug | Same family as #55/#77 (continent page crash) | P1 | Bug | |
| 20 | Home filters: "clear all" button; keep filters on Back | feature | Reasonable UX request | P3 | Decision | |
| 21 | Map: show the selected filters (like on home) | feature | Reasonable UX request | P3 | Decision | |
| 22 | Map: place names lowercase or with hyphens | bug | Spreadsheet: fixed | – | Closed | |
| 23 | DE map link → 404 (`strandkarte`) | bug | Spreadsheet: fixed | – | Closed | |
| 24 | Map button sometimes jumps to another language (PL → RU) | bug | Locale routing: the localized map slug resolves to the wrong locale. Frontend middleware / URL builders | P1 | Bug | |
| 25 | Bottom button on a localized map goes to another locale or back to EN | bug | Same family as #24 | P1 | Bug | |
| 26 | UA/RU map page partly untranslated | bug | Missing translations | P3 | Translation/data | |
| 27 | PL footer not translated | bug | Missing translations | P3 | Translation/data | |
| 28 | SL footer problem | bug | Needs the screenshot. Likely a translation or layout issue | P3 | Translation/data | |
| 29 | Footer section untranslated in DE, HR, FR, ES, IT, UA, RU, PL | bug | Missing translations | P3 | Translation/data | |
| 30 | Need languages DA, PT, NL, EL, SQ | feature | These 5 locales exist since `NEW_LANGUAGES_CHANGES.md` (19 locales). Retest | – | Likely fixed, retest | |
| 31 | "Views" links redirect to home | bug | Need to know which element; define the target | P2 | Decision | |
| 32 | Items link nowhere; content needed | question | Content decision | – | Decision | |
| 33 | Home shows 2876 beaches, map 2877 | bug | Two different counts (cached vs live, or a status filter). Verify | P3 | Bug, to verify | |
| 34 | Element links nowhere: to define | question | Content decision | – | Decision | |
| 35 | Map element always in EN | bug | Missing translation (UI string or DB) | P3 | Translation/data | |
| 36 | Map says webcam exists, but click shows nothing and the beach page has none | bug | Data (webcam URL) or frontend. Verify against `media.embeds.webcam` | P2 | Bug, to verify | |
| 37 | Map card images cropped (logo cut) | bug | CSS `object-fit` / Imagor `fit-in`. Verify | P3 | Bug, to verify | |
| 38 | Many beaches show no sphere (map or beach page) | bug | See #17 | P2 | Likely fixed, retest | Beachrex/backend#23 (+ TASK-002) |
| 39 | Map search does not leave the selected zone (Istria → Zadar) | bug | Behaviour decision: searching a place should change the zone. Probably a small frontend change once agreed | P2 | Decision | |
| 40 | Map search results hidden behind the map | bug | z-index / CSS | P2 | Bug | |
| 41 | Searching "Zadar" shows "Zara, città di Zara" | bug | The Meilisearch city name uses a non-local translation (IT name in the EN index?). Check the search locale fallback | P3 | Bug, to verify | |
| 42 | Hover on map elements: show region/city, link | question | Define the intended behaviour | P3 | Decision | |
| 43 | City and beach level: side map shows the region, not the city | bug | Frontend map bounds use the region. Verify | P2 | Bug | |
| 44 | Map filter: Croatia shows no results; URL words flicker | bug | Probably the `country`/`country_code` mismatch (fixed in Beachrex/backend#23: `/beaches/filters` accepts `country_code`) **or** a frontend loop. Retest first, then fix the flicker | P1 | Likely fixed, retest | Beachrex/backend#23 (partly) |
| 45 | Same beach-type, family and parking icon on every beach | bug | Frontend shows defaults or wrong fields; the DB has `beach_type_id`, `user_type`, infrastructure. Verify | P2 | Bug | |
| 46 | Beach page: wrong beach type (sand shown as rock), duplicates, no sphere | bug | Same as #45 (+ #17) | P2 | Bug | |
| 47 | Parking and pool are "YES only": was it like that on the old site? | question | The owner checks the old site; the agent can check `MigrateBeachesFromOldDb` mapping | – | Decision | |
| 48 | `/si/plaze`: image error, media switch (image/video/sphere) broken, inactive buttons | bug | Verify on dev | P2 | Bug, to verify | |
| 49 | Define what these buttons do | feature | Decision | – | Decision | |
| 50 | Search result priority: country → region → city/island → beach | feature | Sensible; small change in search ranking or grouping once agreed | P3 | Decision | |
| 51 | SL search: all levels only for Albania and Montenegro; "Belgij" ≠ "Belgija" | bug | Meilisearch index or locale data inconsistency. Verify | P2 | Bug, to verify | |
| 52 | Non-EN search → country page is empty | bug | Localized route resolution bug (all non-EN locales) | P1 | Bug | |
| 53 | From Albania page, search France → client-side exception | bug | Same family as #52/#55 | P1 | Bug | |
| 54 | All non-EN: every search result level → empty page | bug | Same as #52 | P1 | Bug | |
| 55 | Click Europe (all languages) → page loads, then client-side exception | bug | Continent page crash | P1 | Bug | |
| 56 | Click any country (except SI, DK, BE, AL, NL) → client-side exception | bug | Country page crash for most countries, maybe data-dependent (e.g. a null field) | P1 | Bug | |
| 57 | Sort countries A–Z and by beach count | feature | Small feature | P3 | Decision | |
| 58 | Sticky header | feature | Small feature | P3 | Decision | |
| 59 | Country level: switch beach view on "browse all" like on "most visited" | feature | Small feature | P3 | Decision | |
| 60 | Next page / next country click broken | bug | Probably the same as #56 | P1 | Bug | |
| 61 | Map sphere click → `NoSuchKey` (Albania); beach page works | bug | Matches the bug fixed in Beachrex/backend#23 (map/search tour URL ignored `mobile_upload`) | P2 | Likely fixed, retest | Beachrex/backend#23 |
| 62 | Click beach on map → open the beach page | feature | Small feature | P3 | Decision | |
| 63 | No videos for some Albania and Germany beaches | bug | Data (no video media rows) or frontend. Verify per beach | P2 | Bug, to verify | |
| 64 | No webcams for Albania and Belgium | bug | Data. Verify | P2 | Bug, to verify | |
| 65 | Germany/Denmark: map shows webcam, card click and beach page do not | bug | Same family as #36 | P2 | Bug, to verify | |
| 66 | Spain: no videos for several beaches | bug | Data or frontend. Verify | P2 | Bug, to verify | |
| 67 | Media switch differs between map and beach page | bug | Frontend consistency | P3 | Bug | |
| 68 | Beach page webcam toggle disabled (red symbol) | bug | Probably an invalid or missing webcam URL. Verify | P2 | Bug, to verify | |
| 69 | Lake/river beaches filter shows nothing on the map | bug | `waterbody` filter mapping. Verify | P2 | Bug, to verify | |
| 70 | Map does not separate indoor and outdoor pool like home | bug | Filter parity | P3 | Bug | |
| 71 | These functions are not programmed; how should they work? | question | Decision | – | Decision | |
| 72 | Map needs a double click to show a beach | bug | Frontend map click handler | P2 | Bug | |
| 73 | Map card: place and country clickable | feature | Small feature | P3 | Decision | |
| 74 | DE → map → logo: language resets to EN (all languages) | bug | The logo link is not locale-aware. Use the URL builders | P1 | Bug | |
| 75 | RU, UA, EL, BG: beach page text not in their script | bug | Missing translations (DB `*_translation` + UI strings) | P3 | Translation/data | |
| 76 | Same as #75 on maps | bug | Missing translations | P3 | Translation/data | |
| 77 | EUROPE on the map → empty page | bug | Same family as #55 | P1 | Bug | |

Second sheet ("ZA NAREDITI, RAZMISLITI"): one note, item 2 needs a definition of what the page should do → **Decision**.

## Proposed order
1. **P1 crashes and navigation**: 19/55/77 (continent page), 56/60 (country pages), 52/53/54 (non-EN search → empty pages), 24/25/74 (locale jumps). Mostly in web-client; likely a few root causes.
2. **Retest batch** for the tester: 6, 7, 18 (fixed); 8, 17, 30, 38, 44, 61 (likely fixed).
3. **P2 data/feature bugs**: beach type and icons (45/46), map city bounds (43), double click (72), search z-index (40), media and webcams (36, 48, 63–68), lake/river filter (69).
4. **Translations**: one audit of all 19 `messages/*.json` (missing keys) plus DB translation coverage per locale, then fill (AI translation pass via `beaches:translate` where it fits).
5. **Decisions** list for the owner: 20, 21, 31, 32, 34, 39, 42, 47, 49, 50, 57, 58, 59, 62, 71, 73.

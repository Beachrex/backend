# TASK-004 — Pre-launch audit (web-client)

Date: 2026-10-08 · Code: `Beachrex/web-client` `origin/dev` @ `5f2e44c` · Read-only, nothing fixed yet.
Live check: **NOT RUN** — the sandbox cannot reach `*.beachrex.com` (proxy 403). A local Playwright run follows
before fixes; rows marked *(verify locally)* depend on it.

Categories: **Code** (agent fixes) · **Data** (DB content; agent prepares, owner runs) · **Decision** (owner) ·
**Already fixed**. Prio: **P1** visible to every visitor or a 404/empty page · **P2** wrong/fake content on a
page · **P3** polish (SEO/a11y/logs).

## A. Translations (`messages/*.json`, 585 keys in `en.json`)

| # | Finding | Cat | Prio |
|---|---|---|---|
| A1 | `si.json` has no `Auth` and no `AI` section (56 keys). No fallback locale is configured (`src/i18n/request.ts`), so Slovenian sign-in/AI pages show raw keys or throw *(verify locally)* | Code | P1 |
| A2 | `it`, `ru`, `ua` have 26 extra keys (`Navigation.*`) unused by the code | Code | P3 |
| A3 | Values identical to English (4–37 per locale; most are fine: "Filter", "Home", names). Fake values among them: `Common.accommodationDefaultTitle` "Apartma Pehta", `accommodationDefaultBeach` "Haludovo Beach", `BannerSection.badgeFestival` "Crna Ovca Festival" | Code (see C5) | P2 |
| A4 | Hardcoded English (not through next-intl): `payment/success` and `payment/cancel` (~7 each), `WorldQuickActionsSection` (10), `ai/page` (~6), `ai/chat`, `ai/itinerary`, `BurgerMenu` ("Beach map with filter", "Accommodation"), "Loading…", `StyledSelect` | Code | P2 |
| A5 | Hardcoded Slovenian in every locale: `TopBar.tsx:107,162` "Sporočilo…", "Izpostavljeno sporočilo…"; `AccommodationCard.tsx:60` "Najbolj obiskano" | Code | P1 |
| A6 | ~90 hardcoded English `alt` texts ("Accommodation 1", "image 0", …) | Code (SEO step) | P3 |
| A7 | No CI check for key parity across the 19 files | Code | P2 |
| A8 | `Metadata.description` (site-wide meta description) is a long first-person founder text with typos ("sperichal", "charasteristics") | Decision (text) | P1 |
| A9 | DB translations (beach names "Beach …" in all locales, missing region translations, ~21% beaches with a description) — HANDOFF "Data quality" | Data | P2 |

## B. Menu, header and navigation

| # | Finding | Cat | Prio |
|---|---|---|---|
| B1 | `BurgerMenu`: only `menu_en.json` and `menu_si.json`; 17 locales get the EN menu with `/beaches/europe`, while the continent slug is localized (de `europa`, …) → likely 404 *(verify locally)*. All items except "Europe" are `enabled:false` | Code + Decision (menu content) | P1 |
| B2 | `BurgerMenu.tsx:195` "Accommodation" → `/beach-accommodation` (no route → bare 404) | Decision (hide?) | P1 |
| B3 | `BurgerMenu.tsx:214` `/mobile-app`: inside a commented block, not reachable | Code (cleanup) | P3 |
| B4 | `QuickMenu.tsx:238` "About us" is a `<span>` with no link | Code + Decision (target) | P1 |
| B5 | `QuickMenu.tsx:141` "Top beaches" opens the same view as "World beaches" | Decision | P3 |
| B6 | `TopBar.tsx` Favorites and My account buttons do nothing | Decision (hide?) | P1 |
| B7 | `SearchBar.tsx` Accommodations and Shop icons do nothing | Decision (hide?) | P1 |
| B8 | `GlobalSearch.tsx:290` Search button only reopens the dropdown (no results page) | Decision | P3 |
| B9 | `useLocalizedPath.ts`: beaches word map lacks da/pt/nl/el/sq (HANDOFF open item, still true); ~25 `console.log` per language switch (+4 in `TopBar`) | Code | P2 |
| B10 | The AI pages (`/ai`, chat, itinerary) are not linked from any menu | Decision | P2 |
| B11 | No catch-all: an unknown URL gets Next's bare 404 without header/footer; `[locale]/not-found.tsx` is only an `<h1>` with no link home | Code | P2 |

## C. Footer

| # | Finding | Cat | Prio |
|---|---|---|---|
| C1 | Info column = `static_pages` with `is_in_footer` (about-us, privacy-policy, terms-of-use, contribution-rules, contact). Seeder has EN placeholders only; real content in 19 locales is step 3 | Data | P1 |
| C2 | Browse column `/destinations` and `/accommodations` (all 19 files) → no route → 404 (HANDOFF open item, still true) | Decision (remove or retarget) | P1 |
| C3 | `da`, `nl`, `sq` footer hrefs use localized paths, the other 16 use internal paths (works, inconsistent) | Code | P3 |
| C4 | Most-viewed column and newsletter privacy link are API-driven; empty if nothing is flagged | Data *(verify locally)* | P2 |
| C5 | Footer translations (QA #27, #28, #29): the footer UI strings are translated in all 19 files (only the hrefs equal EN). The untranslated part is most likely the Info column: static page titles exist only in EN in the DB (seeder), so every locale shows the EN fallback → fixed by step 3 | Data (step 3) | P1 |

## D. Pages

| # | Page | Finding | Cat | Prio |
|---|---|---|---|---|
| D1 | `/beaches` (QA #48) | Mock: 4× "Plaža Ladinka" (25 €, rating 7.2), "Country 1..12" with `Math.random()` counts/prices (hydration mismatch), hardcoded totals (120 countries, 22 000 river beaches…), quick actions = `console.log`, "All beaches" `href="#"`, title "Countries in Europe", 0 `<h1>`, no metadata, client-only | Code (step 4) | P1 |
| D2 | Beach, city, region, island, country | Fake accommodation section ("Apartma Pehta", Baška, "from 25 €", Croatia flag) when no data; `BeachDetailPage.tsx:112` "569 accommodations" on every beach | Code + Decision (hide until real source) | P1 |
| D3 | Beach | `BottomBadgesBeach.tsx:25` Lorem Ipsum when a beach has no description (~79% of beaches) | Code | P1 |
| D4 | Beach | `GetYourGuideSection`: heading + filter buttons, no content | Decision (hide?) | P2 |
| D5 | Beach, city | Distance/filter dropdown handlers are `console.log` only | Code | P2 |
| D6 | City | name falls back to `'Malinska'` | Code | P2 |
| D7 | Country, region, island, city | "All beaches (n)" `href="#"` | Code | P2 |
| D8 | Continent | Meta description says "0 countries"; "Countries in Europe" title on every continent | Code | P2 |
| D9 | Home | Hardcoded "1500 beaches", "+6250 accommodations"; blog button → `/blog` (no route, locale-less) ; accommodations button → 404; media item buttons and accommodation CTA do nothing; 0 `<h1>` | Code + Decision (numbers, blog, accommodation) | P1 |
| D10 | `/about` | Placeholder "Description about us page", no metadata. Duplicate of the CMS page `about-us` | Decision (redirect to `/p/…` or own copy) | P1 |
| D11 | `/beach-type`, `/user-type` (+ `[type]`) | Stub pages with debug `<h1>` ("Beach Types Page: /beach-type/sandy"), any `[type]` → 200; listed in the sitemap; localized index URLs 404 | Decision (remove for launch?) | P1 |
| D12 | `payment/success`, `payment/cancel` | Redirect/links to `/internal/...` (no route → 404), locale-less links, no i18n | Code | P2 (only if Stripe is on at launch) |
| D13 | `auth/callback` | Hardcoded `/en` link (HANDOFF open item, still true) | Code | P2 |
| D14 | `/beach-map` | Client-only, no metadata, 0 `<h1>` | Code (SEO step) | P2 |

## E. SEO (step 5 baseline)

| # | Finding | Cat |
|---|---|---|
| E1 | 0 `<h1>` on home, continent, country, region, island, city, `/beaches`, `/beach-map` (titles are `<span>`) | Code |
| E2 | No metadata on `/about`, `/beaches`, `/beach-map`, AI chat/itinerary | Code |
| E3 | `sitemap.ts` emits a `<urlset>` of .xml files instead of a `<sitemapindex>`; static sitemap lists stub pages and 36 non-existent URLs; slug drift vs `routing.ts` (bg, de, it) | Code |
| E4 | No JSON-LD audit yet, no `llms.txt`, no AI-crawler policy | Code + Decision |
| E5 | No Google Analytics / CookieYes yet | Code (owner sends GA ID + CookieYes ID) |

## F. Cleanup (not visible)

| # | Finding | Cat |
|---|---|---|
| F1 | `console.log` in `BeachCategorySearch`, `LeftSidebarFilters` (×5), sitemap routes | Code |
| F2 | ~15 unused files (`TestImageComponent.tsx`, `HeroSearchSection.tsx`, `LeafletMapComponent.tsx`, …), `src/lib/utils/__tests__/manual-test-seo.ts` | Code |
| F3 | `localhost` fallbacks for `NEXT_PUBLIC_API_ENDPOINT` in 5 places (safe while the build sets the var) | Code (P3) |
| F4 | Unused `routes.*` messages namespace with wrong de/ru map slugs | Code |

## Proposed PR order (after the owner's decisions)

1. **A1 + A5 + A7**: Slovenian `Auth`/`AI`, remove hardcoded SL strings, key-parity unit test (CI).
2. **D3 + D2 + D9 numbers**: no Lorem Ipsum, no fake accommodations / "569", no fake counts.
3. **Dead links/buttons** per the owner's decisions (B1, B2, B4, B6, B7, C2, D9, D10, D11) + B11 catch-all 404.
4. **Footer pages content** (step 3, backend command + content in 19 locales).
5. **`/beaches`** (D1, step 4, QA #48).
6. **Small code fixes**: B9, D5–D8, D12, D13, A4, F1–F4.
7. **SEO + AI search + GA/CookieYes** (E1–E5, A6, A8, D14).
8. **Runbook review**.

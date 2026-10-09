# Beachrex mobile app — engineering plan and development guide

**Status:** Planned (TASK-005, owner request 2026-10-09). Nothing is built yet.
**Owner's brief (2026-10-09):** "React Native, NativeWind, EAS – Expo dev. Bistvo je iskanje plaž in vse kar pripada zraven. Grafika po našem CGP-ju, dober UX."
**Who reads this:** the **mobile agent** (see [`docs/AGENT-ROLES.md`](../AGENT-ROLES.md)). The task list is [`MOBILE-TASKS.md`](MOBILE-TASKS.md).

The rules in [`docs/DEVELOPMENT-RULES.md`](../DEVELOPMENT-RULES.md) apply to the mobile repo too (git flow, proof by output,
tests that can fail, no secrets, owner decides promotion). This file adds what is specific to the app.

---

## 1. Goal and scope

A native iOS and Android app for **finding beaches**: near me, on a map, by name, by place and by filters,
with a beach page that shows everything Beachrex knows about the beach (photos, 360° views, video, webcam,
facts, weather, sea temperature, directions). Same data and the same 19 languages as beachrex.com, a native UX,
and the BeachRex brand (CGP v1.0).

### In scope for v1 (store release)

| Area | What the user can do |
|---|---|
| Discover | See beaches near the current location, the most visited beaches, quick filter tiles, browse continent → country → region / island / city |
| Search | Type a beach or place name in any language, get suggestions while typing, open the result; recent searches |
| Map | See beaches in the visible area (clustered), filter them, tap a marker for a card, open the beach |
| Filters | The same filters as the web map (beach type, family, parking, waterbody, pool type, depth, …), quick filters on top |
| Beach page | Gallery (swipe + full screen), 360° tour, video, webcam, badges and facts, description, weather + sea temperature, mini map, **Navigate** (Apple / Google Maps), **Share** (beachrex.com link), **Save** |
| Places | Country, region, island, city pages: banner, popular beaches, sub-places, all beaches with filters |
| Saved | Save beaches on the device (no account needed) |
| More | Language (19), about, legal pages (CMS), contact, the author page, app version |
| Links | beachrex.com links open the app on the right screen (universal / app links, production only) |

### Not in v1 (later phases, see `MOBILE-TASKS.md` phase 2)

Sign-in (Google, Apple), AI chat, AI itineraries (paid), favorites synced to an account, push notifications,
offline map packs. **Everything hidden on the web** stays hidden in the app too
([`docs/features/hidden-for-launch.md`](../features/hidden-for-launch.md): accommodation, shop, blog,
GetYourGuide, Google ratings, my account).

### Store rules that shape the scope

- **Paid digital content inside the app must use Apple In-App Purchase / Google Play Billing** (App Store
  guideline 3.1.1, Play payments policy). The web sells AI itinerary credits through Stripe; the app must **not**
  open Stripe checkout or link to it in v1. Paid itineraries in the app need IAP (e.g. RevenueCat) plus a backend
  entitlement endpoint — phase 2, owner decision.
- **If the app offers Google sign-in, it must also offer Sign in with Apple** (guideline 4.8). That is why sign-in
  is phase 2 and needs backend work.
- **Account deletion in the app** is required once accounts exist (guideline 5.1.1(v)).
- iOS **privacy manifest** and Play **Data safety** form must match what the app collects (v1: location only on
  the device, no account, crash reports without personal data).

---

## 2. Stack (decided; changes need an ADR)

| Concern | Choice | Notes |
|---|---|---|
| Framework | **Expo** (latest stable SDK at project start), React Native, TypeScript `strict` | Record the SDK version in the ADR log when the repo is created |
| Routing | **Expo Router** (file-based, typed routes) | Tabs + stacks; deep links come for free |
| Styling | **NativeWind v4** (Tailwind for RN) | Tokens from CGP in `tailwind.config.js` (§5) |
| Server state | **TanStack Query v5** | Same library as the web; persisted cache (§8) |
| API client | Generated from the backend OpenAPI with **@hey-api/openapi-ts** (same generator as web-client) | `src/api/` is generated, never edited by hand |
| i18n | **use-intl** (the core of next-intl, framework-agnostic) + `expo-localization` | Same ICU message format as the web (`{var}`, single braces); 19 locales |
| Local storage | **react-native-mmkv** (saved beaches, recent searches, settings, query cache persistence) | Needs a dev build (not Expo Go) |
| Images | **expo-image** | Disk cache, placeholders, Imagor URLs (§7) |
| Map | **react-native-maps** (Apple Maps on iOS, Google Maps on Android) + **supercluster** | Android needs a Google Maps API key (owner); MapLibre is the fallback if tile cost/terms become a problem (ADR) |
| Bottom sheets | **@gorhom/bottom-sheet** | Map list and filters |
| Lists | **@shopify/flash-list** | Long beach lists |
| Location | **expo-location** (when-in-use only) | Never background location |
| Media | **expo-video** (uploaded videos), **react-native-webview** (360° tours, webcams, YouTube embeds) | |
| Sharing / directions | React Native `Share`, `Linking` (Apple Maps / Google Maps URLs) | |
| Icons | **lucide-react-native** (same family as the web) + beach category icons from Spaces `categories_icons/` | |
| Fonts | **Figtree** (UI, as on the web) via `expo-font`; **Damion** only for the faded decorative word in banners | Logo is never typeset: SVG from the CGP package |
| Crash reports | **Sentry** (`@sentry/react-native`, Expo plugin) — owner creates the project; no personal data | |
| Tests | **Jest** (`jest-expo`) + **React Native Testing Library**; **Maestro** flows for E2E (run by the owner or a CI with emulators) | |
| Builds / releases | **EAS Build**, **EAS Submit**, **EAS Update** (OTA) | Profiles in §10 |

No other runtime dependency without a reason in the PR (DEVELOPMENT-RULES §10).

---

## 3. Repository and layout

- **Repo:** `Beachrex/mobile-app` (owner creates it on GitHub and adds it to the agent's environment).
  Branches as in the other repos: `dev` (default), `uat`, `main`. Work branches from `origin/dev`, PR into `dev`.
- **App identifiers:** iOS bundle ID and Android package `com.beachrex.app` (proposal — owner confirms before the
  first EAS build; they cannot change after the store listing exists). App name in stores: "BeachRex".
- The repo gets its own `CLAUDE.md` that points here (like `web-client/CLAUDE.md`).

```
mobile-app/
  app/                         Expo Router routes
    _layout.tsx                providers: QueryClient, IntlProvider, fonts, theme, Sentry
    (tabs)/_layout.tsx         tab bar: Discover · Map · Search · Saved · More
    (tabs)/index.tsx           Discover
    (tabs)/map.tsx
    (tabs)/search.tsx
    (tabs)/saved.tsx
    (tabs)/more.tsx
    beach/[slug].tsx           beach page
    place/[type]/[slug].tsx    country | region | island | city
    continent/[slug].tsx
    filters.tsx                modal
    gallery/[slug].tsx         full-screen gallery (modal)
    page/[slug].tsx            CMS page (legal, about)
    +not-found.tsx
  src/
    api/                       GENERATED (openapi-ts) — do not edit
    lib/                       api config, url builders, imagor, deep links, location, storage
    features/<area>/           components + hooks per area (discover, search, map, beach, place, saved, settings)
    ui/                        design system components (Button, Chip, Card, Sheet, Badge, …)
    i18n/                      locale detection, IntlProvider setup
  messages/                    19 locale files (en.json is the source)
  assets/                      CGP logo SVGs, app icon, splash, fonts
  e2e/                         Maestro flows
  __tests__/ or *.test.tsx     Jest
  app.config.ts                Expo config (reads EXPO_PUBLIC_* and the build profile)
  eas.json                     build/submit/update profiles
  tailwind.config.js           CGP tokens
```

---

## 4. Backend API (what the app uses)

Base URL per environment (`EXPO_PUBLIC_API_URL`): dev `https://dev-api.beachrex.com`, uat
`https://uat-api.beachrex.com`, prod `https://api.beachrex.com` (prod is not live yet — runbook `prod-launch.md`).
Every request sends **`Accept-Language`** with the app locale (`si` is accepted and mapped to DB `sl`; send the same
codes as the web). Never send `X-Beachrex-Client` (it marks internal tools; app visits must count as views).
The API is public for everything v1 needs; no token.

| Screen | Endpoints (all `GET /api/v1/...`) |
|---|---|
| Discover | `spatial/beaches/nearest?latitude&longitude&limit`, `beaches/most-visited`, `continents`, `countries`, `first-page-content` |
| Search | `search?q&limit&types&country_code` (Meilisearch, any language); `resolve-slug/{slug}` for links |
| Map | `spatial/beaches/bounding-box?min_lat&min_lon&max_lat&max_lon&…filters`, `beaches/map-bounds`, `spatial/beaches/density-grid` (zoomed out) |
| Filters | `beaches/filters?continent_code&country&region&island` (labels and options, localized) |
| Lists | `beaches?…filters&limit` (cards carry `features`: beach type, family, adventurer, youth, parking) |
| Beach page | `beach-details/{identifier}`, `beach-details/{identifier}/weather` (weather + sea temperature), `spatial/beaches/within-radius` (similar beaches nearby) |
| Places | `continents/{code}`, `countries/{codeOrSlug}`, `regions/{slug}`, `islands/{slug}`, `cities/{slug}` (+ `/weather`), `regions?country_code`, `islands?…`, `cities?…` |
| CMS pages | `static-pages`, `static-pages/{slug}` |
| Old links | `legacy-redirect?path=` (old beachrex.com URLs opened in the app) |

The OpenAPI file is `storage/api-docs/api-docs.json` in this repo (also served at `/api/v1/openapi.json`).
Regenerate the app client after backend API changes: `npx @hey-api/openapi-ts -i <path or URL> -o src/api`.

**Rule for the backend from the first store release on:** `/api/v1` changes must be backward compatible
(additive only). An installed app cannot be forced to update; a removed field breaks it. Breaking changes need
`/api/v2` or a phased plan (ADR). This rule is added to `DEVELOPMENT-RULES.md` §9.

### Backend work the app needs (done by the web/backend agent, see `MOBILE-TASKS.md` §B)

| ID | What | When |
|---|---|---|
| B-M1 | `GET /api/v1/app-config` (public): `min_supported_version` per platform, `latest_version`, feature flags (e.g. `ai_chat`), store URLs | before v1 release |
| B-M2 | Native sign-in: `POST /api/v1/auth/google/native` (Google ID token, verified server-side) and `POST /api/v1/auth/apple` (Apple identity token); both return a Sanctum token; `DELETE /api/v1/me` (account deletion) | phase 2 |
| B-M3 | Favorites API (`GET/POST/DELETE /api/v1/me/favorites`) — also brings back web H6 | phase 2 |
| B-M4 | `apple-app-site-association` and `assetlinks.json` served by web-client on **beachrex.com only** (universal / app links) | before v1 release, after prod is live |
| B-M5 | IAP entitlements (RevenueCat webhook → existing entitlements) for paid itineraries | phase 2, owner decision |

---

## 5. Design system (CGP v1.0)

Source: `web-client/public/brand/BeachRex_CGP/` (guidelines PDF, `04_Barve/colors.json`, SVG logos, app icons).
Copy the SVGs and icons into `assets/` — never redraw or retype the logo (CGP §05).

### Colour tokens (`tailwind.config.js`)

| Token | Hex | Use |
|---|---|---|
| `teal` | `#00B2C9` | Brand primary: active tab, selected chip, primary button **fill**, map cluster, links/icons |
| `orange` | `#EB8751` | Accent: "Navigate", highlights, sun / sea temperature, most-visited badge |
| `deep-sea` | `#0F3B45` | **All body text and headings**, dark surfaces, text on teal/orange buttons |
| `sand` | `#FFF4E8` | Warm backgrounds (Discover header, empty states, cards on white) |
| `white` | `#FFFFFF` | Default background |
| greys | `#222222`, `#4D4D4D`, `#8D8D8D`, `#E7E7E7`, `#F5F5F5` | Secondary text, borders, skeletons (same values as the web) |
| place colours | country `#DD0000`, region `#FF2877`, island `#FFC422`, city `#FF8000` | Place pills and filter buttons, as on the web pages |

**Contrast (WCAG 2.1, checked):** white text on teal is 2.6:1 and white on orange 2.6:1 — **not allowed for normal
text**. Deep Sea on teal is 4.7:1, Deep Sea on orange 4.7:1, Deep Sea on white 12:1, on Sand 11:1. So: primary
buttons are teal with **Deep Sea** label (or Deep Sea with white label); white text only on photos with a dark
scrim or on Deep Sea. Dark mode: Deep Sea backgrounds, white text, teal accents (v1 follows the system setting).

### Logo usage

Header and splash: horizontal colour logo on white/Sand (min 140 px wide on screen); on teal, dark or photos: the
white version. App icon and small spaces: the symbol (`02_Favicon_App/app-icon_*_1024.png`, min 16 px). No
shadows, rotation, recolouring or stretching.

### Type, spacing, shape

Figtree 400/600/700. Scale: 32 (screen titles), 24, 20, 17 (body), 15, 13 (captions). Support Dynamic Type /
font scaling up to 200 % without clipping. 4-pt spacing grid; screen side padding 16. Radius 12 for cards,
full for chips. Touch targets ≥ 44×44 pt. Icons 24 pt line icons (lucide), category icons from Spaces.

### Components (`src/ui/`)

Button (primary / secondary / ghost), IconButton, Chip (filter, removable), Badge (feature icons + label),
BeachCard (image, name, place, feature icons, distance, save), PlaceCard, SectionHeader ("All (n) →"),
SearchField, BottomSheet, Skeleton, EmptyState (illustration + text + action), ErrorState (retry),
OfflineBanner, Toast. Each has a Jest render test and a light/dark snapshot-free visual check in the PR
(screenshots from the owner's device or simulator).

---

## 6. UX principles and screens

Principles: **one-thumb use on the beach** (key actions at the bottom), **fast first result** (cached data shows
instantly, refresh in the background), **no dead ends** (every empty or error state has a next action), **never
English in another locale**, **no fake data** (same rule as the web, ADR "TASK-004: What Visitors See at Launch").

| Screen | Content and behaviour |
|---|---|
| First launch | Language from the device (fallback EN, changeable later). One screen explaining why location helps ("beaches near you"), then the system prompt; "Not now" works and the app stays useful (Discover without "near me", map starts on the last region or Europe) |
| Discover (tab 1) | Search bar (opens Search) · "Near you" horizontal cards with distance · quick filter tiles (sand, pebbles, family, adventurers, parties, lakes, pools … as on the web home) → open the map with that filter · "Most visited" · "Browse by country" |
| Search (tab 3) | Autofocus field, debounced (300 ms) suggestions grouped by type (beaches, cities, islands, regions, countries) with the place under each name; recent searches (MMKV, clearable); no result → suggest the map |
| Map (tab 2) | Full-screen map, clustered markers (teal clusters with count), "Search this area" after a pan, quick filter chips on top, "Filters (n)" button → filter sheet, list bottom sheet (peek / half / full) synced with the visible area, "My location" button, marker tap → card at the bottom → beach page |
| Filters (modal) | Groups from `beaches/filters`, counts where the API gives them, sticky "Show n beaches" and "Clear"; state lives in the URL-like route params so it survives navigation and deep links |
| Beach page | Hero gallery (swipe; tap → full-screen pinch-zoom), media tabs: photos / 360° / video / webcam (only those that exist), name + place breadcrumb, feature badges, weather + sea temperature card, description (hidden if empty — never Lorem Ipsum), facts in groups (same groups as the web), mini map, sticky bottom bar: **Navigate** (orange), Share, Save; "Similar beaches nearby" with a distance selector |
| Place pages | Banner with name and place colour pill, weather (region/city/island), popular beaches carousel, sub-places (regions/islands/cities), "All beaches (n)" → list with filters |
| Saved (tab 4) | Saved beaches (device), sorted by distance when location is known; empty state → Discover |
| More (tab 5) | Language, About, Media kit / author page links (open web), legal pages (CMS, in-app), contact (`mailto:info@beachrex.com`), version + build, open-source licences |

Loading: skeletons, never spinners on full screens. Errors: inline retry; network loss → `OfflineBanner`, cached
data stays visible. Haptics on save and filter apply (light). Respect "Reduce motion".

---

## 7. Images and media

- Build image URLs exactly like the web (`web-client/src/lib/utils/beach-images.ts`): Imagor
  (`s3.beachrex.com`) with the teaser size for cards, large for the gallery, `img/beaches_mobile/` for beaches with
  `mobile_upload = true`. Port the helpers with their unit tests; never link Spaces directly (ADR TASK-002 §5).
- `expo-image` with `cachePolicy="memory-disk"`, a blurred/sand placeholder, and `recyclingKey` in lists.
- Photo credit: photos are works of MOJA PLAŽA, d.o.o. (terms §6). The full-screen gallery shows
  "© MOJA PLAŽA, d.o.o." small at the bottom.
- 360° tours and webcams: `react-native-webview` in a full-screen modal, loaded only on tap (data and battery).
- Video: `expo-video`, no autoplay on cellular.

---

## 8. Data, caching, offline

- TanStack Query with persistence to MMKV (`@tanstack/query-async-storage-persister` adapter over MMKV):
  reference data (continents, countries, filters) `staleTime` 24 h; beach details 1 h; lists 10 min; map viewport
  queries 5 min keyed by rounded bounds + filters.
- Saved beaches store a snapshot (id, slug, name, place, image path, coordinates) so the Saved tab works offline.
- `NetInfo` drives the offline banner and pauses refetching.
- No personal data leaves the device in v1. Location is used for requests (`nearest`, distance sort) and not
  stored on the server.

---

## 9. Internationalisation

- 19 locales, same codes as the web: `en si de hr fr es it ua ru pl cs hu bg sk da pt nl el sq` (`si` = Slovenian).
- Messages in `messages/*.json`, ICU syntax with single braces `{count}`; `en.json` is the source.
- A Jest test fails when any locale misses or adds a key versus `en.json` (port `web-client/e2e/messages-parity`).
- Reuse web strings where the text is the same (copy the values; the repos stay independent).
- Dates, numbers, distances and temperatures through `Intl` with the app locale (Hermes supports `Intl`).
- Language switch in More applies immediately (re-render + refetch with the new `Accept-Language`).
- RTL: none of the 19 locales is RTL; keep layouts direction-safe anyway (`start`/`end`, not `left`/`right`).

---

## 10. Environments, builds and releases (EAS)

| EAS profile | API | Distribution | Channel (EAS Update) | Built from |
|---|---|---|---|---|
| `development` | dev | dev client (internal) | — | any branch, by the agent/owner |
| `preview` | uat (or dev) | internal (TestFlight internal / Play internal testing) | `preview` | `dev` or `uat`, on owner request |
| `production` | prod | stores | `production` | `main`, **only when the owner asks** |

- `app.config.ts` reads `EXPO_PUBLIC_API_URL`, `EXPO_PUBLIC_IMAGOR_URL` and `EXPO_PUBLIC_ENV` from the profile.
  `EXPO_PUBLIC_*` values are baked into the bundle — never put secrets there.
- Secrets (EAS / GitHub only, never in the repo or chat): `EXPO_TOKEN`, Google Maps Android API key, Sentry
  auth token, Apple / Google service account keys. The agent names them; the owner sets them.
- **OTA updates** (EAS Update) only for JS/asset changes with the same `runtimeVersion` (policy `appVersion`).
  A native dependency change requires a new build. Never ship an OTA to `production` without the owner's request.
- Version: `version` (semver, user-visible) in `app.config.ts`; build numbers auto-incremented by EAS
  (`autoIncrement`).
- dev/uat builds are never public: internal distribution only (DEVELOPMENT-RULES §9 visibility rule).

---

## 11. Quality, tests and proof

Before every PR (the agent runs them and pastes real output):
- `npx tsc --noEmit`, `npx expo lint`, `npx jest --ci`, `npx expo-doctor`;
- `npx expo export --platform ios --platform android` (proves the bundle builds without a device).

Test rules (DEVELOPMENT-RULES §6 applies): pure helpers (URL builders, Imagor, filters ↔ params, distance
format, deep link parsing) get unit tests that can fail; screens get RNTL tests with a mocked API layer
(MSW or a fetch mock) for loading, empty, error and data states; every bug fix gets a regression test.

What the sandbox cannot do: run iOS/Android simulators or EAS cloud builds without `EXPO_TOKEN`. Device checks
are **NOT RUN** by the agent and listed for the owner in the PR (what to tap, what to expect). Maestro flows
live in `e2e/` so the owner (or a later CI with emulators) can run them: `maestro test e2e/`.

CI (GitHub Actions in `mobile-app`): typecheck, lint, Jest, expo-doctor, `expo export` on every PR. EAS builds
are triggered by hand (or by a tag later), not on every push.

Performance budget: cold start to Discover content < 2.5 s on a mid-range Android with a warm cache;
60 fps scrolling in lists (FlashList, memoised cards, image sizes from Imagor); map shows ≤ 300 markers at once
(cluster above that).

Accessibility: every touchable has `accessibilityLabel` (translated) and role; images have meaningful
labels (beach name + place); focus order checked with VoiceOver/TalkBack by the owner before release.

---

## 12. Deep links and sharing

- Scheme `beachrex://` for internal use; **universal links / app links on `https://beachrex.com` only**
  (production). Map web URLs to screens with the same rules as the web URL builders:
  `/{locale}/{beachesWord}/{continent}/{country}/…/{beach}` → `beach/[slug]`, places → `place/[type]/[slug]`
  (resolve with `resolve-slug`), `/{locale}/{beachMapWord}` → Map, old beachrex.com URLs → `legacy-redirect`.
- Share always shares the **web URL** of the beach/place in the app locale (works without the app), built with
  ported `buildBeachUrl` / `buildDestinationUrl` helpers.
- Web-client serves `/.well-known/apple-app-site-association` and `/.well-known/assetlinks.json` (B-M4); dev and
  uat must not claim app links.

---

## 13. Privacy, consent and store listing

- v1 collects: approximate/precise location while in use (on device only, for distance and "near me"), crash
  reports (Sentry, no personal data, IP not stored). No ads, no analytics SDK in v1 (owner decides later; any
  analytics needs a consent screen and an ADR, like the web CookieYes setup).
- iOS: `NSLocationWhenInUseUsageDescription` translated (InfoPlist strings for the 19 locales), privacy manifest
  (required reason APIs used by dependencies), App Tracking Transparency **not** needed (no tracking).
- Play: Data safety form matches the above. Target the current Play target SDK level required at release.
- Store texts in the 19 languages (later) — EN and SL first; screenshots from real screens (no mockups with fake
  data). Privacy policy URL: the CMS page on beachrex.com.

---

## 14. Owner actions (accounts and decisions)

1. Create the GitHub repo `Beachrex/mobile-app` (branches `dev`, `uat`, `main`) and add it to the agent's environment.
2. Expo account / organisation "beachrex"; create `EXPO_TOKEN` and store it as a GitHub Actions secret.
3. Apple Developer Program (organisation account for MOJA PLAŽA, d.o.o., needs a D-U-N-S number) and Google Play
   Console (organisation).
4. Confirm the bundle ID / package name (`com.beachrex.app` proposed) and the store name.
5. Google Maps API key restricted to the Android app (package + SHA-1) → EAS secret.
6. Sentry project (or say no to crash reporting) → DSN as `EXPO_PUBLIC_SENTRY_DSN` (public by design), auth token as EAS secret.
7. Decide later: sign-in (phase 2), paid itineraries via IAP (phase 2), analytics in the app.

---

## 15. Risks

| Risk | Mitigation |
|---|---|
| API changes break installed apps | Additive-only rule (§4), `app-config` min version + "please update" screen |
| Map tile cost / terms | Apple/Google native maps in v1; MapLibre + vector tiles as a documented fallback |
| Data quality (names with "Beach …", ~21 % descriptions, missing region translations) | Same as the web: hide empty sections, never invent text; data cleanup is a separate backend task |
| Store rejection (payments, sign-in) | v1 without accounts and payments; phase 2 only with IAP + Apple sign-in |
| Agent cannot test on devices | Unit/RNTL tests + `expo export` in CI; Maestro flows and a per-PR device checklist for the owner |

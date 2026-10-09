# Beachrex mobile app — tasks for the mobile agent

**Status:** Planned (TASK-005). Read [`MOBILE-PLAN.md`](MOBILE-PLAN.md) first; it holds the decisions this list relies on.

## How to work

- One task = one branch = one PR into `dev` of `Beachrex/mobile-app`; merge on green CI (DEVELOPMENT-RULES §5).
- Do the tasks in order; a task may start only when the tasks it depends on are merged.
- Every PR body: what changed, real output of `tsc`, `expo lint`, `jest`, `expo-doctor`, `expo export`, and a
  **device checklist for the owner** (what to open and what must happen), because the agent cannot run a device.
- Every user-facing string in all 19 `messages/*.json`; the parity test must stay green.
- Use only real API data. A section without data is hidden; nothing is invented.
- Record each task's status here (Planned → In progress → Done with PR number) and keep `docs/HANDOFF.md` current.
- Ask the owner only for accounts, secrets, store actions, product decisions and releases.

Status labels: **Planned** · **In progress** · **Done** (PR) · **Blocked** (reason).

---

## Phase 0 — Foundation

| ID | Task | Depends | Acceptance criteria | Status |
|---|---|---|---|---|
| M0.1 | Create the Expo app (TypeScript strict, Expo Router, latest stable SDK), `CLAUDE.md` pointing to the backend docs, README, `.env.example` with `EXPO_PUBLIC_*` names | owner: repo | `npx expo-doctor` clean; `expo export` builds; ADR "Mobile app stack" with the SDK version | Planned |
| M0.2 | Tooling: ESLint (expo config), Prettier, Jest (`jest-expo`) + RNTL, `tsc` script; GitHub Actions CI (typecheck, lint, jest, expo-doctor, expo export) on PRs | M0.1 | A deliberately broken test fails CI (shown in the PR) | Planned |
| M0.3 | `eas.json` profiles `development` / `preview` / `production`, `app.config.ts` reading `EXPO_PUBLIC_API_URL`, `EXPO_PUBLIC_IMAGOR_URL`, `EXPO_PUBLIC_ENV`; bundle ID from the owner; `runtimeVersion` policy | M0.1, owner: Expo + IDs | `eas build --profile development` succeeds (owner runs it if the agent has no `EXPO_TOKEN`) | Planned |
| M0.4 | NativeWind v4 + CGP tokens (colours, Figtree, spacing, radius) in `tailwind.config.js`; dark mode via system | M0.1 | A token test checks the CGP hex values; contrast helper test (Deep Sea on teal ≥ 4.5) | Planned |
| M0.5 | API client generated from `storage/api-docs/api-docs.json` (`@hey-api/openapi-ts`), fetch config with base URL and `Accept-Language`; TanStack Query provider with MMKV persistence | M0.3 | A unit test proves every request sends `Accept-Language` and no `X-Beachrex-Client` | Planned |
| M0.6 | i18n: `use-intl` provider, device locale → one of the 19 codes (fallback `en`), locale stored in MMKV; port the message parity test | M0.1 | Parity test fails when a key is removed from one locale | Planned |
| M0.7 | Port URL and image helpers from web-client (`beach-urls.ts`, `destination-url.ts`, `beach-images.ts` Imagor rules incl. `mobile_upload`) with their tests | M0.1 | Same inputs → same outputs as the web tests | Planned |
| M0.8 | Sentry (if the owner wants it): Expo plugin, no PII, source maps on EAS builds | M0.3, owner: Sentry | A test crash from a dev build appears in Sentry (owner checks) | Planned |

## Phase 1 — Design system and shell

| ID | Task | Depends | Acceptance criteria | Status |
|---|---|---|---|---|
| M1.1 | UI kit in `src/ui/`: Button, IconButton, Chip, Badge, BeachCard, PlaceCard, SectionHeader, SearchField, Skeleton, EmptyState, ErrorState, OfflineBanner, Toast | M0.4, M0.6 | RNTL test per component (renders, labels, disabled/pressed states); 44-pt targets | Planned |
| M1.2 | App shell: tab bar (Discover, Map, Search, Saved, More) with lucide icons, CGP logo header, splash screen and app icons from the CGP package | M1.1 | Tabs navigate; icons/splash use CGP files only (no redrawn logo) | Planned |
| M1.3 | First-launch flow: language detection, location rationale screen + system prompt, "Not now" path | M1.2 | Both paths tested; app usable without location | Planned |
| M1.4 | Network + error handling: NetInfo offline banner, query error boundaries with retry, `app-config` min-version check ("please update" screen) once B-M1 exists | M1.2 | Offline and error states tested with a mocked API | Planned |

## Phase 2 — Finding beaches (the core)

| ID | Task | Depends | Acceptance criteria | Status |
|---|---|---|---|---|
| M2.1 | Discover: near you (`spatial/beaches/nearest`), quick filter tiles, most visited, browse by country | M1.3 | Without location the "near you" row is replaced by a prompt; cards open the beach page | Planned |
| M2.2 | Search: debounced `search` with grouped results, recent searches (MMKV), empty state → map | M1.2 | Typing "zlatni rat" (any locale) lists the beach; recent searches clearable | Planned |
| M2.3 | Beach page part 1: header gallery, name + place breadcrumb, feature badges, description (hidden if empty), facts groups, mini map, sticky bar (Navigate / Share / Save) | M0.7, M1.1 | Uses `beach-details`; Navigate opens Apple/Google Maps at the coordinates; Share sends the web URL in the app locale | Planned |
| M2.4 | Beach page part 2: media tabs (360° WebView, `expo-video`, webcam WebView — only those that exist), full-screen gallery with pinch zoom and © line, weather + sea temperature card, similar beaches nearby with distance selector | M2.3 | Media loads only on tap; a beach without media shows no tabs | Planned |
| M2.5 | Map: react-native-maps, viewport query (`spatial/beaches/bounding-box`) with supercluster, "search this area", marker card, my-location button, list bottom sheet synced with the viewport | M1.2, owner: Android Maps key | ≤ 300 markers rendered; panning does not refetch until "search this area" or a settle debounce | Planned |
| M2.6 | Filters: sheet built from `beaches/filters`, quick chips, "Show n beaches", clear; filter state ↔ route params (shared by map and lists) | M2.5 | Filter → params → filter round-trip unit test; same filter keys as the web map | Planned |
| M2.7 | Place pages: continent, country, region, island, city (banner + place colour, weather, popular beaches, sub-places, all beaches with filters) | M2.3, M2.6 | Uses the same endpoints as the web pages; empty sections hidden | Planned |
| M2.8 | Saved: save/unsave everywhere (card + beach page), Saved tab sorted by distance, works offline from snapshots | M2.3 | Saved list survives app restart and airplane mode | Planned |

## Phase 3 — Polish and release v1

| ID | Task | Depends | Acceptance criteria | Status |
|---|---|---|---|---|
| M3.1 | More tab: language switch (immediate), About / legal pages from CMS (`static-pages`), contact, author page and media kit (open web), version, licences | M1.2 | Legal pages render in all 19 locales (EN fallback from the API) | Planned |
| M3.2 | Deep links: `beachrex://` + universal/app links for `beachrex.com` (after B-M4), web URL → screen mapping incl. `legacy-redirect` | M2.7, B-M4 | Unit tests for 10+ real web URLs and 3 old URLs; owner checks on a device | Planned |
| M3.3 | Performance pass: FlashList everywhere, memoised cards, Imagor sizes, startup trace | Phase 2 | Budget in MOBILE-PLAN §11 met on the owner's Android device (owner reports) | Planned |
| M3.4 | Accessibility pass: labels, roles, Dynamic Type 200 %, reduce motion, VoiceOver/TalkBack order | Phase 2 | Checklist in the PR; owner verifies with VoiceOver and TalkBack | Planned |
| M3.5 | Maestro flows: first launch, search → beach, map → filter → beach, save → Saved offline, language switch | Phase 2 | Flows in `e2e/`; owner runs `maestro test e2e/` on a dev build | Planned |
| M3.6 | Store readiness: privacy manifest, location strings in 19 languages, Data safety answers, store texts EN + SL, screenshots list | M3.1–M3.5 | Document `docs/store-listing.md` in the mobile repo with every answer | Planned |
| M3.7 | Release: `preview` build to TestFlight internal + Play internal testing (owner request), feedback round, then `production` build and submission (owner request) | M3.6, owner: store accounts | Builds installed by the owner; release only on the owner's "da" | Planned |

## Phase 2 product (after v1, each needs an owner decision)

| ID | Task | Needs |
|---|---|---|
| M4.1 | Sign in with Apple + Google (native), account screen, account deletion | B-M2 |
| M4.2 | Favorites synced to the account (merge device favorites on first sign-in) | B-M3, M4.1 |
| M4.3 | AI chat in the app (free, same API as the web, rate limits respected) | owner decision, `app-config` flag |
| M4.4 | Paid AI itineraries via In-App Purchase (RevenueCat), entitlements from the backend | B-M5, owner decision |
| M4.5 | Push notifications (e.g. sea temperature at saved beaches) with opt-in | owner decision, backend task |
| M4.6 | Offline region packs (beaches + images of a region) | owner decision |

---

## B — Backend / web tasks the app depends on (web agent)

These are done in `Beachrex/backend` / `Beachrex/web-client` by the web agent (not the mobile agent), each as a
normal PR with OpenAPI + `DEV_HANDOFF.md`. The mobile agent asks for them through the owner or the HANDOFF.

| ID | Task | Needed by | Status |
|---|---|---|---|
| B-M1 | `GET /api/v1/app-config`: `min_supported_version` / `latest_version` per platform, store URLs, feature flags; cached; values from config/env | M1.4, release | Done (2026-10-09): `config/mobile.php`, `MOBILE_*` env vars, `Cache-Control: max-age=300` |
| B-M2 | `POST /api/v1/auth/google/native` (verify Google ID token, audience = iOS/Android/web client IDs), `POST /api/v1/auth/apple` (verify Apple identity token), both return a Sanctum token; `DELETE /api/v1/me` with data removal; 401/403/422 tests | M4.1 | Planned |
| B-M3 | Favorites: `GET/POST/DELETE /api/v1/me/favorites` (ownership checks, tests) — also used by web H6 | M4.2 | Planned |
| B-M4 | web-client: `/.well-known/apple-app-site-association` and `/.well-known/assetlinks.json` only on `beachrex.com` (team ID and SHA-256 from the owner) | M3.2 | Planned |
| B-M5 | IAP entitlements (RevenueCat webhook → user entitlements), shared with the web credits | M4.4 | Planned |
| B-M6 | `DEVELOPMENT-RULES.md` §9: `/api/v1` additive-only once the app is in a store | before release | Done (TASK-005 docs PR) |

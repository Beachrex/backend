# TASK-004 — Pre-launch review (web-client + footer content + `/beaches` + SEO + prod runbook)

**Status:** Planned (spec, 2026-10-08) · **Owner request:** 2026-10-08 ("stran gre v produkcijo v naslednjih dneh")
**Repos:** `Beachrex/web-client` (most of the work), `Beachrex/backend` (static page content, API gaps, runbook)

## Goal

Before prod launch, every page a visitor can reach from the menu, the footer or the home page works, shows real
data, is translated in all 19 locales, and is indexable with sensible SEO. The prod launch runbook is checked
against the current code and infra so the owner can run it without surprises.

## Ground rules (owner, 2026-10-08)

- **Verify every finding in the code before fixing.** The tester's statuses are not always current; a finding
  already fixed on `dev` is marked as such, not fixed twice. Cross-check `docs/qa/dev-review-tracker.md`.
- **One thing = one PR into `dev`**, merge on green CI, verify the Deploy run. QA rows referenced as `QA #<row>`.
- No uat/prod promotion unless the owner asks.
- Server commands for the owner: one per message, no shell variables, no comments.
- Talk to the owner in Slovenian; code, docs and commits in English.

## Steps

### Step 1 — This spec (docs PR)

### Step 2 — Audit (read-only, no fixes) → `docs/tasks/TASK-004-audit.md`

Sources, in this order:
1. **Code**: every route under `src/app/[locale]/**` and the sitemap routes; every link in `Header/*`
   (`BurgerMenu`, `QuickMenu`, `SearchBar`), `Footer/*`, home sections and `messages/*.json` link values.
2. **Locales**: a script compares every `messages/*.json` against `en.json` (missing keys, extra keys, values
   still identical to English, `{{var}}` double braces). Output: counts per locale + key list.
3. **Mock/test data**: grep for `mock`, `placeholder`, `lorem`, `TestImageComponent`, hardcoded beach names
   ("Plaža Ladinka"), `console.log`, `TODO`, test pages and unused components reachable from routes.
4. **Live pages** (dev): only if the sandbox can reach `dev.beachrex.com` / `dev-api.beachrex.com`
   (currently blocked by the network policy). Otherwise the live check is listed as `NOT RUN` and done by the owner.
5. **DB content** (static pages, translations): via the dev API if reachable, otherwise the owner runs read-only
   commands.

Every finding gets one category and a verdict:

| Category | Meaning | Who |
|---|---|---|
| **Code** | bug, dead link, mock data, missing UI string | agent fixes (PR) |
| **Data** | DB content: static pages, beach/region translations, media | agent prepares (seeder/command), owner runs on the server |
| **Decision** | what a page or link should do, or whether it should exist at launch | owner |
| **Already fixed** | fixed on `dev`, QA row only needs retest | none |

The audit is shown to the owner **before any fix**. The owner sets the order and decides the Decision rows.

### Step 3 — Footer static pages (backend data)

- The footer info column shows `static_pages` with `is_in_footer = true` (`GET /api/v1/static-pages/footer`), and
  `/[locale]/p/[slug]` renders one page. `StaticPageSeeder` seeds EN placeholders only
  (about-us, privacy-policy, terms-of-use, contribution-rules, …).
- Deliverable: real content for every footer page in **all 19 locales** (title, localized slug, content HTML,
  meta title, meta description), versioned in the repo and loaded by an idempotent artisan command with
  `--dry-run` (updates by `code` + `locale`; it never deletes a page and never touches pages it does not know).
- **Owner input (2026-10-08):** the operator and data controller is MOJA PLAŽA, d.o.o., Prušnikova ulica 22,
  2000 Maribor, Slovenia, contact `info@beachrex.com` (tax and registration numbers given by the owner).
  External services: Google sign-in, Google Analytics; newsletter sign-ups are stored in the Beachrex DB;
  cookie consent via CookieYes. The code also uses Stripe (payments) and OpenAI (AI features); the drafts
  list them only if they are active at launch (agent verifies in code/env names, owner confirms).
  The agent drafts; legal texts are the owner's responsibility (ideally reviewed by a lawyer).
- Slugs are localized but stay ASCII and unique per locale (TASK-002 rule).
- Run order: dev (owner runs the command) → owner checks on dev → later the same command on uat/prod.

### Step 4 — `/[locale]/beaches` (QA #48)

- Today the page renders mock data (`mockCountries`, "Plaža Ladinka" ×4) and has no media switch.
- Make it consistent with the continent/country pages (same banner, breadcrumbs, cards, `PropertyCard` media
  rules: image / video / sphere / webcam) and fill it from the API: continents with country counts, most visited
  beaches, countries list. Reuse existing endpoints first; a new backend endpoint only if none fits (then OpenAPI +
  `l5-swagger:generate` + `yarn generate:api`).
- Render server-side (overlaps SEO step 5). Regression test: no mock names in the rendered output.
- After deploy: tracker row #48 → Retest.

### Step 5 — SEO and AI search (HANDOFF work order item 6)

- SSR for `/beaches` and `/beach-map` (content and metadata in the initial HTML).
- `about` metadata; every page has a unique title/description, canonical and `hreflang` for 19 locales.
- Exactly one `<h1>` per page; meaningful `alt` texts on images.
- Sitemaps (`/sitemap/*.xml`) and `robots`: indexable only on `beachrex.com` (`isProductionSite()`), `noindex`
  on dev/uat. Structured data (JSON-LD) checked where it exists.
- **AI search engines (owner, 2026-10-08):** content readable without JavaScript (SSR), JSON-LD
  (`Organization`, `WebSite` + `SearchAction`, `Beach`/`TouristAttraction`, `BreadcrumbList`), `llms.txt`,
  a `robots` policy for AI crawlers (owner decides: allow search/answer bots such as `OAI-SearchBot`,
  `PerplexityBot`, `Claude-SearchBot`; training bots such as `GPTBot`, `ClaudeBot`, `Google-Extended` separately).
- **Analytics and consent:** Google Analytics (script from the owner) loads only after consent through CookieYes
  (Google Consent Mode v2, default denied). IDs go in `NEXT_PUBLIC_*` env vars (not secrets; baked in at build),
  named in `.env.example` and in the runbook env list; prod only unless the owner wants them on dev.
- Unit tests (`e2e/*.unit.spec.ts`) for the helpers.

### Live QA

The sandbox cannot reach `*.beachrex.com` (network policy). Live QA therefore runs with Playwright against a
**local stack**: the backend on local PostgreSQL + PostGIS with seeded data, and `next start` pointed at it.
This catches crashes, empty pages, dead links, missing strings and SSR/SEO output; data-specific checks on dev
stay an owner action and are marked `NOT RUN` in the audit.

### Step 6 — Prod launch runbook review (`docs/runbooks/prod-launch.md`)

- Check every step against the current code, compose, nginx, deploy workflow and env names
  (`.env.example`, `deploy.yml`): missing variables (e.g. `OPEN_METEO_API_KEY`, `BACKUP_*`), MCP prod-only
  switch, cache/search index steps, DB from UAT, rollback.
- Output: a docs PR with corrections and a short go/no-go checklist for the owner.

## Must not touch

- uat/prod branches and servers; the VPS by hand; secrets.
- `src/lib/api-client/` by hand (generated).
- Beach/region data cleanup (names with "Beach …", missing region translations, descriptions) — listed in the
  audit as **Data**, scheduled separately unless the owner pulls it into this task.

## Tests and proof

- Frontend before every PR: `npx tsc --noEmit -p .`, `yarn lint`,
  `BASE_URL=http://localhost:9 npx playwright test e2e/*.unit.spec.ts --project=chromium`.
- Backend: `php artisan test` (only the known env-dependent failures allowed); new command has tests
  (dry run changes nothing, real run is idempotent, unknown pages untouched).
- A locale-completeness check runs in the frontend unit tests, so a missing key in any of the 19 files fails CI.
- Real outputs go in `docs/tasks/TASK-004.feedback.md` and the PRs.

## Acceptance criteria

1. Audit list exists, every row has a category and verdict, the owner has decided the Decision rows.
2. No menu/footer/home link returns 404 or an empty page on dev (or the link is removed by owner decision).
3. No mock/test data reachable from any route.
4. All 19 `messages/*.json` have the same keys as `en.json`; the check is in CI.
5. Every footer page has content in 19 locales on dev (legal pages only with the owner's data).
6. `/beaches` uses real API data and the shared design; QA #48 in Retest.
7. SEO item 6 done; dev stays `noindex`.
8. Runbook reviewed and corrected; HANDOFF updated with the go/no-go list.

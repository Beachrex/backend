# TASK-011 — Premium trip planner: researched, verified, designed travel plans

**Status:** Proposed — waiting for owner approval (spec only, no code yet).
**Owner request (2026-10-30, paraphrased from Slovenian):** a plan that takes a few minutes is fine — the customer
enters an e-mail or is signed in, and we e-mail a link when it is ready. The plan must be extremely detailed, better
than a travel agency's guided plan, worth a report costing thousands of euros. Gemini and GPT-6 Astra do web research
and descriptions; Claude Opus 5.5 writes it all and makes it visually attractive (PDF). Optional points of interest
tell us what to research; constraints: disability/accessibility, vegan diet, small children, dog, and more.

## Decisions at a glance

| # | Decision | Status |
|---|---|---|
| D1 | Research: **Gemini (Google Search grounding) + GPT-6 Astra (Responses API web search) in parallel**; **Claude Opus 5.5 verifies, merges and writes** | Owner, 2026-10-30 |
| D2 | Prices stay **$19 / $39 / $59** (3/7/14 days) for now; this spec shows our cost per plan (§4) | Owner, 2026-10-30 |
| D3 | Delivery: immediate "we're preparing it" screen + e-mail, then **e-mail with a link** when ready | Owner, 2026-10-30 |
| D4 | Output: **web plan page** (MVP, with browser "Save as PDF") → **designed server PDF** (M2) | Owner asked; staging proposed |
| D5 | The premium pipeline **replaces** the paid 3/7/14 flow (same Stripe products and credits); the current planner stays as the beach-selection step and as a fallback | Proposed |
| D6 | Beaches come from **our database only** (code picks and clusters them, ADR 2026-10-07); the web research adds everything around them | Proposed |
| D7 | No place without proof: every restaurant/sight/activity needs a source URL that resolved, or a Beachrex record | Proposed |
| D8 | Paid plans get their **own daily budget**, outside the $20 emergency cap; already-paid plans always finish | Proposed (§4.4) |
| D9 | Diet/mobility/allergy answers are **health data** (GDPR Art. 9): chips + explicit consent, no names/e-mails in prompts | Proposed (§6) |
| D10 | Build in stages: **M0 prototype + 10-brief evaluation → owner sign-off → M1 MVP → M2 PDF + guest checkout → M3 polish** | Proposed (§8) |

Prices in this document were checked on 2026-10-30 (sources in §4.1). **All token counts, run times and costs per
plan are estimates** until M0 measures them on the 10 evaluation briefs.

---

## 1. User flow

### 1.1 What exists (reused)
- Web: `/{locale}/ai/itinerary` (country/region picker from the API, 3/7/14 with real prices, interests, pace,
  terms + withdrawal-waiver checkboxes), `/{locale}/ai/itinerary/plans` ("My plans"), `/plans/{id}` (`ItineraryPlanView`,
  Leaflet map, `window.print()` button).
- API: `POST /ai/itinerary-plans` (reserves a credit, queues `GenerateItineraryPlan` on the `itineraries` queue),
  `GET /me/itinerary-plans[/{id}]`, `GET /payments/products`, `POST /payments/stripe/checkout`, Stripe webhook,
  `EntitlementService` credits, `ItineraryPlanService` (credit back exactly once), `AiSpendMeter`, `ModelPricing`,
  `AiModelCatalog` + admin "AI models" page, trip-planner on/off switch (`ai_trip_planner_enabled`).
- Mail: `ResetPasswordNotification` / `PreviewAccessLinkNotification` pattern (Blade HTML + text view, `lang/*.json`
  in 19 languages, SMTP `hello@beachrex.com`).

### 1.2 Questionnaire (one page, 5 short steps, progress bar; everything optional except step 1)
1. **Where and when** — country → one or more regions/islands (existing picker), *or* "plan around my favourite
   beaches" (from `/me/favorites`); start date or "flexible: month"; length 3/7/14; arrival point (airport/town, free
   text ≤ 80 chars); base: one base / move around / already booked in *town* (town only, never an address).
2. **Who** — adults; children with **ages** (0–17, one number each); dog (small/medium/large) — no names.
3. **Interests** (optional chips, max 10; tell research where to dig): quiet beaches, family beaches, sandy beaches,
   snorkelling, diving, kayaking/SUP, surfing/wind/kite, boat trips & islands, hiking, cycling, nature & parks,
   culture & history, museums, food & wine, local markets, nightlife, photography spots, family activities,
   wellness/spa, festivals & events, shopping. Free text "must see / avoid" ≤ 300 chars (warning: no personal data).
4. **Needs** (optional chips): mobility — limited mobility / wheelchair / pushchair; diet — vegetarian, vegan,
   pescatarian, gluten-free (coeliac), lactose-free, halal, kosher; allergies — nuts, peanuts, shellfish, fish, egg,
   soy, sesame (+ "other", ≤ 100 chars); small children (derived from ages); dog-friendly (derived from step 2);
   avoid long drives; no ferries; fear of deep water / non-swimmers. A one-line **explicit consent** checkbox appears
   only when a health-related chip is chosen (§6).
5. **Style** — budget (budget / mid / premium / luxury); transport (own car / rental car / public transport / no car);
   pace (relaxed / balanced / packed, existing); accommodation preference (camping / apartment / hotel / boutique /
   resort); food style (local konobas / fine dining / mixed).

Validation extends `ItineraryRequest` **additively** (new optional keys; old clients keep working — API rule
2026-10-09). Enum lists live in one PHP class and are served to clients by a new `GET /ai/trip-planner/options`
(localized labels), so web and mobile never hard-code them.

### 1.3 Account, payment, start
- **M1:** sign-in required (existing e-mail/password and Google). Inline sign-in on the last step, the form is kept
  (existing `saveFormForCheckout`).
- **Draft first, then pay:** `POST /ai/itinerary-plans` stores the brief as a plan with status `awaiting_payment`
  when there is no credit and returns 402 + `plan_id`. Checkout puts `plan_id` into the Stripe session metadata;
  the webhook grants the credit **and** consumes it for that plan and queues it in one transaction. The customer can
  close the tab after paying — the plan starts anyway. (With a credit already in hand, the plan is queued directly,
  as today.)
- **M2 — guest checkout (owner decision, §8.3):** e-mail is collected by Stripe Checkout; the webhook creates (or
  finds) a passwordless account for that e-mail, so credits, "My plans" and ownership work unchanged; the ready
  e-mail carries a plan link with a secret token and a "set your password" link (existing reset flow).
- **Confirmation screen** right after the plan is queued: "We are researching and writing your plan. This takes about
  10–15 minutes. We will e-mail you at d…@… when it is ready — you can close this page." + live progress.
- **Immediate e-mail** "Your plan is being prepared" (summary of the brief, link to the status page).
- **Status page** = the plan page while it is not done: progress steps (Understanding your trip → Our beach data →
  Researching the web (sources found: N) → Checking facts → Writing day 3 of 7 → Final checks → Ready), polled every
  10 s from `GET /me/itinerary-plans/{id}` (new `progress` object). No token streaming.
- **Ready e-mail**: title, dates, hero photo (our beach), 3-line summary, button "Open your plan", (M2) "Download PDF".
- **Failure e-mail**: "We could not finish your plan; your credit is back" + retry button.

---

## 2. Generation pipeline

### 2.1 Shape
Queued, multi-stage, **resumable and idempotent**: every unit of work is a row in a new `itinerary_plan_steps`
table (`plan_id`, `step_key` e.g. `research:gemini:topic-04`, `status`, `attempts`, `input_hash`, `output` JSON,
`sources`, tokens, `cost_usd`, `model`, `error`, timings). A stage job skips steps already `done` with the same
`input_hash`, so a crashed or redeployed worker resumes instead of paying twice. Stages are chained jobs
(`Bus::chain`) on a new `trip-premium` queue; parallelism inside a stage comes from concurrent HTTP
(`Http::pool`, max 6 in flight), not from more PHP processes.

| # | Stage | Who | Output |
|---|---|---|---|
| S0 | **Brief → research plan** | Opus 5.5 (effort low) | normalized brief (no personal identifiers), list of research topics: per area/day cluster × interests/needs (e.g. "vegan & gluten-free eating in Bol and Supetar", "step-free sights in Split old town", "dog rules on Brač beaches and ferries in June") |
| S1 | **Our data first** | code | beaches per day from the existing `ItineraryPlanner` (candidates by attributes, k-means days, nearest-neighbour order, haversine); per beach: facilities, `access_for_disabled`, accessibility easy/medium/difficult, `dog_friendly`, inclination/`sudden_drop`, swimming depth, lifeguard, parking + capacity, shade, food on site, photos (Imagor), page URL; month climatology (air temperature, rain days and sea temperature as a 10-year mean for the month and place, from Open-Meteo historical and Marine data; today we only call OpenWeather current weather and Open-Meteo Marine); webcams (TASK-006) where present; curated country facts (emergency 112 + local numbers, vignette/tolls, currency, plug type) from a **code table**, never from a model |
| S2a | **Research A** | Gemini (Google Search grounding), one call per topic | structured JSON: places (name, type, town, address, what/why, price level, opening info, diet/accessibility/kid/dog evidence), practical facts, each item with `source_urls` |
| S2b | **Research B** | GPT-6 Astra, Responses API + `web_search` tool, one call per topic, reasoning effort medium | same JSON schema, URLs from `url_citation` annotations |
| S3 | **Verify & merge** | code + Opus 5.5 (+ `web_fetch` spot checks) | one fact sheet: entities matched across sources (name + town + geocode), claims labelled `confirmed` (2 independent sources or Beachrex record), `single-source`, `conflict` (resolved by fetching the page, else dropped) or `dropped`; time-sensitive facts (hours, prices, ferry times, event dates) tagged **"check before visiting"** with the source date |
| S4 | **Compose** | Opus 5.5, one call per day (parallel, shared cached prefix) + one overview call | day-by-day plan (§3) in the plan language, only from the fact sheet and S1 data |
| S5 | **Quality gate** | code checks + Opus reviewer call | pass, or one repair round per failing day; result and score stored |
| S6 | **Render & notify** | code (M2: PDF job) | plan `done`, ready e-mail, (M2) PDF in private storage |

S2a and S2b run **at the same time**. All stages run in the worker; the API request only validates and queues.

### 2.2 Prompts and data hygiene
- Prompts get the normalized brief only: places, dates/month, party composition (ages, dog size), chips. **Never**
  names, e-mail, user id or free-text allergy wording beyond the 100-char field (which is checked by code for e-mail
  addresses/phone numbers and stripped).
- Research prompts ask for **verifiable, specific** items (names, addresses, which day it is closed, what a vegan
  guest can order there) and forbid guessing; an item without a URL is discarded by code.
- Gemini grounding returns `groundingChunks` with Google redirect URLs: the worker resolves each once (HEAD/GET,
  5 s, max 3 redirects) to the final URL and stores domain + title. OpenAI `url_citation` URLs are used as given.
  All URLs are checked for HTTP 2xx/3xx before S4; dead links are dropped (and their claims, unless another source
  backs them).
- Geocoding of places for maps: Nominatim (OSM) at ≤ 1 request/s with our user agent and attribution, cached per
  place; places that do not geocode are shown without a pin. (Google Maps grounding is not used: its content may not
  be shown on non-Google maps.)
- Structured outputs: Anthropic `output_format` JSON schema, OpenAI `text.format` json_schema, Gemini
  `responseSchema`. **M0 verifies** that Gemini's structured output works together with Search grounding on the
  chosen model; if not, JSON-in-prompt + our validator + one re-ask.

### 2.3 Quality gate (S5) — code checks first, then the reviewer
Hard checks (a failure triggers a repair of that day; a second failure removes the item, never ships it):
1. Schema valid; every day has morning/afternoon/evening, a bad-weather alternative and travel legs.
2. **No hallucinated places:** every named place has ≥ 1 resolved source or a Beachrex id.
3. **Diet:** with vegan/vegetarian/gluten-free/halal/kosher, every eating place has explicit source evidence for that
   need ("vegan dishes on the menu" etc.); a vegan brief never gets a place known only for meat/fish. Allergies →
   every food section carries "always tell the staff about your allergy".
4. **Mobility:** with wheelchair/limited mobility/pushchair, every beach is `access_for_disabled` or accessibility
   `easy` in our data, else it is replaced or shown with an explicit "not verified step-free" note and an
   alternative. No "accessible" claim without a source.
5. **Small children** (< 4): no `sudden_drop` beach without a warning, shade/WC noted, max driving per day by pace,
   no hikes over the limit set in code.
6. **Dog:** beaches must be `dog_friendly` in our data or have a source; dog rules on ferries/restaurants cited.
7. Travel time per day within the pace limit (code estimate: haversine × road factor ÷ average speed, labelled
   "approx."; ferry times from sources).
8. Dates: no visit on a day a place is known closed; season fit (e.g. ferries/boat trips in the month).
9. Language = plan locale; no other-language paragraphs (simple detector).
10. Budget numbers are ranges and labelled estimates.
Then the **Opus reviewer** scores the plan on the rubric (§7) without seeing the drafts' reasoning; below threshold
→ one repair round; still below → plan is delivered but flagged for admin review (or held, if the admin setting
"review before sending" is on — useful for the first weeks after launch).

### 2.4 Timeouts, retries, partial failure, refunds
- Per call timeouts: Gemini 180 s, Astra 300 s, Opus 180 s; 2 attempts with backoff for 429/5xx/timeouts.
- **Research A or B down** → continue with the other (`metadata.research_degraded = true`, recorded in admin).
  **Both down** → retry the research stage every 5 min for up to 30 min; then fail.
- **Writer down** → retry; then the fallback writer from the model picker (§5); then fail.
- **Max runtime 40 min** per plan (new `STALE_RUNNING_MINUTES` per kind; the existing 10 min rule stays for the
  classic planner). Expected: **~8–15 min** (estimate; S2 is the long pole, 2–6 min).
- If the plan is not done after 20 min, a short "taking a bit longer" line appears on the status page (no extra mail).
- **Credits:** reuse `ItineraryPlanService` — the credit goes back exactly once on failure/stale; the customer gets the
  failure e-mail. A plan that passes the gate with degraded research is a normal paid plan. Money refunds stay
  manual in Stripe (existing webhook revokes the credit).
- **Cost guard per plan:** `AiSpendMeter` total for the plan; above `AI_TRIP_PLAN_MAX_COST` (proposal $30) the
  pipeline stops new research calls and composes from what it has.

### 2.5 Where it runs
- New queue connection `trip-premium` (database driver, `retry_after` > job timeout) and **one more worker process
  per environment** (`queue:work trip-premium --timeout=900 --tries=1`, same api image — like `worker_<env>`), via
  the deploy compose (no manual VPS work). One premium plan at a time per environment is enough at launch (each
  plan already runs its calls concurrently); a second process later if plans queue up.
- Scheduler: the existing stale check covers the new kind.

---

## 3. Output

### 3.1 Web plan page (`/{locale}/ai/itinerary/plans/{id}`, same route, new layout for `kind=premium`)
1. **Cover** — title, dates, party icons (adults, kids' ages, dog), hero photo (our beach), 3-line summary.
2. **At a glance** — overview map of all days (Leaflet, existing component), route, numbers (beaches, km, sea
   temperature for the month), budget range.
3. **Before you go** — weather & sea temperature for the month (our data), tailored packing list, money/vignettes/
   ferries to book, and only-when-relevant briefings: accessibility, diet & allergies, travelling with small
   children, with a dog.
4. **Day by day** — per day: map with pins; morning/afternoon/evening with times; beach cards (our photos, facts,
   link to the beach page, webcam if any); activities and sights; where to eat (diet-matched, price level, source);
   travel legs (approx. time/km); bad-weather alternative; kid/dog/accessibility notes; day budget; "check before
   visiting" badges.
5. **Food guide** by area (only verified places).
6. **Budget** table (ranges, labelled estimates).
7. **Local tips** — etiquette, useful phrases in the local language, best times to avoid crowds.
8. **Emergency & practical** — 112 and local numbers (code table), nearest hospital/pharmacy (+ vet with a dog) with
   sources.
9. **Sources** — numbered list with domain and access date; claims in the text link to their numbers.
10. **Disclaimers** (§6.4).

Images: **our beach photos only** (Imagor, existing URL helpers). No images from Google, OpenAI/Gemini answers or
scraped sites (licensing). Optional M3: Wikimedia Commons photos for sights, only files with a free licence, with
author + licence + link shown under the image (Commons API `extmetadata`).

### 3.2 PDF
- **MVP:** a print stylesheet for the premium page (A4, page break per day, maps and photos sized for print, sources
  as footnotes) + the existing "Print / Save as PDF" button. Zero new infrastructure.
- **M2 — designed server PDF:** a **Gotenberg** container (Chromium HTML→PDF over HTTP; internal network only, one
  shared instance, memory limit ~1 GB, one render at a time) renders a dedicated print route
  `/{locale}/ai/itinerary/plans/{id}/print?pt=<print token>` of the website (internal address, not through
  Cloudflare). The print token is a 5-minute HMAC token accepted only by that route. Cover page, table of contents,
  page numbers, embedded Figtree, static day maps. Chosen over Browsershot/Puppeteer in the PHP image (would add
  Node + Chromium, ~400 MB, to every api/worker container) and over a second Blade template (two layouts to keep in
  sync). Static map images: OSM's public tile server forbids bulk/automated use, so the PDF uses a tile provider with
  an API key and an allowed free/paid tier (e.g. MapTiler or Stadia Maps — owner picks, §8.3); attribution printed.
- Storage: a **private** Spaces bucket (the current `spaces` disk is public) — new disk `private`; key
  `trip-plans/{plan_id}/{template_version}.pdf`. Download: `GET /me/itinerary-plans/{id}/pdf` → ownership check →
  302 to a presigned URL valid 10 min. Regenerated lazily when the template version changes or the file is missing.
  Target size < 15 MB.

### 3.3 E-mails
Three notifications (prepared, ready, failed), Blade HTML + text like the password-reset mail, strings in all 19
`lang/*.json`, language = plan locale (website URL locale `si` for `sl`), Beachrex logo, plain layout that survives
Outlook. Sent from the worker after the state change (not in a DB transaction). Deliverability (SPF/DKIM/DMARC for
beachrex.com on `mail.datavallis.com`) is an owner check.

### 3.4 Link security and retention
- M1: the e-mail link opens the plan page, which requires sign-in and ownership (existing `GET /me/itinerary-plans/{id}`).
- M2 (guest/sharing): optional secret link `?k=<token>` — 32 random bytes, only the SHA-256 stored, read-only access
  to that one plan, revocable ("reset share link" in My plans), `noindex`, `Referrer-Policy: no-referrer`.
- Retention: plans stay in "My plans" until the user deletes them (new `DELETE /me/itinerary-plans/{id}`) or the
  account. Raw research outputs (`itinerary_plan_steps.output`) are deleted 90 days after delivery (admin review
  window); the final plan keeps its sources. Health-related brief fields: see §6.

---

## 4. Costs

### 4.1 Provider prices (checked 2026-10-30)
| Item | Price | Source |
|---|---|---|
| Claude Opus 5.5 | $4 in / $20 out per MTok; cache read $0.20; batch −50% | [Anthropic pricing](https://platform.claude.com/docs/en/about-claude/pricing) |
| Claude web search / web fetch | $10 per 1,000 searches / no extra fee (tokens only) | same |
| GPT-6 Astra (standard) | $10 in / $1 cached / $50 out per MTok (flex/batch $5/$25) | [OpenAI pricing](https://developers.openai.com/api/docs/pricing) |
| OpenAI web search tool | $10 per 1,000 calls **+ search content tokens at model rates** (reasoning models) | same |
| Gemini 3.1 Pro Preview | $2 in / $12 out per MTok (≤ 200K); $4 / $18 above | [Google Cloud generative AI pricing](https://cloud.google.com/gemini-enterprise-agent-platform/generative-ai/pricing) |
| Gemini 4 Argon | $2 / $10 promo until 2026-11-30, then $4 / $20 | same |
| Grounding with Google Search (Gemini 3/4) | 5,000 queries/month free, then **$14 per 1,000 queries** (each query billed); grounding input tokens free | same; [eesel.ai summary, Jun 2026](https://eesel.ai/blog/google-gemini-3-pricing) |
| Stripe (EEA cards) | ~1.5% + €0.25; non-EEA ~3.25% + €0.25 (+2% conversion); Stripe Tax ~0.5% | stripe.com/pricing — **not reachable from the sandbox, verify** |

The ai.google.dev pricing page was not reachable from the sandbox; the figures above are Google Cloud's list for
the same models. **M0 must confirm the Gemini API (AI Studio key) prices and that the key is on the paid tier**
(free-tier data may be used by Google to improve its products).

### 4.2 Estimated cost per plan (default config: Gemini 3.1 Pro + GPT-6 Astra + Opus 5.5)
Assumptions (estimates): research topics 5 / 8 / 13; Gemini per topic 6K in + 6K out (incl. thinking) + 6 search
queries; Astra per topic 35K in (search content counts as input) + 7K out + 8 searches; Opus verify ≈ 7K × topics +
2.5K × days in, plus 2 `web_fetch` checks per topic; compose per day 8K fresh + 12K cached in, 7K out; reviewer +
15% of days repaired.

| Stage | 3 days | 7 days | 14 days |
|---|---|---|---|
| S0 brief + research plan (Opus) | $0.13 | $0.13 | $0.13 |
| S2a Gemini tokens | $0.42 | $0.67 | $1.09 |
| S2a grounding queries (after free 5,000/month) | $0.42 | $0.67 | $1.09 |
| S2b GPT-6 Astra tokens | $3.50 | $5.60 | $9.10 |
| S2b web search calls | $0.40 | $0.64 | $1.04 |
| S3 verify & merge (Opus) | $0.72 | $1.09 | $1.71 |
| S4 compose (Opus) | $0.78 | $1.48 | $2.70 |
| S5 review + repairs (Opus) | $0.25 | $0.46 | $0.81 |
| **Total (estimate)** | **≈ $6.6** (range $4–10) | **≈ $10.8** (range $6–16) | **≈ $17.7** (range $11–27) |
| Without GPT-6 Astra (Gemini + Opus only) | ≈ $2.7 | ≈ $4.5 | ≈ $7.6 |

GPT-6 Astra is ~55% of the cost (search pages are billed as input at $10/MTok). M0 measures its **marginal value**:
the share of verified facts in the final plan that only Astra found. If it is small, the owner can switch research B
off in the model picker. PDF rendering, e-mail, geocoding and storage are cents.

### 4.3 Against the price (estimate, per plan)
| | 3 days $19 | 7 days $39 | 14 days $59 |
|---|---|---|---|
| Net of VAT, if the price includes 22% VAT | $15.57 | $31.97 | $48.36 |
| Stripe (EEA card, approx.) | −$0.56 | −$0.86 | −$1.16 |
| AI cost (estimate, range) | −$6.6 (4–10) | −$10.8 (6–16) | −$17.7 (11–27) |
| **Left per plan** | **≈ $8** ($5–11) | **≈ $20** ($15–25) | **≈ $30** ($21–37) |

The 3-day plan carries the thinnest margin; it researches fewer topics by design. Failed plans (credit returned)
cost us their spend so far — a reason for the per-plan cost guard (§2.4).

### 4.4 Daily cost cap (ADR 2026-10-29) — proposal
Today paid planner spend counts toward the **$20 emergency limit**, so two 14-day plans could switch off all AI
(chat included) for the rest of the day, and a paid plan could be stopped half-way. Proposal:
1. Premium plans record their real cost (tokens **plus tool fees**: grounding queries, web search calls) under a new
   `event_type=trip_premium` and count toward a new **`AI_PAID_DAILY_LIMIT`** (proposal **$300**/day ≈ 15–30 plans),
   not toward the $20 emergency bucket. `ModelPricing` gets the Gemini models and per-call tool fees.
2. The limit is checked **before payment**: when it is reached, checkout answers "fully booked today, try tomorrow"
   (503 `daily_limit` with `retry_after`). A plan already paid **always runs** (if a provider outage, not the cap,
   stops it, the normal failure/credit rules apply).
3. The per-plan cap `AI_TRIP_PLAN_MAX_COST` stops a runaway single plan.
4. The $20 emergency limit keeps protecting the free chat and everything unpaid.

---

## 5. Model configuration
- Extend the admin page **AI → AI models** (ADR 2026-10-30) with three selects, stored as site settings (idempotent
  migration, NULL = config default):
  - `ai_trip_research_a_model` — Gemini 3.1 Pro (default), Gemini 4 Argon, a Gemini Flash model, or **off**;
  - `ai_trip_research_b_model` — GPT-6 Astra (default) or **off** (at least one research model must stay on);
  - `ai_trip_writer_model` — Claude Opus 5.5 (default), Claude Sonnet 5.5; fallback writer `ai_trip_writer_fallback`
    (GPT-6 Astra or Sonnet 5.5).
- `AiModelCatalog` gets a `gemini` provider and per-use flags (`trip_research_a`, `trip_research_b`, `trip_writer`);
  a model is selectable only when its provider key exists in that environment (existing rule). The estimate table on
  the page shows cost per 3/7/14 plan from §4.2's token profile, later from measured `ai_usage` averages.
- New env vars (names only, values set by the owner in `/opt/beachrex/envs/.env.<env>`): **`GEMINI_API_KEY`**,
  `AI_PAID_DAILY_LIMIT`, `AI_TRIP_PLAN_MAX_COST`, `AI_TRIP_PREMIUM_ENABLED` (plus site setting), (M2)
  `GOTENBERG_URL`, `PRIVATE_SPACES_*` bucket vars, `STATIC_MAP_API_KEY`. Existing: `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`.
- Client code: Gemini via REST with Laravel `Http` (no official PHP SDK); OpenAI Responses API via `openai-php`
  if its version supports the `web_search` tool, else `Http`; Anthropic via the installed SDK.
- "Test" buttons (existing pattern) for each new select: one fixed research question, shows answer, sources, time, cost.

---

## 6. Privacy and legal

### 6.1 Data collected
Trip brief: destination, dates, party size, **children's ages**, dog size, interests, budget/transport/style;
**health data** (GDPR Art. 9): mobility/wheelchair, gluten-free (coeliac), allergies; **possibly religious data**:
halal/kosher. Vegan/vegetarian is not special-category but is kept with the same care. Account e-mail (existing).

### 6.2 Legal basis and minimisation
- The plan itself: contract (Art. 6(1)(b)). Health/religion-related chips: **explicit consent** (Art. 9(2)(a)) — a
  checkbox shown only when such a chip is chosen, with one sentence on why and who processes it; without consent
  those chips are cleared and the plan is made without them.
- Chips instead of free text wherever possible; the "other allergy" (100 chars) and "must see / avoid" (300 chars)
  fields warn "no names or personal details" and are scrubbed by code for e-mails/phone numbers.
- Prompts to Google, OpenAI and Anthropic contain the normalized brief only — no name, e-mail, user id or IP. Abstract
  phrasing ("travel party needs step-free access", "one child aged 2").
- Retention proposal: the health/religion fields of the stored brief are removed **30 days after the trip's end date**
  (or after delivery when no date was given); the plan text that already reflects them stays until the user deletes
  the plan or the account. Research raw outputs: 90 days (§3.4).

### 6.3 Providers and policy text
- Processors to name in the privacy policy (19 languages, `database/content/static-pages/*.json`, owner runs
  `static-pages:sync`): **Google (Gemini API)**, **OpenAI**, **Anthropic** for the trip planner; DigitalOcean (Spaces)
  for the PDF; Stripe (existing). Data processing terms of each API (no training on API data on paid tiers; their
  retention for abuse monitoring) — the owner checks/accepts each DPA; the Gemini key must be on a **paid** project.
- Transfers outside the EU: covered by each provider's SCCs/DPF — to be stated in the policy.

### 6.4 Disclaimers (on the page, in the PDF, in the terms of sale)
Beachrex is **not a travel agency** and books nothing (no package travel); information comes from our database and
public web sources and may change — opening hours, prices, ferry times and rules are marked "check before visiting";
accessibility and diet information must be confirmed with the venue; with allergies always tell the staff; in an
emergency call 112. The terms of sale (AI-4) get one paragraph on the premium plan and its delivery time.

The legal wording is written by the agent and should be reviewed by someone qualified (as for the chat policy).

---

## 7. Quality bar and evaluation

### 7.1 Rubric (1–5 each; reviewer = owner + Opus reviewer; facts checked by a human)
Accuracy (sample 20 claims per plan, open the sources) · constraint compliance (pass/fail, §2.3) · specificity (named
places, times, what to order) · logistics realism (travel times, opening days, ferries) · personalization to
interests · local insight beyond page-one search results · usefulness of practical sections · presentation ·
language quality in the plan language · honesty (labels for estimates and "check before visiting").

### 7.2 Ten evaluation briefs (M0)
1. Brač + Split, 7 days, June: **wheelchair user + vegan + toddler (2) + medium dog**, own car (the hard case).
2. Istria (HR) + Slovenian coast, 3 days, September, couple, food & wine, cycling, no car.
3. Kvarner islands, 14 days, July, family 2+2 (6, 10), snorkelling, boat trips, budget, ferries.
4. Algarve (PT), 7 days, May, gluten-free (coeliac) + surfing + photography, rental car.
5. Sardinia/Italy region in our DB, 7 days, August, luxury, nightlife + beaches, no kids.
6. Crete/Greek region in our DB, 14 days, October, retired couple, limited mobility, culture & history, public transport.
7. Montenegro coast, 3 days, June, friends 4 adults, nightlife, kayaking, mid budget.
8. Albanian Riviera, 7 days, July, family with baby (0) and pushchair, halal, quiet beaches.
9. Costa Brava (ES), 7 days, September, two dogs (large), hiking + beaches, own car.
10. Danish/Dutch coast, 3 days, August, family 2+1 (8), nature, rainy-weather alternatives, lactose-free.
(Exact regions are picked from our database in M0 so every brief has enough beaches; the language of the plans
varies: en, sl, de, hr, it, fr.)

### 7.3 Proof
- Admin **AI → Trip plans** (Filament, read-only): brief, status per stage, time and cost per stage, sources count,
  claims confirmed/single/dropped, gate results, reviewer score, rendered plan, raw research per provider (for the
  owner), "re-run stage" (admin, dev only) and "send to me" buttons.
- **Side by side** for each evaluation brief: our plan vs the same brief given to consumer ChatGPT and Gemini (copied
  in by the owner) and, if the owner can get one, a real agency itinerary.
- **Sign-off bar** (proposal): average ≥ 4.0, no hard-constraint failure, ≥ 95% of sampled claims verified, no dead
  source links, median run time ≤ 15 min, measured cost within §4.2 ranges. Owner sign-off before enabling on prod
  (`AI_TRIP_PREMIUM_ENABLED`, per environment).

---

## 8. Stages, effort, risks

### 8.1 Milestones (effort = agent working days, rough)
| Stage | Scope | Effort |
|---|---|---|
| **M0 — prototype + evaluation** | `GEMINI_API_KEY`; provider clients (Gemini REST, Responses API web search, Opus structured output); `trip:premium-run {brief.json}` artisan command running S0–S5 without UI, writing JSON + a simple HTML preview; real cost and time per stage; 10 briefs; admin read-only page; owner review | 3–4 |
| **M1 — MVP** | questionnaire v2 (19 languages) + options endpoint; draft-then-pay flow; pipeline as queued resumable stages + steps table; worker process; cost cap changes; status/progress; 3 e-mails; premium web page + print stylesheet; model picker selects; privacy texts; admin guide (Slovenian); tests (auth 401/403, idempotent resume, credit-back once, gate checks with fake providers, deliberate breaks) | 8–10 |
| **M2 — PDF + guest** | Gotenberg container + print route + private bucket + static maps; PDF in ready e-mail and My plans; guest checkout (auto-account from Stripe e-mail) + secret share link | 4–6 |
| **M3 — polish** | Wikimedia photos with attribution; "redo this day"; calendar export (.ics); real routing times (API decision); mobile API extras; retention jobs; more languages QA | 3–5 |

### 8.2 Reused vs new
**Reused:** `ItineraryPlanner` + `ItineraryContextBuilderService` (beaches/days), `itinerary_plans` (+ columns `kind`,
`progress`, `cost_usd`, `pdf_path`, `share_token_hash`, `emailed_at`), `ItineraryPlanService` credit rules,
`EntitlementService`, Stripe checkout/webhook, `AiSpendMeter`/`ModelPricing`/`AiModelCatalog`, admin AI models page,
trip-planner switch, notification + Blade mail pattern, Imagor/URL helpers, Leaflet map, Open-Meteo sea temperature.
**New:** steps table + stage jobs, three provider clients with web search, fact-sheet merge/verification, quality
gate, geocoding cache, premium page layout, e-mails, paid daily budget, (M2) Gotenberg, private disk, print route.

### 8.3 Open questions for the owner
1. **Guest checkout** in M2 (auto-account from the Stripe e-mail) — yes/no? Or sign-in always?
2. **Price** after M0 costs are measured (and whether prices include VAT); keep 3-day at $19?
3. **GPT-6 Astra on or off by default** after M0's marginal-value measurement (≈ 55% of cost).
4. Paid daily budget amount (proposal $300) and per-plan cap ($30).
5. **Review before sending** for the first N plans after launch — yes/no?
6. Refund policy when a customer is unhappy with a delivered plan (today: manual Stripe refund revokes the credit).
7. Static map tile provider for the PDF (paid key) and whether to pay for a routing API for real driving times.
8. Open-Meteo commercial plan (`OPEN_METEO_API_KEY`) — climatology for a paid product needs it.
9. Plan languages: all 19 from day one (proposal: yes, Opus writes all 19; M0 checks sl/hr/de/it quality).
10. Retention of health fields (30 days after the trip) and research outputs (90 days).

### 8.4 Risks
- Provider rate limits (Gemini preview models, OpenAI tier for Astra) — owner checks account tiers before launch.
- Grounded answers still contain wrong details → mitigated by two sources + URL checks + labels, not eliminated.
- Run time > 15 min on 14-day plans; customer expectation set on the confirmation screen.
- Gotenberg memory on the 8 GB VPS (one render at a time, memory limit).
- E-mail deliverability from `mail.datavallis.com`.
- Mobile in-app purchase rules (§9).

---

## 9. Mobile app impact (owned by the mobile agent; this lists API needs only)
Additive `/api/v1` changes the app will use: `GET /ai/trip-planner/options` (chips, localized); the extended
`POST /ai/itinerary-plans` (brief, draft + 402 with `plan_id`); `GET /me/itinerary-plans[/{id}]` with `kind`,
`progress` and the premium plan JSON (sections as structured data, not HTML, so the app renders natively);
`GET /me/itinerary-plans/{id}/pdf` (M2); `DELETE /me/itinerary-plans/{id}`; later a push-token endpoint for a "plan
ready" notification. **Risk for the owner:** app stores require in-app purchase for digital goods used in the app;
selling plans inside the app via Stripe may be rejected. Options: buy on the website and view in the app, or
IAP products — an owner decision before the mobile agent builds a purchase screen.

## Must not touch (when implementing)
The classic 1-day/free path and `POST /ai/itineraries`; existing request fields and response fields (additive only);
the chat engines and the free chat cost bucket; the Stripe webhook's idempotency guarantees.

## Acceptance (for the whole task)
M0 report with real per-stage costs/times for the 10 briefs and owner sign-off (§7.3); M1 live on dev with a plan
bought in Stripe test mode, e-mails received, credit returned on a forced failure; ADRs written for D5–D9 when
approved; admin guide and privacy texts updated in the same PRs.

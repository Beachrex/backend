# TASK-001 feedback
Status: DONE (code). The live quality check on dev/uat is an owner action (needs `OPENAI_API_KEY`).

## What I implemented
- **Candidates** (`ItineraryContextBuilderService`, rewritten).
  - Hard filters on real data: country; region/city via translated slug; surface → `beach_type` codes; facilities → `location_infrastructure` codes (AND); accessibility → `access_for_disabled`; `avoid_ferries` → no island beaches.
  - Translations in the requested locale with English fallback, plus type, facilities, user types and activities.
  - Score = log popularity + matched interests + family fit when travelling with children.
  - Relaxation order is facilities, then surface. Accessibility is never relaxed.
- **Planner** (`App\Services\AI\Itinerary\ItineraryPlanner`, new, pure PHP).
  - Deterministic k-means into day clusters; pace sets 2/3/4 beaches per day.
  - Nearest-neighbour order within each day and across days (west → east start); haversine legs and totals.
  - `max_travel_km_per_day` drops outliers.
- **Narrative** (`ItineraryService`, rewritten).
  - The LLM gets the fixed plan plus facts; JSON mode (`response_format`), `max_tokens = min(8000, 800 + 450·days)`.
  - Language as a full name; `finish_reason=length` counts as a failure; one retry, then the deterministic fallback (`metadata.narrative="fallback"`, logged).
  - Merge: beach objects and distances come from the DB; text for beach IDs outside the plan is dropped and logged.
- `ItineraryController`: `NotEnoughBeachesException` → 422 `not_enough_beaches` with `found`/`needed`. No credit is used, because credits are decremented only after success.
- `ChatCompletionRequest::$responseFormat` + `OpenAIProvider` passes `response_format`.
- Default model `gpt-4o-mini` (`config/ai.php`, `.env.example`).

## Files changed (path — why)
- `app/Services/AI/ItineraryContextBuilderService.php`: real retrieval (above).
- `app/Services/AI/ItineraryService.php`: orchestration, prompt, merge, fallback.
- `app/Services/AI/Itinerary/ItineraryPlanner.php`, `NotEnoughBeachesException.php`: new.
- `app/Http/Controllers/AI/ItineraryController.php`: 422 handling + OpenAPI description.
- `app/Services/AI/DTOs/ChatCompletionRequest.php`, `Providers/OpenAIProvider.php`: JSON mode.
- `config/ai.php`, `.env.example`: default model.
- Tests: `tests/Unit/ItineraryPlannerTest.php`, `tests/Feature/AI/ItineraryQualityTest.php`, `tests/Feature/AI/Concerns/SeedsItineraryBeaches.php` (new); `ItineraryControllerTest.php` now seeds beaches.

## Deviations from the task
- The response keeps the documented schema. Added fields: `beach.type`, `beach.suitable_for`, `beach.latitude`, `beach.longitude`, `logistics.notes`, `metadata.narrative`, `metadata.candidates_considered`. `beach.surface` now carries the localized beach-type name (e.g. "Sand"/"Pesek") instead of `null`.
- Two `ItineraryControllerTest` cases previously passed with **zero** beaches in the DB, because the LLM mock invented them. They now seed real beaches. The test intent is unchanged.

## New dependencies
None.

## How to verify
- `php artisan test --filter='ItineraryQualityTest|ItineraryControllerTest|ItineraryPlannerTest'` → 27 passed.
- On dev (owner): `POST /api/v1/ai/itineraries` with `{"destination":{"country_code":"HR"},"duration_days":3,"locale":"sl"}` (logged-in user with a credit). Each day should stay in one area, real beach names, Slovenian text, `metadata.narrative = "llm"`.

## Test results (this session)
- Itinerary tests: 27 passed (planner 8, controller 9, quality 10). Full suite: 316 passed; the only failures are the 4 known env-dependent ones (`PlanEnforcementTest`, `CountrySlugSupportTest`).

## Deliberate breaks
- With the old `ItineraryService` + `ItineraryContextBuilderService` from `origin/dev`, `ItineraryQualityTest` gave 10 failed. Restored: 10 passed.
- Planner formula check: Split → Dubrovnik 164.6 km against an independent equirectangular estimate of 164.8 km. My first expected value in the test was wrong (171) and was corrected; the code was right.

## NOT RUN
- A real OpenAI call (no key in the sandbox). Narrative quality must be judged on dev by the owner.
- Production data volumes: the pool is 150 candidates per request, eager-loaded. Expected to be fine at ~3,800 beaches, but not measured.

## Open questions / risks
- `OPENAI_MODEL` in `/opt/beachrex/envs/.env.*` may be pinned to `gpt-3.5-turbo`. The owner should set `gpt-4o-mini`, or remove the variable so the new default applies.
- Distances are straight-line; road and ferry routing is out of scope.
- Chat (`/ai/chat`) still has the English-only keyword intent detection. That is the next task.

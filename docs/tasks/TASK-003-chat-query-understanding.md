# TASK-003 — AI chat: multilingual query understanding (structured extraction) + real candidates

Depends on: TASK-001 (candidate service)
Read first:
- `app/Http/Controllers/AI/ChatController.php`
- `app/Services/AI/ContextBuilderService.php`
- `app/Services/AI/ItineraryContextBuilderService.php`
- the ADR "Itineraries: code plans, LLM narrates" (2026-10-07)

Requested by the owner, 2026-10-07 (work order item 5, then "ja, začni task").

## Goal
`POST /api/v1/ai/chat` understands a request in any of the 19 site languages, and recommends real beaches that match the place and attributes asked for. Examples:
- "peščena plaža za otroke na Braču" (Slovenian);
- "Sandstrand mit Duschen in Kroatien" (German);
- "spiaggia tranquilla vicino a Makarska" (Italian).

## Diagnosis (2026-10-07)
- **English-only keywords.** `detectIntent()` is English `str_contains` (`'top'` matches "stop", `'vs'` matches any word containing "vs"). `parseCountryFromMessage()` knows only 3 countries and 8 words.
- **No beaches without location.** `ContextBuilderService::buildContext()` fetches beaches only for (a) a popularity intent, (b) a GPS location, or (c) `beach_search` plus a country keyword. "Sandy beach on Brač" with no GPS gets 0 beaches, so the model can only apologise or invent.
- **Attributes are never queried.** Surface and facilities from `context.preferences` are put into `filters`, but `getNearestBeaches()` ignores them. Places (region, island, city) are not understood at all.
- **Inaccurate `filters_applied`.** It is whatever the answer model claims, not what was actually queried.

## Decision (ADR to add: "Chat: code queries, LLM understands and narrates")
Same split as TASK-001: the LLM turns free text into **validated parameters**, the code queries the DB, and the LLM writes the answer from real beaches.

1. **Understanding call** (`App\Services\AI\Chat\QueryUnderstandingService`):
   - one small LLM call, `temperature 0`, `response_format: json_schema` (strict structured output);
   - schema: `intent` (the existing 8 values), `place` (free text or null), `country_code`, `beach_types[]`, `facilities[]`, `user_types[]`, `activities[]`, `near_me`, `days`, `beach_names[]`;
   - the code lists are **enums built from the DB** (`beach_type`, `location_infrastructure`, `user_type`, `sporting_activities` codes, active countries), so the model cannot invent a code;
   - the last 4 conversation messages are included, so follow-ups ("and with a playground?") keep the place;
   - **fallback**: on LLM error, invalid JSON or schema violation, the current keyword detection is used, and `metadata.understanding = "fallback"`.
2. **Place resolution** (code, no LLM):
   - `place` is matched to city, island or region by translated slug or name, in any locale, ignoring case and diacritics;
   - order: exact slug, then exact name, then prefix;
   - the result becomes `city_id`, `island_id` or `region_id` (plus `country_code`);
   - an unresolved place is listed in `assumptions`, never guessed.
3. **Candidates**: `ItineraryContextBuilderService` gets a generic `fetchChatCandidates()`, reusing the TASK-001 query/format code:
   - optional country, region, island or city;
   - optional GPS point plus radius (ordered by `geography` distance, TASK-003 builds on the `PostGISService` fix);
   - beach types, facilities (AND), user types, activities;
   - relax user types, activities, facilities, then beach types, only when nothing matches, and report the relaxation; accessibility is never relaxed;
   - rank by match score and popularity; up to `MAX_BEACHES[tier]`;
   - `popularity_query` keeps "most viewed" ordering within the filters.
4. **Answer call**: the existing prompt and JSON contract, with these changes:
   - the answer language is the **language of the user's message** as detected by the understanding call (fallback: session locale);
   - `filters_applied` comes from the code, not the model;
   - beaches stay restricted to candidate IDs (`enrichBeaches` already drops others).
5. **Plan enforcement** (`itinerary_request` days, `accessibility_query`) uses the extracted intent and `days`, so it works in every language.

## Response contract
Unchanged top-level fields: `session_id`, `message`, `intent`, `beaches`, `filters_applied`, `suggestions`, `metadata`.

Added:
- `metadata.understanding` (`llm` | `fallback`);
- `metadata.language`;
- `metadata.assumptions[]`;
- `filters_applied` keys use the real param names (`country_code`, `region_code`/`island_code`/`city_code`, `beach_type`, `location_infrastructure`, `user_type`, `sporting_activities`).

OpenAPI is updated. The frontend chat keeps working: it only reads `message` and `beaches`; this is to be checked in web-client.

## Cost
+1 small call per message: about 600–1200 input tokens (enums plus history) and at most 200 output tokens, `gpt-4o-mini`. Logged in `ai_usages` like the answer call.

## Not in scope
- Weather forecasts.
- Semantic re-ranking (pgvector / Meilisearch hybrid).
- Streaming.
- Replacing the answer call with tool loops.
- Frontend changes beyond verifying compatibility.

## Tests (real DB, LLM mocked)
- Unit `QueryUnderstandingService`:
  - the schema enums equal the DB codes;
  - unknown codes or an invalid intent → fallback;
  - invalid JSON → fallback;
  - history is passed.
- Place resolution: "Brač" / "brac" / "Bracu" (prefix) → island; "Makarska" → city; an unknown place → assumption, no filter; NFD input.
- Feature chat:
  - a Slovenian message with mocked understanding `{place: "Brač", beach_types: [sand], user_types: [family]}` → only sandy Brač beaches reach the answer prompt, and `filters_applied` reflects them;
  - no GPS and no country → beaches are still returned (popularity within filters);
  - relaxation is reported;
  - accessibility is never relaxed;
  - an answer-model beach ID that is not a candidate is dropped;
  - plan enforcement triggers for an extracted `itinerary_request` with `days: 5` in German;
  - on understanding failure the keyword fallback is used and the response still succeeds.
- Regression: "stop" no longer means `popularity_query` (fallback path, word boundaries).
- The existing `ChatControllerTest` / `PlanEnforcementTest` keep passing.

## Acceptance criteria
- All tests pass, with a deliberate break shown; the full suite shows only the known env-dependent failures.
- After the dev deploy (owner, live): 5 test questions in sl, hr, de, it and en give real, relevant beaches. Recorded in `TASK-003.feedback.md`.
- ADR, `DEV_HANDOFF.md`, OpenAPI and HANDOFF are updated.

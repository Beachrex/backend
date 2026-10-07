# TASK-001 — AI itinerary quality: real beaches, geographic days, honest narrative

Depends on: —
Read first: docs/ai/implementation/DECISIONS.md (2026-10-07 "Itineraries: code plans, LLM narrates"),
`app/Services/AI/ItineraryService.php`, `ItineraryContextBuilderService.php`, `BeachFilterService.php`

## Goal
`POST /api/v1/ai/itineraries` returns a plan built from real Beachrex beaches. It honours the destination
(country/region/city), surface, facilities and accessibility. Each day's beaches are geographically close,
with real distances, and the text is in the requested language.

## Diagnosis (2026-10-07, why it was bad)
- Filters used columns that don't exist (`surface`, `showers`, `toilets`, `wheelchair_*`, `region.slug`), so they
  returned a 500 or the wrong beaches. `parking` was inverted (1 = no).
- The model got name + city + views per beach: no description, type, facilities or coordinates.
- No geography: the model invented the day grouping and the distances.
- No JSON mode, fixed `max_tokens` 3000 (14-day plans were cut off), and the language was given as a bare code ("Respond in sl language").
- Default model `gpt-3.5-turbo`.

## Scope
1. **Candidates** (`ItineraryContextBuilderService`).
   - Hard filters on real data: country; region/city by translated slug; surface → `beach_type` codes; facilities → `location_infrastructure` codes (AND); accessibility → `access_for_disabled`; `avoid_ferries` → exclude island beaches.
   - Load translations (requested locale, English fallback), type, facilities, user types and activities.
   - Soft ranking: interests (activities, family, quiet, nightlife, nature), children in the travel party, and popularity.
   - Relax facilities, then surface, when too few beaches match. **Never relax accessibility.** Every relaxation is listed in `assumptions`.
2. **Planner** (`App\Services\AI\Itinerary\ItineraryPlanner`, pure PHP).
   - k-means (deterministic farthest-point init) into `duration_days` geographic clusters.
   - Beaches per day by pace (relaxed 2, balanced 3, packed 4). Nearest-neighbour order within each day and across days.
   - Haversine distances. `max_travel_km_per_day` is enforced by dropping outliers.
3. **Narrative** (`ItineraryService`).
   - The LLM gets the fixed skeleton plus facts and returns JSON (`response_format: json_object`) with title, summary, day themes, per-beach notes/why, optional generic extras (no named businesses) and assumptions.
   - The code merges it: beach objects and distances always come from the DB, and unknown beach IDs are ignored.
   - On invalid JSON or `finish_reason=length`: one retry, then a deterministic fallback text with `metadata.narrative = "fallback"` and a log warning.
   - `max_tokens` scales with the number of days; the language is passed as a full name; the default model is `gpt-4o-mini` (`OPENAI_MODEL` overrides).
4. The response schema stays compatible: same top-level fields. Added: beach `latitude`, `longitude`, `type`, `suitable_for`; `metadata.narrative`.

## Not in scope (later tasks)
- Chat intent extraction (multilingual function calling).
- Weather forecast in plans.
- Semantic re-ranking (Meilisearch hybrid or pgvector).
- Road or ferry routing (distances are straight-line).

## Tests
- Unit, planner: clusters separate distant areas; within-day distance stays under the limit; deterministic; fewer beaches than days → exception; pace → beaches per day.
- Feature, real DB with seeded test beaches in 3 Croatian areas and a mocked LLM:
  - days are geographically coherent;
  - only DB beach IDs appear;
  - facility, surface and region filters are honoured;
  - relaxation is noted;
  - accessibility is never relaxed (422);
  - the language name is in the prompt;
  - an invalid LLM JSON gives the fallback narrative;
  - an LLM-invented beach ID is dropped.
- Existing auth/402/422 tests keep passing.

## Acceptance criteria
- All tests above pass, including a deliberate break; the full suite shows only the known env-dependent failures.
- `DEV_HANDOFF.md`, OpenAPI (if the response changed) and HANDOFF are updated.

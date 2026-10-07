# TASK-003 — feedback (implementer's report)

Spec: [`TASK-003-chat-query-understanding.md`](TASK-003-chat-query-understanding.md) · ADR 2026-10-07 "Chat: Code Queries, LLM Understands and Narrates"

## What was built
- `QueryUnderstandingService`: strict `json_schema` extraction, with DB code enums and the last 4 messages; null on failure.
- `PlaceResolver`: island/city/region from translations in all locales, with inflection handling.
- `ItineraryContextBuilderService::fetchChatCandidates()`: shared with TASK-001; relaxes only when nothing matches; accessibility is never relaxed.
- `ContextBuilderService`: candidate context and the facts in the prompt.
- `ChatController`:
  - understood intent and days for plan enforcement;
  - answer in the user's language;
  - `filters_applied` from the code;
  - tokens and cost of both calls counted;
  - keyword fallback with word boundaries;
  - history order fix.

Deviation from the spec: relaxation happens when **no** beach matches (the spec draft said fewer than 3). A specific question ("sand on Brač") with one exact match should get that beach, not looser ones.

## Proof (this session, local PostgreSQL 16 + PostGIS, LLM mocked)
```
  ✓ slovenian place and attributes become real filters                   4.31s  
  ✓ the understanding schema offers only codes that exist                0.41s  
  ✓ without gps or country attributes still return beaches               0.35s  
  ✓ relaxation is reported when nothing matches                          0.37s  
  ✓ accessibility is never relaxed                                       0.32s  
  ✓ answer beaches outside the candidates are dropped                    0.38s  
  ✓ unknown place is an assumption not a guess                           0.35s  
  ✓ plan enforcement uses understood days in any language                0.25s  
  ✓ understanding failure falls back to keywords                         0.24s  
  ✓ invalid understanding json falls back                                0.27s  
  ✓ unknown codes from the model are dropped                             0.35s  
  ✓ keyword fallback uses word boundaries                                0.28s  
  ✓ history is passed to the understanding call                          0.42s  
  ✓ names and spellings                                                  0.14s  
  ✓ inflected forms                                                      0.11s  
  ✓ island wins over a town with the same name                           0.11s  
  ✓ unknown or too short is null                                         0.10s  
  ✓ display name uses the locale                                         0.10s  
  Tests:    18 passed (66 assertions)
```
Full suite:
```
   FAILED  Tests\Feature\AI\PlanEnforcementTest > anonymous user can access…    
   FAILED  Tests\Feature\AI\PlanEnforcementTest > rate limit headers are set    
   FAILED  Tests\Feature\CountrySlugSupportTest > weather endpoint accepts s…   
   FAILED  Tests\Feature\CountrySlugSupportTest > weather endpoint accepts c…   
  Tests:    4 failed, 6 skipped, 407 passed (1807 assertions)
```
(the 4 failures are the known env-dependent ones)

Deliberate breaks, each run against the two test files above:

| Break | Result |
|---|---|
| Place filter ignored in `chatQuery()` | 1 failed (`slovenian place and attributes become real filters`) |
| Model's `filters_applied` kept | 3 failed |
| No inflection matching in `PlaceResolver` | 2 failed |
| Accessibility made relaxable | 1 failed (`accessibility is never relaxed`) |

## NOT RUN
- **A real OpenAI call.** The sandbox has no outbound access, so strict `json_schema` support for the configured model is not verified live. `gpt-4o-mini` supports it. If the provider rejects the schema, the chat still works through the fallback (`metadata.understanding = fallback`) and the logs show "Chat query understanding failed".
- **The live check on dev (owner).** Ask these after the deploy and note whether the beaches fit and `metadata.understanding` is `llm`:

| # | Question | Expected |
|---|---|---|
| 1 | sl: "peščena plaža za otroke na Braču" | sandy family beaches on Brač, answer in Slovenian |
| 2 | hr: "plaže s tušem u Makarskoj" | Makarska beaches with showers |
| 3 | de: "ruhiger Strand mit Parkplatz in Istrien" | Istria, parking |
| 4 | it: "spiaggia accessibile in sedia a rotelle vicino a Dubrovnik" | accessible beaches only (or 402 for the free tier) |
| 5 | en: "most popular beaches in Croatia" | popularity order, HR |

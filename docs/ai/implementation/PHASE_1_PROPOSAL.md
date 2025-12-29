Got it - here’s the Phase 1 output (Architecture + API Contracts) that your Task #2 agent should produce. English only, OpenAPI-first, no code.

Task #2 Phase 1 Proposal - Trip Itinerary Generation

1) Endpoint decision

Decision: Add a dedicated endpoint

Add: POST /api/v1/ai/itineraries

Rationale:
•	Itineraries are a paid, structured output with different payload shape and guardrails than chat.
•	Keeps /ai/chat focused on discovery, Q&A, and recommendations.
•	Easier OpenAPI typing and frontend client generation.
•	Clear monetization boundary (always 402 unless entitled).

How it integrates:
•	Reuses the existing AI core components: optional auth, plan enforcement, rate limiting, token budget, circuit breaker, LLM abstraction, logging, AI sessions/messages/usage tables.
•	May optionally link to an ai_session_id for continuity, but does not require chat.

Alternative (not chosen): extend POST /api/v1/ai/chat with intent=itinerary_request.
•	Rejected because it mixes two very different response models and increases risk of leaking paid content through chat responses.

⸻

2) Request schema (OpenAPI-ready)

Endpoint

POST /api/v1/ai/itineraries

Request body: AiItineraryRequest

Required:
•	destination
•	country_code (string, ISO 2, example: “HR”)
•	Optional: region_slug (string, example: “dalmatia”)
•	Optional: city_slug (string, example: “split”)
•	duration_days (integer enum: 3 | 7 | 14)
•	start_date (string date, optional) - if omitted, itinerary is date-agnostic
•	travel_party (optional object)
•	adults (int, default 2)
•	children (int, default 0)
•	preferences (optional object)
•	pace (enum: “relaxed” | “balanced” | “packed”)
•	interests (array of enum strings, optional): [“swimming”, “snorkeling”, “family”, “quiet”, “nightlife”, “nature”]
•	must_have_facilities (array of strings, optional): [“parking”, “showers”, “toilets”, “lifeguard”]
•	surface_types (array of strings, optional): [“sand”, “pebble”, “rock”, “concrete”]
•	accessibility (array of strings, optional): [“wheelchair_accessible”, “ramp_available”]
•	constraints (optional object)
•	max_travel_km_per_day (int, optional, default 80)
•	avoid_ferries (bool, optional, default false)
•	locale (string, optional) - if missing use Accept-Language and backend locale resolution
•	session_id (uuid, optional) - if user wants continuity with AI chat session

Validation rules:
•	duration_days must be 3/7/14 only.
•	destination.country_code required.
•	arrays must be capped (e.g., interests max 10, facilities max 10).
•	reject oversized payloads (413 handled by token budget middleware and/or request size limits).

Example request:

{
"destination": { "country_code": "HR", "region_slug": "dalmatia", "city_slug": "split" },
"duration_days": 7,
"preferences": {
"pace": "balanced",
"must_have_facilities": ["parking", "showers"],
"surface_types": ["pebble", "sand"],
"interests": ["swimming", "family"]
},
"constraints": { "max_travel_km_per_day": 60 },
"locale": "en",
"session_id": "019b6be2-2aca-72e2-abae-4b640ac6f25b"
}


⸻

3) Response schema (OpenAPI-ready)

Success 200: AiItineraryResponse

Fields:
•	itinerary_id (uuid) - generated id for tracking and caching
•	session_id (uuid|null) - if linked to chat session
•	title (string) - localized
•	summary (string) - 2-5 sentences
•	destination (object)
•	country_code
•	optional region_slug
•	optional city_slug
•	duration_days (3|7|14)
•	days (array length = duration_days)
•	Each day:
•	day_number (int)
•	theme (string, short)
•	base_area (string, e.g., “Split”)
•	items (array, 2-6 items)
•	Each item:
•	type (enum: “beach” | “activity” | “food” | “viewpoint”)
•	time_block (enum: “morning” | “afternoon” | “evening”)
•	beach (nullable object, required if type=“beach”)
•	id (int)
•	name (string)
•	slug (string)
•	city (string|null)
•	country_code (string)
•	surface (string|null)
•	facilities (array of strings)
•	distance_km (number|null) - only when user location is provided (usually not for itineraries)
•	notes (string, 1-3 sentences)
•	why (string, short justification, must not claim ratings/reviews unless you have them)
•	logistics (object)
•	estimated_travel_km (number|null)
•	parking_notes (string|null)
•	accessibility_notes (string|null)
•	assumptions (array of strings) - transparent statements, e.g., “Ordered beaches by visitor interest (beach_views).”
•	metadata (object)
•	request_id (uuid)
•	tier (string)
•	tokens_used (int)
•	model (string)
•	response_time_ms (int)
•	cached (bool)

Important content constraints:
•	No bookings, no external purchases, no claims about “best rated” unless you have rating data.
•	If popularity is used, be transparent: “frequently viewed” / “based on visitor interest (beach_views)”.

Example response (truncated):

{
"itinerary_id": "3bfb6fd6-1d18-4a19-8f0d-3f4f7c1a0a0c",
"session_id": "019b6be2-2aca-72e2-abae-4b640ac6f25b",
"title": "7-Day Beach Itinerary - Dalmatia, Croatia",
"summary": "A balanced week around Split and nearby islands, mixing popular beaches with quieter coves.",
"destination": { "country_code": "HR", "region_slug": "dalmatia", "city_slug": "split" },
"duration_days": 7,
"days": [
{
"day_number": 1,
"theme": "Split classics",
"base_area": "Split",
"items": [
{
"type": "beach",
"time_block": "morning",
"beach": { "id": 614, "name": "Beach Bacvice", "slug": "beach-bacvice", "city": "Split", "country_code": "HR", "surface": "sand", "facilities": ["parking", "showers"], "distance_km": null },
"notes": "Arrive early for easier parking and calmer water.",
"why": "Good facilities and easy access for a first day."
}
],
"logistics": { "estimated_travel_km": 10, "parking_notes": "Use nearby paid lots if street parking is full.", "accessibility_notes": null }
}
],
"assumptions": ["Beach ordering uses visitor interest (beach_views) when no explicit location routing is provided."],
"metadata": { "request_id": "94f27541-491e-49ba-89ac-e8af24eaafaa", "tier": "basic", "tokens_used": 1820, "model": "gpt-3.5-turbo", "response_time_ms": 4100, "cached": false }
}


⸻

4) Guardrails

402 enforcement (paid gating)
•	If user is not entitled to itineraries (free tier or no purchase):
•	Return 402 Payment Required immediately.
•	No LLM call.
•	Response includes product type “itinerary”, duration_days, price from config/ai.php, and purchase_url.

Token/cost caps
•	Reuse existing TokenBudgetMiddleware and circuit breakers.
•	Itineraries are heavier than chat, so cap:
•	max itinerary generation attempts per hour per user (reuse rate limit tiers, optionally stricter for itineraries later).
•	max output size: enforce schema and limit day items.

Output limits
•	days.items: 2-6 items per day.
•	Beaches per itinerary: hard cap, e.g. 1-2 beach items per day.
•	No multi-destination itineraries in MVP.

Failure modes
•	LLM returns invalid JSON: respond with safe 500 or structured fallback (no paid content leak).
•	LLM timeout: 500 with safe message; log provider latency.
•	Token truncation: if truncation occurs, do not return partial itinerary for free users. For paid users, return a valid shorter itinerary but must be clearly labeled (or return 500 and ask to retry - pick one and document it).

Caching
•	Optional: cache paid itinerary results per user+payload hash for short TTL (e.g., 15 min) to reduce cost on retries.
•	Must not allow free users to access cached paid results.

Observability
•	Log: request_id, itinerary_id, user_id (nullable), tier, duration_days, destination scope, tokens, cost_usd, response_time_ms.
•	No PII. No raw messages in logs.

⸻

5) Error model (status codes and triggers)
   •	400 Bad Request - invalid schema (missing destination/duration_days), invalid enums.
   •	402 Payment Required - itinerary requested without entitlement.
   •	413 Payload Too Large - token budget exceeded (request too complex or internal budget hit).
   •	429 Too Many Requests - rate limit exceeded (tier-based).
   •	503 Service Unavailable - circuit breaker open or provider degraded.
   •	500 Internal Server Error - unexpected failure, LLM parse error, DB failure, etc.

Each error must match the existing canonical error payload shapes from api-contracts.md (or define a new itinerary-specific error schema only if necessary).

⸻

6) Docs to update (names only)

Must update:
•	docs/ai/architecture/api-contracts.md
•	Add new section for POST /api/v1/ai/itineraries
•	Include request/response schemas and all error responses
•	docs/ai/architecture/prompt-structure.md
•	Add itinerary-specific system prompt + strict JSON schema output rules
•	Add “no leak” rules for 402 and failures
•	docs/ai/architecture/mvp-integration.md
•	Add itinerary flow diagram and reuse of middleware/services
•	docs/ai/implementation/DECISIONS.md
•	ADR: choose dedicated endpoint vs chat extension
•	docs/ai/implementation/TASKS.md
•	Add Task #2 step breakdown (contracts, OpenAPI, controller/service, tests, playground support)
•	OpenAPI schema locations (as per your standard):
•	Add schemas for AiItineraryRequest, AiItineraryResponse, and itinerary item types.

⸻

Suggested Phase 2 scope (preview only, no code now)
•	Add endpoint controller POST /api/v1/ai/itineraries
•	Add PlanEnforcementService check for entitlement + price lookup for 402
•	Add ItineraryContextBuilder to fetch beaches by destination and preferences (use beach_views when needed)
•	Add LLM prompt template enforcing strict JSON
•	Add tests for 402/429/413/503 + happy path for entitled user
•	Add internal playground support (dev/staging only)

If you want, I can also draft the exact OpenAPI schemas (request/response) in a format your Swagger annotations can reference, still without writing code.

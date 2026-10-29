# TASK-009 — Switch the AI beach guide on/off in admin

**Owner request (2026-10-29):** "Can we switch the AI guide on/off in admin? I am putting the site on production, Stripe
is not on production yet and the guide still needs testing, so I would switch it off on prod."

## Decisions (agent)

- **Per-environment DB setting**, edited in admin (System → Site settings), table `site_settings`. Default **on** (seeded by
  the migration), so deploying changes nothing; the owner switches prod off himself.
- **Off means off everywhere:** website hides the menu entry and `llms.txt` line, `/ai/*` and `/payment/*` pages answer
  404 (not indexed); the API answers 503 `ai_disabled` on `/api/v1/ai/*` and `POST /payments/stripe/checkout` (also
  protects the mobile app and direct API use). Reading existing plans (`/me/itinerary-plans`) stays allowed.
- **Public read endpoint** `GET /api/v1/site-settings` (additive) so the website and the app know the state.
- `AI_ENABLED` (env) stays as the server-level kill switch; the admin switch is an extra condition, not a replacement.
- Website picks up a change within its ISR window (≤ 5 min); the API immediately (cache cleared on save).

## Acceptance

- Admin toggles; non-admin 403, guest → login.
- Off: API 503 `ai_disabled` on chat, itineraries, itinerary plans, checkout; website without the menu entry; `/ai` 404.
- On: unchanged behaviour.

## Amendment 2026-10-29: two switches (chat / trip planner)

**Owner (2026-10-29):** "to sva se narobe razumela, želel sem skriti trip planner, ker je plačljiv, ne pa chat.... dodaj
to, da lahko v adminu izklopim eno ali drugo ali pa oboje,... potem bom trip planner izklopil na prod."

- `ai_assistant_enabled` now means **AI chat** only (key kept for API compatibility).
- New key `ai_trip_planner_enabled` (default on, idempotent migration `2026_10_29_150000_…`) for the **paid trip planner**.
- Route → switch: chat = `POST /ai/chat`, `POST /ai/test` (503 `ai_disabled`); planner = `POST /ai/itineraries`,
  `POST /ai/itinerary-plans`, `GET /payments/products`, `POST /payments/stripe/checkout` (503 `ai_trip_planner_disabled`);
  never switched = `POST /payments/stripe/webhook`, `GET /me/entitlements`, `GET /me/itinerary-plans[/{id}]`.
- Website: chat page, widget and "AI beach chat" menu entry follow the chat switch; planner pages (`/ai/itinerary/*`),
  `/payment/*`, the "AI trip planner" menu entry and planner links follow the planner switch; `/ai` and the menu group
  disappear only when both are off.

# TASK-010 — AI chat as a widget on every page, aware of the current page

**Owner request (2026-10-29):** "bi ta AI chat naredili kot widget na strani? da je na vseh straneh, podstraneh, da se
lahk karkoli pogovarjaš, dobro bi še blo da bi še vedel na kateri strani se nahajaš, če gre?"
Later the same day: the conversation must survive closing the tab or browser by accident, with a "New conversation" button.

## Scope

1. **Backend** (this repo, additive): optional `page_context` on `POST /api/v1/ai/chat`
   (`page_type` enum, `path`, `beach_id`, `title`). Strict validation (422 on anything else), turned into one short
   server-built line for the model (`App\Services\AI\Chat\PageContextHint`). Beach pages: name, town and country are read
   from the database by `beach_id` (active beaches only); the client `title` is ignored there. Other pages: the title is
   cleaned (control/bidi characters, brackets, markup removed), cut to 80 characters and quoted as data.
   Both chat engines (`pipeline`, `agent`) use the hint; the agent's system prompt stays byte-stable (the hint goes into
   the per-message context line). Response `metadata.page_context_used`.
2. **Web** (`Beachrex/web-client`): `AiChatWidget` mounted once in the locale layout, hidden when the AI switch is off
   (TASK-009) and on `/ai/chat`; floating button → panel (bottom sheet / full screen on phones); conversation kept in a
   provider above the pages and in `localStorage` (`beachrex.aiChat.v1`, last 50 messages, 7 days); sends `page_context`
   derived from the path and the beach page's id.

## Must not touch
- The AI on/off switch semantics (TASK-009), the agent system prompt text, existing request fields.

## Tests
- `tests/Feature/AI/ChatPageContextTest.php`: beach resolved server-side, English fallback, inactive/unknown beach,
  title cleaning/truncation/quoting, home page, absent context unchanged, 12 invalid shapes → 422, exact limits accepted,
  pipeline prompt carries the hint. Deliberate break: without the validation rules 17 of 19 tests fail.
- Web: `e2e/ai-chat-widget.unit.spec.ts` (page-context derivation, visibility, persistence helpers).

## Acceptance
- Off switch → no widget, no request. On → widget on every page except `/ai/chat`; on a beach page "is this beach good
  for kids?" is answered about that beach.

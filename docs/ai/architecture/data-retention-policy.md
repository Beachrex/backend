# AI Data Retention & Redaction Policy

**Last Updated**: 2026-10-29
**Status**: chat retention **Built** (`ai:prune-conversations`, scheduled daily; Live on an environment once deployed
there) · everything else in this file describes the code as it is.

> **History.** The 2025-12-23 version of this file ("APPROVED") set 30 days for messages, 90 days for sessions and
> 365 days for usage rows, with an `ai:cleanup-expired-data` job. That job was never implemented: until 2026-10-29
> nothing deleted `ai_sessions`, `ai_messages` or `ai_usage`. On 2026-10-29 the owner approved **180 days** for chat
> conversations ("hramba chatov ok", ADR 2026-10-29 "AI chat history in the admin", amendment). This version replaces
> the old periods.

---

## 1. Overview

- Chat conversations (questions and answers) are kept **180 days after their last activity**, then deleted
  automatically. They are kept that long so that the team can review them in the admin (**AI → Chat conversations**)
  to improve the answers (owner 2026-10-29).
- Cost rows stay for statistics and the daily cost limits, but lose the link to the deleted conversation and the
  pseudonymous identifiers (IP hash, user agent).
- Paid trip-planner data (purchases, credits, itinerary plans) is **not** part of chat retention: it lives with the
  user account.
- IP addresses are stored only as a SHA-256 hash. Chat text is not written to logs.
- The privacy policy (static page `privacy-policy`, all 19 languages) states the 180 days and how to ask for earlier
  deletion (`info@beachrex.com`).

---

## 2. Data stored

### `ai_sessions` — one chat conversation
User (nullable), tier, locale, engine, `page_context` (first page the chat was opened on), message/token counters,
`ip_address` (**SHA-256 hash**), `user_agent`, `last_activity_at`, `expires_at` (30 min sliding: ends the conversation
for the model only, deletes nothing), timestamps.
**Retention: 180 days after `last_activity_at`** (see §4).

### `ai_messages` — the turns of a conversation
Role, `content` (question or answer text), intent, `context` (tool calls / beaches shown), tokens, `page_context`,
`error` (turn without an answer), `request_id`. FK `session_id → ai_sessions` **ON DELETE CASCADE**.
**Retention: deleted together with their session.**

### `ai_usage` — cost of each LLM call
User (nullable, `ON DELETE SET NULL`), `session_id` (nullable, `ON DELETE SET NULL`), tier, event type, provider, model,
tokens, `cost_usd`, latency, locale, `user_agent`, `ip_address` (hash), `request_id`, timestamps.
**Retention: kept** (cost statistics, daily cost limits). When its conversation is deleted, the row keeps time, model,
tokens and cost; `session_id`, `ip_address` and `user_agent` are set to NULL. No automatic deletion of usage rows
(no period decided).

### Not part of chat retention
`purchases`, `user_entitlements`, `itinerary_plans` (paid trip planner) — kept while the user account exists (FK
`user_id` cascade on account deletion); purchases are also accounting records.

---

## 3. Retention periods

| Data | Retention | Mechanism |
|---|---|---|
| `ai_sessions` + `ai_messages` | 180 days after the last activity (`AI_CHAT_RETENTION_DAYS`) | `ai:prune-conversations`, daily 01:40 Europe/Ljubljana |
| `ai_usage` | kept; unlinked and stripped of IP hash / user agent when its conversation is deleted | same command |
| Trip plans, purchases, credits | while the account exists | FK cascade on user deletion |
| Database backups | `BACKUP_KEEP_DAYS` (default 14) | `db:backup` prunes old dumps — a deleted conversation can stay in a backup that long |
| Application logs | no chat text is logged | — |

---

## 4. Automated cleanup — `ai:prune-conversations`

`app/Console/Commands/PruneAiConversations.php`, scheduled in `routes/console.php`.

```
php artisan ai:prune-conversations --dry-run      # counts only
php artisan ai:prune-conversations                # delete (what the scheduler runs)
php artisan ai:prune-conversations --days=90      # other period for this run
```

- **Expired** = `last_activity_at < now − days` (or `created_at` when `last_activity_at` is NULL) **and** no message
  with `created_at ≥ now − days` (guard against a stale `last_activity_at`).
- Per chunk (`--chunk`, default 200 sessions; a session has at most 50 messages) in one transaction:
  `ai_usage` → `session_id`, `ip_address`, `user_agent` = NULL; delete `ai_messages`; delete `ai_sessions`.
  Explicit statements, not only the FK actions; query builder, no model events.
- Logs one info line with counts (`sessions`, `messages`, `usage_unlinked`, `days`, `cutoff`), never chat text.
- `--days` must be an integer ≥ 1; `AI_CHAT_RETENTION_DAYS=0` turns the scheduled run off (the command itself refuses 0).
- Schedule: daily 01:40 Europe/Ljubljana (before the 02:30 UTC backup), `withoutOverlapping`.
- Tests: `tests/Feature/AI/PruneAiConversationsTest.php`.

---

## 5. Deletion on request (GDPR right to erasure)

- **One conversation / one visitor's chats:** no admin action or command yet. A developer deletes the `ai_sessions`
  rows (messages cascade; set `ai_usage.session_id`, `ip_address`, `user_agent` to NULL for those sessions). Anonymous
  visitors can be identified only by the time, the page and the question text they remember.
- **Account deletion:** `ai_sessions.user_id` and `ai_usage.user_id` become NULL (FK `nullOnDelete`); trip plans,
  credits and purchases are deleted with the account (cascade). The anonymous conversations then age out after 180
  days.
- **Planned (not built):** `ai:redact-user-data {userId}` and a self-service "delete my AI history" in the account.

---

## 6. Open items

- Retention of `ai_usage` itself (no period decided; rows hold no chat text).
- Admin/command for deleting one conversation on request (today a developer task).
- Monitoring: alert when the oldest `ai_sessions.last_activity_at` is older than retention + a few days (job not
  running).

---

## 7. References

- ADR log `docs/ai/implementation/DECISIONS.md`: 2026-10-29 "AI chat history in the admin" (+ amendment, retention),
  2026-10-29 "AI chat daily cost cap and Turnstile".
- Admin guide `docs/guides/admin/ai-pogovori.md`.
- Privacy and cookie policy texts: `database/content/static-pages/*.json` (applied with `static-pages:sync`).
- GDPR Art. 5(1)(e) (storage limitation), Art. 17 (erasure), Art. 25 (data protection by design).

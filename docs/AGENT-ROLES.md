# Agent roles

Owner, 2026-10-09: two agents work on Beachrex in parallel. The owner says which one a session is.

| Role | When | Repos | Start here | Scope |
|---|---|---|---|---|
| **Web agent** (default) | Any session where the owner does **not** say "mobile agent" | `Beachrex/backend`, `Beachrex/web-client` | `docs/HANDOFF.md` | Website (desktop + mobile web), backend API, admin, MCP, infrastructure docs. Also the backend/web tasks the app needs (`docs/mobile/MOBILE-TASKS.md` §B) |
| **Mobile agent** | The owner says "ti si mobile agent" / "mobile agent" | `Beachrex/mobile-app` (code); reads `Beachrex/backend` docs and API | `docs/mobile/MOBILE-PLAN.md`, then `docs/mobile/MOBILE-TASKS.md` | The iOS/Android app (Expo, React Native, NativeWind, EAS) |

Shared rules for both: `docs/DEVELOPMENT-RULES.md` (git flow, proof by output, tests, secrets, owner decides
promotion and releases), talk to the owner in Slovenian, code/docs/commits in English.

## Boundaries

- The mobile agent does **not** change backend or web-client code. When the app needs an API change, it writes
  the need into `docs/mobile/MOBILE-TASKS.md` §B (docs-only PR in the backend repo) and tells the owner; the web
  agent implements it.
- The web agent does **not** change the mobile repo. Every `/api/v1` change keeps old fields working
  (additive only) because installed apps cannot be forced to update (`DEVELOPMENT-RULES.md` §9).
- Both keep `docs/HANDOFF.md` current: the web agent in its sections, the mobile agent in "Mobile app".
- The ADR log `docs/ai/implementation/DECISIONS.md` is shared; mobile decisions are titled "Mobile: …".

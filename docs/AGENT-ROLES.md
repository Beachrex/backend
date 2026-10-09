# Agent roles

Owner, 2026-10-09: two agents work on Beachrex in parallel. The owner says which one a session is.

| Role | When | Repos | Start here | Scope |
|---|---|---|---|---|
| **Web agent** (default) | Any session where the owner does **not** say "mobile agent" | `Beachrex/backend`, `Beachrex/web-client` | `docs/HANDOFF.md` | Website (desktop + mobile web), backend API, admin, MCP, infrastructure docs. Also the backend/web tasks the app needs (`docs/mobile/MOBILE-TASKS.md` §B) |
| **Mobile agent** | The owner says "ti si mobile agent" / "mobile agent" | `Beachrex/mobile-app` (code); reads `Beachrex/backend` docs and API | `docs/mobile/MOBILE-PLAN.md`, then `docs/mobile/MOBILE-TASKS.md` | The iOS/Android app (Expo, React Native, NativeWind, EAS) |

Shared rules for both: `docs/DEVELOPMENT-RULES.md` (git flow, proof by output, tests, secrets, owner decides
promotion and releases), talk to the owner in Slovenian, code/docs/commits in English.

## Boundaries

- The mobile agent does **not** change backend or web-client code or docs. When the app needs an API change or a
  doc update, it tells the owner; the web agent adds it to `docs/mobile/MOBILE-TASKS.md` §B and implements it.
- The web agent does **not** change the mobile repo. Every `/api/v1` change keeps old fields working
  (additive only) because installed apps cannot be forced to update (`DEVELOPMENT-RULES.md` §9).
- `docs/HANDOFF.md` is written by the web agent; the "Mobile app" section carries the mobile agent's status as relayed by the owner.
- The ADR log `docs/ai/implementation/DECISIONS.md` is shared; mobile decisions are titled "Mobile: …".

## Who edits which doc (owner, 2026-10-09: no conflicts between the two agents)

The mobile agent does **not** change the backend repo, not even docs. It sends what must change in the docs to the owner, who passes it to the web agent; the web agent makes the change in a small docs PR into `dev`. So only one agent ever writes in `Beachrex/backend`, and the two agents cannot conflict there.

| Doc | Who writes it | Source of the content |
|---|---|---|
| `docs/mobile/MOBILE-PLAN.md`, `docs/mobile/MOBILE-TASKS.md` | Web agent | Mobile agent / owner requests (relayed by the owner); the web agent updates the B-M status itself |
| `docs/HANDOFF.md` section "Mobile app" | Web agent | Mobile agent status sent through the owner |
| Every other backend doc (`HANDOFF.md`, `DEV_HANDOFF.md`, `DEVELOPMENT-RULES.md`, runbooks, `DECISIONS.md`) | Web agent | — (mobile ADRs titled "Mobile: …", written on request) |
| Everything in `Beachrex/mobile-app` (code, `CLAUDE.md`, README, changelog, its own ADR notes) | Mobile agent | — |
